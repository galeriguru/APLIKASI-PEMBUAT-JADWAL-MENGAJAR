<?php
require_once 'config/database.php';
session_start();

$_SESSION['emergency_placements'] = [];
$_SESSION['unplaced_blocks'] = [];

mysqli_query($koneksi, "DELETE FROM tbl_jadwal WHERE (id_waktu, id_kelas) NOT IN (SELECT id_waktu, id_kelas FROM tbl_jadwal_wajib)");

function pecah_jam_ke_blok($jam) {
    if ($jam <= 0) return [];
    if ($jam == 1) return [1];
    if ($jam == 2) return [2];
    if ($jam == 3) return [3];
    if ($jam == 4) return [2, 2];
    if ($jam == 5) return [3, 2];
    if ($jam == 6) return [3, 3];

    $blocks = [];
    while ($jam > 0) {
        if ($jam >= 3) {
            $blocks[] = 3;
            $jam -= 3;
        } else {
            if ($jam > 0) $blocks[] = $jam;
            $jam = 0;
        }
    }
    return $blocks;
}

function tryPlaceChunk($blok, $ukuran_coba, &$jadwal_guru_terisi, &$jadwal_kelas_terisi, $all_waktu_slots, $stmt) {
    $possible_indices = range(0, count($all_waktu_slots) - $ukuran_coba);
    shuffle($possible_indices);

    foreach ($possible_indices as $i) {
        $slot_awal = $all_waktu_slots[$i];
        $slot_akhir = $all_waktu_slots[$i + $ukuran_coba - 1];
        if ($slot_awal['hari'] != $slot_akhir['hari'] || $slot_awal['jam_ke'] + $ukuran_coba - 1 != $slot_akhir['jam_ke']) {
            continue;
        }

        $potongan_slot = array_slice($all_waktu_slots, $i, $ukuran_coba);
        $chunk_bebas = true;
        foreach ($potongan_slot as $slot) {
            if (isset($jadwal_guru_terisi[$slot['id_waktu']][$blok['id_guru']]) || isset($jadwal_kelas_terisi[$slot['id_waktu']][$blok['id_kelas']])) {
                $chunk_bebas = false;
                break;
            }
        }

        if ($chunk_bebas) {
            foreach ($potongan_slot as $slot) {
                mysqli_stmt_bind_param($stmt, 'iii', $slot['id_waktu'], $blok['id_kelas'], $blok['id_guru_mapel']);
                mysqli_stmt_execute($stmt);
                $jadwal_guru_terisi[$slot['id_waktu']][$blok['id_guru']] = true;
                $jadwal_kelas_terisi[$slot['id_waktu']][$blok['id_kelas']] = true;
            }
            return true;
        }
    }
    return false;
}

$guru_mapel_res = mysqli_query($koneksi, "SELECT id_guru_mapel, id_guru, jam_per_minggu FROM tbl_guru_mapel WHERE jam_per_minggu > 0");
$all_guru_mapel = mysqli_fetch_all($guru_mapel_res, MYSQLI_ASSOC);

$kelas_res = mysqli_query($koneksi, "SELECT id_kelas FROM tbl_kelas");
$all_kelas_ids = array_column(mysqli_fetch_all($kelas_res, MYSQLI_ASSOC), 'id_kelas');

$all_waktu_slots = [];
$waktu_res = mysqli_query($koneksi, "SELECT id_waktu, hari, jam_ke FROM tbl_waktu_pelajaran ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jam_ke ASC");
while ($row = mysqli_fetch_assoc($waktu_res)) {
    $all_waktu_slots[] = $row;
}

$jadwal_guru_terisi = [];
$jadwal_kelas_terisi = [];
$locked_res = mysqli_query($koneksi, "SELECT jw.id_waktu, jw.id_kelas, gm.id_guru FROM tbl_jadwal_wajib jw JOIN tbl_guru_mapel gm ON jw.id_guru_mapel = gm.id_guru_mapel");
if ($locked_res) {
    while ($row = mysqli_fetch_assoc($locked_res)) {
        $jadwal_guru_terisi[$row['id_waktu']][$row['id_guru']] = true;
        $jadwal_kelas_terisi[$row['id_waktu']][$row['id_kelas']] = true;
    }
}

$penugasan_izin_kelas = [];
$izin_res = mysqli_query($koneksi, "SELECT id_guru_mapel, id_kelas FROM tbl_penugasan_kelas");
if ($izin_res) while ($row = mysqli_fetch_assoc($izin_res)) $penugasan_izin_kelas[$row['id_guru_mapel']][] = $row['id_kelas'];

$blok_dibutuhkan = [];
$total_jam_per_guru = [];
foreach ($all_guru_mapel as $gm) {
    if (!isset($total_jam_per_guru[$gm['id_guru']])) $total_jam_per_guru[$gm['id_guru']] = 0;
    $total_jam_per_guru[$gm['id_guru']] += $gm['jam_per_minggu'];
}

