<?php
require_once 'config/database.php';

if (!isset($_GET['id_guru_mapel']) || empty($_GET['id_guru_mapel'])) {
    header('Location: guru.php');
    exit;
}

$id_guru_mapel = (int)$_GET['id_guru_mapel'];
$notification = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assigned_kelas = $_POST['kelas_ids'] ?? [];

    mysqli_begin_transaction($koneksi);
    try {
        $stmt_delete = mysqli_prepare($koneksi, "DELETE FROM tbl_penugasan_kelas WHERE id_guru_mapel = ?");
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_guru_mapel);
        mysqli_stmt_execute($stmt_delete);

        if (!empty($assigned_kelas)) {
            $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO tbl_penugasan_kelas (id_guru_mapel, id_kelas) VALUES (?, ?)");
            foreach ($assigned_kelas as $id_kelas) {
                $id_kelas_int = (int)$id_kelas;
                mysqli_stmt_bind_param($stmt_insert, 'ii', $id_guru_mapel, $id_kelas_int);
                mysqli_stmt_execute($stmt_insert);
            }
        }
        
        mysqli_commit($koneksi);
        $notification = ['type' => 'success', 'message' => 'Data berhasil diperbarui! Mengarahkan kembali...'];

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $notification = ['type' => 'danger', 'message' => 'Gagal menyimpan data: ' . $e->getMessage()];
    }
}

$assignment_res = mysqli_query($koneksi, "
    SELECT g.nama_guru, m.nama_mapel 
    FROM tbl_guru_mapel gm
    JOIN tbl_guru g ON gm.id_guru = g.id_guru
    JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel
    WHERE gm.id_guru_mapel = $id_guru_mapel
");

if (mysqli_num_rows($assignment_res) == 0) {
    header('Location: guru.php');
    exit;
}
$assignment_info = mysqli_fetch_assoc($assignment_res);
$nama_guru = $assignment_info['nama_guru'];
$nama_mapel = $assignment_info['nama_mapel'];

$all_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM tbl_kelas ORDER BY nama_kelas");
$assigned_kelas_res = mysqli_query($koneksi, "SELECT id_kelas FROM tbl_penugasan_kelas WHERE id_guru_mapel = $id_guru_mapel");
$assigned_kelas_ids = [];
while ($row = mysqli_fetch_assoc($assigned_kelas_res)) {
    $assigned_kelas_ids[] = $row['id_kelas'];
}

include 'templates/header.php';
?>

<div class="page-header">
    <h2>Kelola Kelas untuk Penugasan</h2>
    <p>
        Pilih kelas yang dapat diajar untuk penugasan:<br>
        Guru: <strong><?php echo htmlspecialchars($nama_guru); ?></strong><br>
        Mata Pelajaran: <strong><?php echo htmlspecialchars($nama_mapel); ?></strong>
    </p>
</div>

<div class="form-container" style="max-width: 700px; margin: auto;">
    <p class="blinking-warning">PENTING: Jika tidak ada kelas yang dipilih, maka penugasan ini dianggap dapat diajarkan di <strong>semua kelas</strong>.</p>
    <form action="penugasan_kelas.php?id_guru_mapel=<?php echo $id_guru_mapel; ?>" method="POST">
        <div class="checkbox-grid">
            <?php if ($all_kelas && mysqli_num_rows($all_kelas) > 0): ?>
                <?php while ($kelas = mysqli_fetch_assoc($all_kelas)): ?>
                    <div class="checkbox-item">
                        <input type="checkbox" name="kelas_ids[]" value="<?php echo $kelas['id_kelas']; ?>" id="kelas_<?php echo $kelas['id_kelas']; ?>"
                            <?php if (in_array($kelas['id_kelas'], $assigned_kelas_ids)) echo 'checked'; ?>>
                        <label for="kelas_<?php echo $kelas['id_kelas']; ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></label>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Belum ada data kelas yang ditambahkan. Silakan tambahkan data kelas terlebih dahulu.</p>
            <?php endif; ?>
        </div>
        <div class="form-actions" style="text-align: right; margin-top: 20px;">
            <a href="guru.php" class="btn btn-secondary">Kembali ke Daftar Guru</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php if (!empty($notification)): ?>
<div id="toast-notification" class="toast-notification <?php echo $notification['type']; ?>">
    <?php echo $notification['message']; ?>
</div>

<script>
    <?php if ($notification['type'] === 'success'): ?>
        setTimeout(function() {
            window.location.href = 'guru.php';
        }, 2500);
    <?php endif; ?>
</script>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>