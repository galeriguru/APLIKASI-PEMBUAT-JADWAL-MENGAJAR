<?php
require_once 'config/database.php';
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$notification = [];
if (isset($_POST['import'])) {
    if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == 0) {
        $filePath = $_FILES['file_excel']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $sukses = 0;
            
            mysqli_begin_transaction($koneksi);

            $stmt_guru = mysqli_prepare($koneksi, "INSERT INTO tbl_guru (nama_guru) VALUES (?) ON DUPLICATE KEY UPDATE id_guru=LAST_INSERT_ID(id_guru)");
            $stmt_mapel = mysqli_prepare($koneksi, "INSERT INTO tbl_mata_pelajaran (nama_mapel) VALUES (?) ON DUPLICATE KEY UPDATE id_mapel=LAST_INSERT_ID(id_mapel)");
            $stmt_assign = mysqli_prepare($koneksi, "INSERT INTO tbl_guru_mapel (id_guru, id_mapel, jam_per_minggu) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE jam_per_minggu = VALUES(jam_per_minggu)");

            for ($i = 2; $i <= count($sheetData); $i++) {
                $nama_guru = trim($sheetData[$i]['A']);
                $nama_mapel = trim($sheetData[$i]['B']);
                $jam = (int)trim($sheetData[$i]['C']);

                if (empty($nama_guru) || empty($nama_mapel) || $jam <= 0) continue;

                mysqli_stmt_bind_param($stmt_guru, 's', $nama_guru);
                mysqli_stmt_execute($stmt_guru);
                $id_guru = mysqli_insert_id($koneksi);
                if ($id_guru == 0) {
                     $res = mysqli_query($koneksi, "SELECT id_guru FROM tbl_guru WHERE nama_guru = '".mysqli_real_escape_string($koneksi, $nama_guru)."'");
                     if($res_row = mysqli_fetch_assoc($res)) $id_guru = $res_row['id_guru'];
                }

                mysqli_stmt_bind_param($stmt_mapel, 's', $nama_mapel);
                mysqli_stmt_execute($stmt_mapel);
                $id_mapel = mysqli_insert_id($koneksi);
                if ($id_mapel == 0) {
                     $res = mysqli_query($koneksi, "SELECT id_mapel FROM tbl_mata_pelajaran WHERE nama_mapel = '".mysqli_real_escape_string($koneksi, $nama_mapel)."'");
                     if($res_row = mysqli_fetch_assoc($res)) $id_mapel = $res_row['id_mapel'];
                }

                if ($id_guru > 0 && $id_mapel > 0) {
                    mysqli_stmt_bind_param($stmt_assign, 'iii', $id_guru, $id_mapel, $jam);
                    if(mysqli_stmt_execute($stmt_assign)) $sukses++;
                }
            }
            
            mysqli_commit($koneksi);
            $notification = ['type' => 'success', 'message' => "$sukses data penugasan berhasil diimpor/diperbarui."];

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $notification = ['type' => 'danger', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_guru = (int)$_GET['id'];
    $stmt = mysqli_prepare($koneksi, "DELETE FROM tbl_guru WHERE id_guru = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_guru);
    mysqli_stmt_execute($stmt);
    header('Location: guru.php?status=deleted'); exit;
}
include 'templates/header.php';

$guru_list = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM tbl_guru ORDER BY nama_guru");
?>
<h2>Manajemen Data Guru & Penugasan</h2>
<?php if (!empty($notification)): ?><div class="alert alert-<?php echo $notification['type']; ?>"><?php echo $notification['message']; ?></div><?php endif; ?>

<div class="import-form-container" style="max-width:600px; margin: 0 auto 2rem auto;">
    <h4>Import Data Guru & Mata Pelajaran dari Excel</h4>
    <form action="guru.php" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <input type="file" name="file_excel" required accept=".xlsx, .xls">
        </div>
        <button type="submit" name="import" class="btn btn-primary">Import Data</button>
        <a href="templates/template_guru.xlsx" class="btn btn-secondary" download>Download Template</a>
    </form>
</div>
<hr>

<h4>Daftar Guru dan Penugasannya</h4>
<table>
    <thead>
        <tr>
            <th>Nama Guru</th>
            <th>Penugasan (Mapel & Jam/Minggu)</th>
            <th>Kelas yang Diampu untuk Penugasan ini</th>
            <th style="width: 15%;">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if($guru_list && mysqli_num_rows($guru_list) > 0): ?>
    <?php while($g = mysqli_fetch_assoc($guru_list)): ?>
        <?php
            $assignments_query = mysqli_query($koneksi, "
                SELECT gm.id_guru_mapel, m.nama_mapel, gm.jam_per_minggu
                FROM tbl_guru_mapel gm
                JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel
                WHERE gm.id_guru = {$g['id_guru']}
            ");
            $assignment_count = mysqli_num_rows($assignments_query);
        ?>
        <tr>
            <td rowspan="<?php echo $assignment_count > 0 ? $assignment_count : 1; ?>">
                <strong><?php echo htmlspecialchars($g['nama_guru']); ?></strong>
            </td>
            <?php if($assignment_count > 0): ?>
                <?php $first = true; while($a = mysqli_fetch_assoc($assignments_query)): ?>
                    <?php if (!$first) echo '<tr>'; ?>
                    <td><?php echo htmlspecialchars($a['nama_mapel']) . " (" . $a['jam_per_minggu'] . " jam)"; ?></td>
                    <td>
                        <?php
                            $kelas_ampu_query = mysqli_query($koneksi, "
                                SELECT GROUP_CONCAT(k.nama_kelas SEPARATOR ', ') as daftar_kelas 
                                FROM tbl_penugasan_kelas pk
                                JOIN tbl_kelas k ON pk.id_kelas = k.id_kelas 
                                WHERE pk.id_guru_mapel = {$a['id_guru_mapel']}
                            ");
                            $kelas_ampu = mysqli_fetch_assoc($kelas_ampu_query)['daftar_kelas'];
                            echo !empty($kelas_ampu) ? htmlspecialchars($kelas_ampu) : '<span class="empty-slot">Semua Kelas</span>';
                        ?>
                        <a href="penugasan_kelas.php?id_guru_mapel=<?php echo $a['id_guru_mapel']; ?>" style="font-size: 0.8em; display: block; margin-top: 5px;">[ Kelola Kelas ]</a>
                    </td>
                    <?php if ($first): ?>
                    <td rowspan="<?php echo $assignment_count; ?>">
                        <a href="guru.php?action=delete&id=<?php echo $g['id_guru']; ?>" class="btn btn-danger" onclick="return confirm('Menghapus guru akan menghapus semua jadwal dan penugasan terkait. Yakin?')">Hapus Guru</a>
                    </td>
                    <?php $first = false; endif; ?>
                    <?php echo '</tr>'; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <td colspan="3" style="text-align:center;">Belum ada mapel ditugaskan</td>
                 <td><a href="guru.php?action=delete&id=<?php echo $g['id_guru']; ?>" class="btn btn-danger" onclick="return confirm('Menghapus guru akan menghapus semua jadwal dan penugasan terkait. Yakin?')">Hapus Guru</a></td>
            <?php endif; ?>
    <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="4" style="text-align:center;">Belum ada data guru. Silakan import dari Excel.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php include 'templates/footer.php'; ?>