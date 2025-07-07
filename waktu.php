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
            $query = "INSERT INTO tbl_waktu_pelajaran (hari, jam_ke, range_waktu) VALUES (?, ?, ?) 
                      ON DUPLICATE KEY UPDATE range_waktu = VALUES(range_waktu)";
            $stmt = mysqli_prepare($koneksi, $query);
            for ($i = 2; $i <= count($sheetData); $i++) {
                $hari = trim($sheetData[$i]['A']);
                $jam_ke = (int)trim($sheetData[$i]['B']);
                $range = trim($sheetData[$i]['C']);
                if (!empty($hari) && $jam_ke > 0 && !empty($range)) {
                    mysqli_stmt_bind_param($stmt, 'sis', $hari, $jam_ke, $range);
                    if(mysqli_stmt_execute($stmt)) $sukses++;
                }
            }
            $notification = ['type' => 'success', 'message' => "$sukses data waktu berhasil diimpor/diperbarui."];
        } catch (Exception $e) {
            $notification = ['type' => 'danger', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_waktu = (int)$_GET['id'];
    $stmt = mysqli_prepare($koneksi, "DELETE FROM tbl_waktu_pelajaran WHERE id_waktu = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_waktu);
    mysqli_stmt_execute($stmt);
    header('Location: waktu.php?status=deleted'); exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_waktu'])) {
    $hari = trim($_POST['hari']);
    $jam_ke = (int)$_POST['jam_ke'];
    $range_waktu = trim($_POST['range_waktu']);
    if (!empty($hari) && $jam_ke > 0 && !empty($range_waktu)) {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO tbl_waktu_pelajaran (hari, jam_ke, range_waktu) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE range_waktu = VALUES(range_waktu)");
        mysqli_stmt_bind_param($stmt, 'sis', $hari, $jam_ke, $range_waktu);
        mysqli_stmt_execute($stmt);
        header('Location: waktu.php?status=success'); exit;
    }
}
include 'templates/header.php';
$list_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$order_hari = "FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')";
$list_waktu = mysqli_query($koneksi, "SELECT * FROM tbl_waktu_pelajaran ORDER BY $order_hari, jam_ke ASC");
?>
<h2>Manajemen Data Hari & Jam Pelajaran</h2>
<?php if (!empty($notification)): ?><div class="alert alert-<?php echo $notification['type']; ?>"><?php echo $notification['message']; ?></div><?php endif; ?>
<div class="view-selection-forms">
    <div class="form-container selection-form">
        <h4>Tambah / Edit Slot Waktu Manual</h4>
        <form action="waktu.php" method="POST">
            <div class="form-group">
                <label>Hari</label>
                <select name="hari" required>
                    <?php foreach($list_hari as $h) echo "<option value='$h'>$h</option>"; ?>
                </select>
            </div>
            <div class="form-group"><label>Jam Ke-</label><input type="number" name="jam_ke" required min="1"></div>
            <div class="form-group"><label>Rentang Waktu</label><input type="text" name="range_waktu" required placeholder="Contoh: 07:00 - 07:45"></div>
            <button type="submit" name="save_waktu" class="btn btn-primary">Simpan</button>
        </form>
        <small>*) Jika hari dan jam ke- sudah ada, data akan diperbarui.</small>
    </div>
    <div class="form-container selection-form">
        <h4>Import dari Excel</h4>
        <form action="waktu.php" method="post" enctype="multipart/form-data">
            <div class="form-group"><label>Pilih File Excel (.xlsx, .xls)</label><input type="file" name="file_excel" required accept=".xlsx, .xls"></div>
            <button type="submit" name="import" class="btn btn-primary">Import</button>
            <a href="templates/template_waktu.xlsx" class="btn btn-secondary" download>Download Template</a>
        </form>
    </div>
</div>
<hr>
<h4>Daftar Slot Waktu</h4>
<table>
    <thead><tr><th>Hari</th><th>Jam Ke-</th><th>Rentang Waktu</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if($list_waktu && mysqli_num_rows($list_waktu) > 0): ?>
    <?php while($row = mysqli_fetch_assoc($list_waktu)): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($row['hari']); ?></strong></td>
            <td><?php echo $row['jam_ke']; ?></td>
            <td><?php echo htmlspecialchars($row['range_waktu']); ?></td>
            <td><a href="waktu.php?action=delete&id=<?php echo $row['id_waktu']; ?>" class="btn btn-danger" onclick="return confirm('Yakin?')">Hapus</a></td>
        </tr>
    <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="4" style="text-align:center;">Belum ada data waktu.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php include 'templates/footer.php'; ?>