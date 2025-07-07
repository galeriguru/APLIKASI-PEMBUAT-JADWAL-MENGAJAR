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
            $query = "INSERT INTO tbl_kelas (nama_kelas, jumlah_jam_per_hari) VALUES (?, ?) 
                      ON DUPLICATE KEY UPDATE jumlah_jam_per_hari = VALUES(jumlah_jam_per_hari)";
            $stmt = mysqli_prepare($koneksi, $query);
            for ($i = 2; $i <= count($sheetData); $i++) {
                $nama_kelas = trim($sheetData[$i]['A']);
                $jam_hari = (int)trim($sheetData[$i]['B']);
                if (!empty($nama_kelas) && $jam_hari > 0) {
                    mysqli_stmt_bind_param($stmt, 'si', $nama_kelas, $jam_hari);
                    if(mysqli_stmt_execute($stmt)) $sukses++;
                }
            }
            $notification = ['type' => 'success', 'message' => "$sukses data kelas berhasil diimpor/diperbarui."];
        } catch (Exception $e) {
            $notification = ['type' => 'danger', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_kelas = (int)$_GET['id'];
    $stmt = mysqli_prepare($koneksi, "DELETE FROM tbl_kelas WHERE id_kelas = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_kelas);
    mysqli_stmt_execute($stmt);
    header('Location: kelas.php?status=deleted'); exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_kelas'])) {
    $nama_kelas = trim($_POST['nama_kelas']);
    $jumlah_jam = (int)$_POST['jumlah_jam_per_hari'];
    if (!empty($nama_kelas) && $jumlah_jam > 0) {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO tbl_kelas (nama_kelas, jumlah_jam_per_hari) VALUES (?, ?) ON DUPLICATE KEY UPDATE jumlah_jam_per_hari = VALUES(jumlah_jam_per_hari)");
        mysqli_stmt_bind_param($stmt, 'si', $nama_kelas, $jumlah_jam);
        mysqli_stmt_execute($stmt);
        header('Location: kelas.php?status=success'); exit;
    }
}
include 'templates/header.php';
$list_kelas = mysqli_query($koneksi, "SELECT * FROM tbl_kelas ORDER BY nama_kelas ASC");
?>
<h2>Manajemen Data Kelas</h2>
<?php if (!empty($notification)): ?><div class="alert alert-<?php echo $notification['type']; ?>"><?php echo $notification['message']; ?></div><?php endif; ?>

<div class="view-selection-forms">
    <div class="form-container selection-form">
        <h4>Import dari Excel</h4>
        <form action="kelas.php" method="post" enctype="multipart/form-data">
            <div class="form-group"><label>Pilih File Excel (.xlsx, .xls)</label><input type="file" name="file_excel" required accept=".xlsx, .xls"></div>
            <button type="submit" name="import" class="btn btn-primary">Import</button>
            <a href="templates/template_kelas.xlsx" class="btn btn-secondary" download>Download Template</a>
        </form>
    </div>
    <div class="form-container selection-form">
        <h4>Tambah / Edit Kelas Manual</h4>
        <form action="kelas.php" method="POST">
            <div class="form-group"><label>Nama Kelas</label><input type="text" name="nama_kelas" required placeholder="Contoh: X IPA 1"></div>
            <div class="form-group"><label>Jumlah Jam per Hari</label><input type="number" name="jumlah_jam_per_hari" required min="1" placeholder="Contoh: 10"></div>
            <button type="submit" name="save_kelas" class="btn btn-primary">Simpan</button>
        </form>
        <small>*) Jika nama kelas sudah ada, data akan diperbarui.</small>
    </div>
</div>
<hr>

<h4>Daftar Kelas</h4>
<table>
    <thead><tr><th>Nama Kelas</th><th>Jam/Hari</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if ($list_kelas && mysqli_num_rows($list_kelas) > 0): ?>
    <?php while($row = mysqli_fetch_assoc($list_kelas)): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['nama_kelas']); ?></td>
            <td><?php echo $row['jumlah_jam_per_hari']; ?></td>
            <td><a href="kelas.php?action=delete&id=<?php echo $row['id_kelas']; ?>" class="btn btn-danger" onclick="return confirm('Yakin?')">Hapus</a></td>
        </tr>
    <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="3" style="text-align:center;">Belum ada data kelas.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php include 'templates/footer.php'; ?>