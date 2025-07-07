<?php
require_once 'config/database.php';
include 'templates/header.php';

// ACTION: Hapus Jadwal Wajib
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_wajib = (int)$_GET['id'];

    $stmt_info = mysqli_prepare($koneksi, "SELECT id_waktu, id_kelas FROM tbl_jadwal_wajib WHERE id_wajib = ?");
    mysqli_stmt_bind_param($stmt_info, 'i', $id_wajib);
    mysqli_stmt_execute($stmt_info);
    mysqli_stmt_bind_result($stmt_info, $id_waktu, $id_kelas);
    $info_found = mysqli_stmt_fetch($stmt_info);
    mysqli_stmt_close($stmt_info);

    if ($info_found) {
        mysqli_begin_transaction($koneksi);
        try {
            $stmt_del_jadwal = mysqli_prepare($koneksi, "DELETE FROM tbl_jadwal WHERE id_waktu = ? AND id_kelas = ?");
            mysqli_stmt_bind_param($stmt_del_jadwal, 'ii', $id_waktu, $id_kelas);
            mysqli_stmt_execute($stmt_del_jadwal);
            mysqli_stmt_close($stmt_del_jadwal);

            $stmt_del_wajib = mysqli_prepare($koneksi, "DELETE FROM tbl_jadwal_wajib WHERE id_wajib = ?");
            mysqli_stmt_bind_param($stmt_del_wajib, 'i', $id_wajib);
            mysqli_stmt_execute($stmt_del_wajib);
            mysqli_stmt_close($stmt_del_wajib);

            mysqli_commit($koneksi);
            header('Location: jadwal_wajib.php?status=deleted');
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            die("Gagal menghapus jadwal: " . $e->getMessage() . " <a href='jadwal_wajib.php'>Kembali</a>");
        }
    } else {
        header('Location: jadwal_wajib.php?status=notfound');
    }
    exit;
}

// ACTION: Simpan Jadwal Wajib Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_wajib'])) {
    $id_guru_mapel = (int)$_POST['id_guru_mapel'];
    $id_kelas = (int)$_POST['id_kelas'];
    $id_waktu = (int)$_POST['id_waktu'];

    if($id_guru_mapel > 0 && $id_kelas > 0 && $id_waktu > 0) {

        // Validasi 1: Cek penugasan guru di kelas
        $stmt_cek_penugasan = mysqli_prepare($koneksi, "SELECT COUNT(*) FROM tbl_penugasan_kelas WHERE id_guru_mapel = ? AND id_kelas = ?");
        mysqli_stmt_bind_param($stmt_cek_penugasan, 'ii', $id_guru_mapel, $id_kelas);
        mysqli_stmt_execute($stmt_cek_penugasan);
        mysqli_stmt_bind_result($stmt_cek_penugasan, $jumlah_penugasan);
        mysqli_stmt_fetch($stmt_cek_penugasan);
        mysqli_stmt_close($stmt_cek_penugasan);

        if ($jumlah_penugasan == 0) {
            die("Error: Guru ini tidak memiliki penugasan untuk mengajar di kelas tersebut. <a href='jadwal_wajib.php'>Kembali</a>");
        }

        // Validasi 2: Cek alokasi jam mengajar
        $stmt_alokasi = mysqli_prepare($koneksi, "SELECT jam_per_minggu FROM tbl_guru_mapel WHERE id_guru_mapel = ?");
        mysqli_stmt_bind_param($stmt_alokasi, 'i', $id_guru_mapel);
        mysqli_stmt_execute($stmt_alokasi);
        mysqli_stmt_bind_result($stmt_alokasi, $alokasi_jam);
        mysqli_stmt_fetch($stmt_alokasi);
        mysqli_stmt_close($stmt_alokasi);
        
        $stmt_terjadwal = mysqli_prepare($koneksi, "SELECT COUNT(*) FROM tbl_jadwal WHERE id_guru_mapel = ?");
        mysqli_stmt_bind_param($stmt_terjadwal, 'i', $id_guru_mapel);
        mysqli_stmt_execute($stmt_terjadwal);
        mysqli_stmt_bind_result($stmt_terjadwal, $jam_terjadwal);
        mysqli_stmt_fetch($stmt_terjadwal);
        mysqli_stmt_close($stmt_terjadwal);

        if (($jam_terjadwal + 1) > $alokasi_jam) {
            die("Error: Jam mengajar akan melebihi alokasi yang ditentukan ({$alokasi_jam} jam/minggu). Saat ini sudah terjadwal {$jam_terjadwal} jam. <a href='jadwal_wajib.php'>Kembali</a>");
        }

        // Proses Insert jika semua validasi lolos
        mysqli_begin_transaction($koneksi);
        try {
            $stmt1 = mysqli_prepare($koneksi, "INSERT INTO tbl_jadwal (id_waktu, id_kelas, id_guru_mapel) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id_guru_mapel = VALUES(id_guru_mapel)");
            mysqli_stmt_bind_param($stmt1, 'iii', $id_waktu, $id_kelas, $id_guru_mapel);
            mysqli_stmt_execute($stmt1);
            mysqli_stmt_close($stmt1);

            $stmt2 = mysqli_prepare($koneksi, "INSERT INTO tbl_jadwal_wajib (id_waktu, id_kelas, id_guru_mapel) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, 'iii', $id_waktu, $id_kelas, $id_guru_mapel);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            mysqli_commit($koneksi);
            header('Location: jadwal_wajib.php?status=success');
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            die("Error: Terjadi bentrok jadwal atau slot sudah dikunci. Pesan: " . $e->getMessage() . " <a href='jadwal_wajib.php'>Kembali</a>");
        }
        exit;
    }
}