foreach ($all_guru_mapel as $gm) {
    $id_gm = $gm['id_guru_mapel'];
    $kelas_diampu = isset($penugasan_izin_kelas[$id_gm]) && !empty($penugasan_izin_kelas[$id_gm]) ? $penugasan_izin_kelas[$id_gm] : $all_kelas_ids;
    if (empty($kelas_diampu)) continue;

    $jumlah_kelas = count($kelas_diampu);
    $jam_per_kelas_dasar = floor($gm['jam_per_minggu'] / $jumlah_kelas);
    $sisa_jam = $gm['jam_per_minggu'] % $jumlah_kelas;
    
    foreach ($kelas_diampu as $id_kelas) {
        $jam_untuk_kelas_ini = $jam_per_kelas_dasar + ($sisa_jam > 0 ? 1 : 0);
        if ($sisa_jam > 0) $sisa_jam--;

        $wajib_res = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tbl_jadwal_wajib WHERE id_guru_mapel = $id_gm AND id_kelas = $id_kelas");
        $jam_terkunci_di_kelas = mysqli_fetch_assoc($wajib_res)['total'];
        
        $sisa_jam_untuk_dijadwalkan = $jam_untuk_kelas_ini - $jam_terkunci_di_kelas;
        $blok_untuk_kelas_ini = pecah_jam_ke_blok($sisa_jam_untuk_dijadwalkan);
        
        foreach ($blok_untuk_kelas_ini as $ukuran_blok) {
            $skor_kesulitan = ($total_jam_per_guru[$gm['id_guru']] * 10) + $ukuran_blok;
            $blok_dibutuhkan[] = [
                'id_guru_mapel' => $id_gm, 'id_guru' => $gm['id_guru'], 'id_kelas' => $id_kelas, 
                'ukuran_blok' => $ukuran_blok, 'key' => $id_gm . '-' . $id_kelas,
                'skor_kesulitan' => $skor_kesulitan
            ];
        }
    }
}

usort($blok_dibutuhkan, function($a, $b) {
    return $b['skor_kesulitan'] <=> $a['skor_kesulitan'];
});

$stmt = mysqli_prepare($koneksi, "INSERT INTO tbl_jadwal (id_waktu, id_kelas, id_guru_mapel) VALUES (?, ?, ?)");

foreach ($blok_dibutuhkan as $blok) {
    $jam_untuk_ditempatkan = $blok['ukuran_blok'];

    $berhasil = tryPlaceChunk($blok, $jam_untuk_ditempatkan, $jadwal_guru_terisi, $jadwal_kelas_terisi, $all_waktu_slots, $stmt);
    
    if ($berhasil) {
        continue;
    }

    $_SESSION['emergency_placements'][$blok['key']] = $blok;
    
    if ($jam_untuk_ditempatkan > 1) {
        $ukuran_potongan = $jam_untuk_ditempatkan - 1;
        $berhasil_parsial = tryPlaceChunk($blok, $ukuran_potongan, $jadwal_guru_terisi, $jadwal_kelas_terisi, $all_waktu_slots, $stmt);
        if ($berhasil_parsial) {
            $jam_untuk_ditempatkan -= $ukuran_potongan;
        }
    }
    
    if ($jam_untuk_ditempatkan > 0) {
        for ($i = 0; $i < $jam_untuk_ditempatkan; $i++) {
            $berhasil_satuan = tryPlaceChunk($blok, 1, $jadwal_guru_terisi, $jadwal_kelas_terisi, $all_waktu_slots, $stmt);
            if (!$berhasil_satuan) {
                $_SESSION['unplaced_blocks'][$blok['key']] = $blok;
                break;
            }
        }
    }
}

if (!empty($_SESSION['emergency_placements']) || !empty($_SESSION['unplaced_blocks'])) {
    $guru_nama_map = array_column(mysqli_fetch_all(mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM tbl_guru"), MYSQLI_ASSOC), 'nama_guru', 'id_guru');
    $mapel_nama_map = array_column(mysqli_fetch_all(mysqli_query($koneksi, "SELECT gm.id_guru_mapel, m.nama_mapel FROM tbl_guru_mapel gm JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel"), MYSQLI_ASSOC), 'nama_mapel', 'id_guru_mapel');
    $kelas_nama_map = array_column(mysqli_fetch_all(mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM tbl_kelas"), MYSQLI_ASSOC), 'nama_kelas', 'id_kelas');
    
    $pesan_darurat = [];
    $processed_keys = [];
    foreach($_SESSION['emergency_placements'] as $key => $blok) {
        if (in_array($key, $processed_keys)) continue;
        $guru = $guru_nama_map[$blok['id_guru']] ?? 'N/A';
        $mapel = $mapel_nama_map[$blok['id_guru_mapel']] ?? 'N/A';
        $kelas = $kelas_nama_map[$blok['id_kelas']] ?? 'N/A';
        $pesan_darurat[] = "Penempatan terpisah: Alokasi jam untuk <strong>{$mapel}</strong> oleh Guru <strong>{$guru}</strong> di Kelas <strong>{$kelas}</strong> terpaksa dipecah.";
        $processed_keys[] = $key;
    }
    $_SESSION['emergency_placements'] = $pesan_darurat;

    $pesan_gagal = [];
     foreach($_SESSION['unplaced_blocks'] as $blok) {
        $guru = $guru_nama_map[$blok['id_guru']] ?? 'N/A';
        $mapel = $mapel_nama_map[$blok['id_guru_mapel']] ?? 'N/A';
        $kelas = $kelas_nama_map[$blok['id_kelas']] ?? 'N/A';
        $pesan_gagal[] = "GAGAL TOTAL: Sisa jam untuk Mapel <strong>{$mapel}</strong> oleh Guru <strong>{$guru}</strong> di Kelas <strong>{$kelas}</strong> tidak dapat dijadwalkan. Jadwal terlalu penuh.";
    }
    $_SESSION['unplaced_blocks'] = $pesan_gagal;
}

header('Location: susun_jadwal.php?status=auto_success');
exit;
?>