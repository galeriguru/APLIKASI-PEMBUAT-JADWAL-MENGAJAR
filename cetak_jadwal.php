<?php
require_once 'config/database.php';

$orientasi = isset($_GET['orientasi']) && $_GET['orientasi'] == 'landscape' ? 'landscape' : 'portrait';

$view_mode = '';
$nama_header = "Jadwal Pelajaran";
$single_view_data = [];
$grid_view_data = [
    'kelas_header' => [],
    'waktu_rows' => [],
    'jadwal_grid' => [],
    'legenda_guru' => []
];

if (isset($_GET['mode']) && $_GET['mode'] == 'semua_kelas') {
    $view_mode = 'grid_semua_kelas';
    $nama_header = "JADWAL MENGAJAR SEMUA KELAS";

    $kelas_res = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC");
    while ($row = mysqli_fetch_assoc($kelas_res)) { $grid_view_data['kelas_header'][] = $row; }

    $waktu_res = mysqli_query($koneksi, "SELECT hari, jam_ke, id_waktu FROM tbl_waktu_pelajaran ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jam_ke ASC");
    $waktu_per_hari = [];
    while ($row = mysqli_fetch_assoc($waktu_res)) { $waktu_per_hari[$row['hari']][] = $row; }
    $grid_view_data['waktu_rows'] = $waktu_per_hari;

    $guru_mapping = []; $guru_nomor = 1;
    $guru_list_res = mysqli_query($koneksi, "SELECT DISTINCT g.id_guru, g.nama_guru FROM tbl_jadwal j JOIN tbl_guru_mapel gm ON j.id_guru_mapel = gm.id_guru_mapel JOIN tbl_guru g ON gm.id_guru = g.id_guru ORDER BY g.nama_guru ASC");
    while ($row = mysqli_fetch_assoc($guru_list_res)) { $guru_mapping[$row['id_guru']] = ['nomor' => $guru_nomor, 'nama' => $row['nama_guru']]; $guru_nomor++; }

    $jadwal_res = mysqli_query($koneksi, "SELECT j.id_waktu, j.id_kelas, gm.id_guru FROM tbl_jadwal j JOIN tbl_guru_mapel gm ON j.id_guru_mapel = gm.id_guru_mapel");
    while ($row = mysqli_fetch_assoc($jadwal_res)) { $guru_nomor = $guru_mapping[$row['id_guru']]['nomor'] ?? '-'; $grid_view_data['jadwal_grid'][$row['id_waktu']][$row['id_kelas']] = $guru_nomor; }

    foreach ($guru_mapping as $data) { $grid_view_data['legenda_guru'][] = ['nama_guru' => $data['nama'], 'guru_nomor' => $data['nomor']]; }
    usort($grid_view_data['legenda_guru'], function($a, $b) { return $a['guru_nomor'] <=> $b['guru_nomor']; });

} else {
    function get_single_view_data($koneksi, $mode, $id) {
        $data = [];
        $query = "";
        if ($mode == 'kelas') {
            $query = "SELECT w.hari, w.jam_ke, w.range_waktu, g.nama_guru, m.nama_mapel FROM tbl_waktu_pelajaran w LEFT JOIN (SELECT * FROM tbl_jadwal WHERE id_kelas = ?) j ON w.id_waktu = j.id_waktu LEFT JOIN tbl_guru_mapel gm ON j.id_guru_mapel = gm.id_guru_mapel LEFT JOIN tbl_guru g ON gm.id_guru = g.id_guru LEFT JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel ORDER BY FIELD(w.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), w.jam_ke ASC";
        } elseif ($mode == 'guru') {
            $query = "SELECT w.hari, w.jam_ke, w.range_waktu, k.nama_kelas, m.nama_mapel FROM tbl_waktu_pelajaran w LEFT JOIN (SELECT j.id_waktu, j.id_kelas, j.id_guru_mapel FROM tbl_jadwal j JOIN tbl_guru_mapel gm_filter ON j.id_guru_mapel = gm_filter.id_guru_mapel WHERE gm_filter.id_guru = ?) j ON w.id_waktu = j.id_waktu LEFT JOIN tbl_kelas k ON j.id_kelas = k.id_kelas LEFT JOIN tbl_guru_mapel gm ON j.id_guru_mapel = gm.id_guru_mapel LEFT JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel ORDER BY FIELD(w.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), w.jam_ke ASC";
        }
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($result)) {
            $data[$row['hari']][] = $row;
        }
        return $data;
    }

    if (isset($_GET['kelas_id'])) {
        $view_mode = 'kelas';
        $id_terpilih = (int)$_GET['kelas_id'];
        $kelas_res = mysqli_query($koneksi, "SELECT nama_kelas FROM tbl_kelas WHERE id_kelas = $id_terpilih");
        $nama_header = "Jadwal Pelajaran Kelas " . htmlspecialchars(mysqli_fetch_assoc($kelas_res)['nama_kelas']);
        $single_view_data = get_single_view_data($koneksi, 'kelas', $id_terpilih);
    } elseif (isset($_GET['guru_id'])) {
        $view_mode = 'guru';
        $id_terpilih = (int)$_GET['guru_id'];
        $guru_res = mysqli_query($koneksi, "SELECT nama_guru FROM tbl_guru WHERE id_guru = $id_terpilih");
        $nama_header = "Jadwal Untuk " . htmlspecialchars(mysqli_fetch_assoc($guru_res)['nama_guru']);
        $single_view_data = get_single_view_data($koneksi, 'guru', $id_terpilih);
    } else {
        if (!isset($_GET['mode'])) {
            die("Mode tampilan tidak valid atau ID tidak diberikan.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Jadwal</title>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 9pt; background: white; }
        .grid-container { display: flex; align-items: stretch; gap: 15px; width: 100%; height: 100%; padding: 10px; box-sizing: border-box; }
        .schedule-matrix-container { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
        .schedule-matrix-table { width: 100%; border-collapse: collapse; font-size: 8pt; flex-grow: 1; }
        .schedule-matrix-table th, .schedule-matrix-table td { border: 1px solid #000; padding: 3px; text-align: center; vertical-align: middle; }
        .schedule-matrix-table .header-main { font-size: 14pt; font-weight: bold; padding: 8px; background-color: #f0f0f0; }
        .schedule-matrix-table .header-kelas { background-color: #f2f2f2; font-weight: bold; font-size: 7.5pt; }
        .schedule-matrix-table .hari-column { font-weight: bold; width: 8%; }
        .schedule-matrix-table .jam-column { font-weight: bold; width: 6%; }
        .legend-container { flex-shrink: 0; width: 180px; display: flex; flex-direction: column; }
        .legend-table { width: 100%; border-collapse: collapse; font-size: 8pt; flex-grow: 1; }
        .legend-table th, .legend-table td { border: 1px solid #000; padding: 3px; text-align: left; }
        .legend-table th { background-color: #f2f2f2; text-align: center; font-weight: bold; }
        .legend-table .kode-column { width: 25%; text-align: center; font-weight: bold; }
        .printable-area { width: 98%; margin: auto; }
        .header-cetak { text-align: center; margin-bottom: 20px; border-bottom: 3px double black; padding-bottom: 10px; }
        .header-cetak h2 { margin: 0; font-size: 16pt; }
        .header-cetak h3 { margin: 5px 0; font-size: 14pt; }
        .schedule-grid-view { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        .day-column { flex: 1; min-width: 280px; max-width: 320px; }
        .day-column h4 { background-color: #e9e9e9; padding: 8px; margin: 0; font-size: 12pt; text-align: center; border: 1px solid #000; border-bottom: none; }

        @media print {
            * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
            <?php if ($orientasi == 'landscape'): ?>
            @page { size: 330mm 215mm; margin: 10mm; }
            html, body { width: calc(330mm - 20mm); height: calc(215mm - 20mm); font-size: 8pt; }
            <?php else: ?>
            @page { size: 215mm 330mm; margin: 10mm; }
            html, body { width: calc(215mm - 20mm); height: calc(330mm - 20mm); font-size: 8pt; }
            <?php endif; ?>
            .grid-container { padding: 0; gap: 10px; }
            .schedule-matrix-table { font-size: 7pt; }
            .schedule-matrix-table th, .schedule-matrix-table td { padding: 2px; }
            .legend-table { font-size: 7pt; }
            .legend-container { width: 160px; }
            .printable-area, .day-column { page-break-inside: avoid; }
            .header-cetak { border-bottom: 2px double black; }
        }
    </style>
</head>
<body onload="window.print()">
    <!-- Konten HTML (tidak berubah) -->
    <?php if ($view_mode == 'grid_semua_kelas'): ?>
    <div class="grid-container">
        <div class="schedule-matrix-container">
            <table class="schedule-matrix-table">
                <thead>
                    <tr><th class="header-main" colspan="<?php echo count($grid_view_data['kelas_header']) + 2; ?>"><?php echo $nama_header; ?></th></tr>
                    <tr><th rowspan="2" class="hari-column header-kelas">HARI</th><th rowspan="2" class="jam-column header-kelas">JAM</th><th class="header-kelas" colspan="<?php echo count($grid_view_data['kelas_header']); ?>">KELAS</th></tr>
                    <tr><?php foreach($grid_view_data['kelas_header'] as $kelas): ?><th class="header-kelas"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach($grid_view_data['waktu_rows'] as $hari => $waktu_list): ?>
                        <?php $first_row = true; foreach($waktu_list as $waktu): ?>
                            <tr>
                                <?php if($first_row): ?><td rowspan="<?php echo count($waktu_list); ?>" class="hari-column"><strong><?php echo $hari; ?></strong></td><?php $first_row = false; ?><?php endif; ?>
                                <td class="jam-column"><?php echo $waktu['jam_ke']; ?></td>
                                <?php foreach($grid_view_data['kelas_header'] as $kelas): ?><td><?php $guru_nomor = $grid_view_data['jadwal_grid'][$waktu['id_waktu']][$kelas['id_kelas']] ?? ''; echo $guru_nomor ?: '-'; ?></td><?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="legend-container">
            <table class="legend-table">
                <thead><tr><th colspan="2">DAFTAR GURU</th></tr><tr><th class="kode-column">Kode</th><th>Nama Guru</th></tr></thead>
                <tbody><?php foreach($grid_view_data['legenda_guru'] as $guru): ?><tr><td class="kode-column"><?php echo $guru['guru_nomor']; ?></td><td><?php echo htmlspecialchars($guru['nama_guru']); ?></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="printable-area">
        <div class="header-cetak">
            <h2><?php echo ($view_mode == 'guru') ? 'JADWAL MENGAJAR' : 'JADWAL PELAJARAN'; ?></h2>
            <h3><?php echo $nama_header; ?></h3>
        </div>
        <div class="schedule-grid-view">
            <?php if (!empty($single_view_data)): foreach ($single_view_data as $hari => $slots): ?>
            <div class="day-column">
                <h4><?php echo $hari; ?></h4>
                <table style="width: 100%; border-collapse: collapse; font-size: 10pt;">
                    <thead>
                        <tr>
                            <th style="width:35%; border: 1px solid #000; padding: 5px; background-color: #f2f2f2; text-align: center;">Waktu</th>
                            <th style="border: 1px solid #000; padding: 5px; background-color: #f2f2f2; text-align: center;"><?php echo ($view_mode == 'guru') ? 'Kelas & Mata Pelajaran' : 'Guru & Mata Pelajaran'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                        <tr>
                            <td style="border: 1px solid #000; padding: 5px; text-align: center; font-weight: bold;"><?php echo htmlspecialchars($slot['range_waktu']); ?></td>
                            <td style="border: 1px solid #000; padding: 5px; text-align: center;">
                                <?php
                                $is_empty = ($view_mode == 'guru' && empty($slot['nama_kelas'])) || ($view_mode != 'guru' && empty($slot['nama_guru']));
                                if ($is_empty) { echo '<div style="color: #999; font-style: italic;">-</div>'; } 
                                else {
                                    $top_text = ($view_mode == 'guru') ? $slot['nama_kelas'] : $slot['nama_guru'];
                                    echo '<div style="font-weight: bold; margin-bottom: 3px;">' . htmlspecialchars($top_text) . '</div>';
                                    echo '<div style="font-size: 9pt; color: #666;">' . htmlspecialchars($slot['nama_mapel']) . '</div>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; else: ?>
            <p style="text-align: center; color: #666; font-style: italic;">Jadwal tidak tersedia untuk ID yang dipilih.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>