// Ambil data untuk form dan tabel
$assignments_list = mysqli_query($koneksi, "SELECT gm.id_guru_mapel, g.nama_guru, m.nama_mapel FROM tbl_guru_mapel gm JOIN tbl_guru g ON gm.id_guru = g.id_guru JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel ORDER BY g.nama_guru, m.nama_mapel");
$list_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM tbl_kelas ORDER BY nama_kelas");
$list_waktu = mysqli_query($koneksi, "SELECT id_waktu, hari, jam_ke, range_waktu FROM tbl_waktu_pelajaran ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jam_ke");

$daftar_wajib_query = "SELECT jw.id_wajib, g.nama_guru, m.nama_mapel, k.nama_kelas, w.hari, w.jam_ke, w.range_waktu FROM tbl_jadwal_wajib jw JOIN tbl_guru_mapel gm ON jw.id_guru_mapel = gm.id_guru_mapel JOIN tbl_guru g ON gm.id_guru = g.id_guru JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel JOIN tbl_kelas k ON jw.id_kelas = k.id_kelas JOIN tbl_waktu_pelajaran w ON jw.id_waktu = w.id_waktu ORDER BY w.id_waktu, k.nama_kelas";
$daftar_wajib = mysqli_query($koneksi, $daftar_wajib_query);
?>

<h2>Manajemen Jadwal Wajib</h2>
<p class="blinking-warning">PENTING: Fitur ini digunakan hanya untuk guru dengan permintaan khusus, misal hanya bisa mengajar di hari Senin jam pertama.</p>

<div class="form-container" style="max-width: 600px; margin: auto; margin-bottom: 2rem;">
    <h4>Tambah Jadwal Wajib Baru</h4>
    <form action="jadwal_wajib.php" method="POST">
        <div class="form-group">
            <label>Guru & Mata Pelajaran</label>
            <select name="id_guru_mapel" required>
                <option value="">-- Pilih --</option>
                <?php while($a = mysqli_fetch_assoc($assignments_list)) echo "<option value='{$a['id_guru_mapel']}'>".htmlspecialchars($a['nama_guru'])." (".htmlspecialchars($a['nama_mapel']).")</option>"; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Kelas</label>
            <select name="id_kelas" required>
                <option value="">-- Pilih --</option>
                <?php mysqli_data_seek($list_kelas, 0); while($k = mysqli_fetch_assoc($list_kelas)) echo "<option value='{$k['id_kelas']}'>".htmlspecialchars($k['nama_kelas'])."</option>"; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Waktu</label>
            <select name="id_waktu" required>
                <option value="">-- Pilih --</option>
                <?php while($w = mysqli_fetch_assoc($list_waktu)) echo "<option value='{$w['id_waktu']}'>".htmlspecialchars($w['hari']).", Jam ke-".$w['jam_ke']." (".htmlspecialchars($w['range_waktu']).")</option>"; ?>
            </select>
        </div>
        <button type="submit" name="save_wajib" class="btn btn-primary">Kunci Jadwal Ini</button>
    </form>
</div>

<h4>Daftar Jadwal yang Dikunci</h4>
<table>
    <thead>
        <tr>
            <th>Hari & Waktu</th>
            <th>Kelas</th>
            <th>Guru & Mapel</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if($daftar_wajib && mysqli_num_rows($daftar_wajib) > 0): ?>
        <?php while($dw = mysqli_fetch_assoc($daftar_wajib)): ?>
            <tr>
                <td><?php echo htmlspecialchars($dw['hari'] . ", Jam " . $dw['jam_ke']); ?></td>
                <td><?php echo htmlspecialchars($dw['nama_kelas']); ?></td>
                <td><?php echo htmlspecialchars($dw['nama_guru'] . " (" . $dw['nama_mapel'] . ")"); ?></td>
                <td>
                    <a href="jadwal_wajib.php?action=delete&id=<?php echo $dw['id_wajib']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin membuka kunci jadwal ini?')">Buka Kunci</a>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="4" style="text-align:center;">Belum ada jadwal yang dikunci.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php include 'templates/footer.php'; ?>