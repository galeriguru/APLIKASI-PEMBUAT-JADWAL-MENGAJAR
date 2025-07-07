<?php
require_once 'config/database.php';
include 'templates/header.php';

$list_kelas_result = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM tbl_kelas ORDER BY nama_kelas ASC");
$list_guru_result = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM tbl_guru ORDER BY nama_guru ASC");
$waktu_list_query = "SELECT id_waktu, hari, jam_ke, range_waktu FROM tbl_waktu_pelajaran ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jam_ke ASC";
$waktu_list = mysqli_query($koneksi, $waktu_list_query);

$view_mode = '';
$id_terpilih = 0;
$nama_header = '';
$jadwal_data = [];

if (isset($_GET['kelas_id']) && !empty($_GET['kelas_id'])) {
    $view_mode = 'kelas';
    $id_terpilih = (int)$_GET['kelas_id'];
    $res = mysqli_query($koneksi, "SELECT nama_kelas FROM tbl_kelas WHERE id_kelas = $id_terpilih");
    $nama_header = "Jadwal Kelas: " . htmlspecialchars(mysqli_fetch_assoc($res)['nama_kelas']);
    $res_jadwal = mysqli_query($koneksi, "SELECT j.id_waktu, g.nama_guru, m.nama_mapel FROM tbl_jadwal j JOIN tbl_guru_mapel gm ON j.id_guru_mapel = gm.id_guru_mapel JOIN tbl_guru g ON gm.id_guru = g.id_guru JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel WHERE j.id_kelas = $id_terpilih");
} elseif (isset($_GET['guru_id']) && !empty($_GET['guru_id'])) {
    $view_mode = 'guru';
    $id_terpilih = (int)$_GET['guru_id'];
    $res = mysqli_query($koneksi, "SELECT nama_guru FROM tbl_guru WHERE id_guru = $id_terpilih");
    $nama_header = "Jadwal Guru: " . htmlspecialchars(mysqli_fetch_assoc($res)['nama_guru']);
    $res_jadwal = mysqli_query($koneksi, "SELECT j.id_waktu, k.nama_kelas, m.nama_mapel FROM tbl_jadwal j JOIN tbl_guru_mapel gm ON j.id_guru_mapel = gm.id_guru_mapel JOIN tbl_kelas k ON j.id_kelas = k.id_kelas JOIN tbl_mata_pelajaran m ON gm.id_mapel = m.id_mapel WHERE gm.id_guru = $id_terpilih");
}

if (!empty($view_mode) && $res_jadwal) {
    while($row = mysqli_fetch_assoc($res_jadwal)) {
        $jadwal_data[$row['id_waktu']] = $row;
    }
}
?>

<style>
.selection-box {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    cursor: pointer;
    transition: background 0.2s ease;
}
.selection-box i {
    font-size: 28px;
    color: #e74c3c;
}
.selection-box:hover {
    background: #f9f9f9;
}
.selection-box form {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    width: 100%;
}
.selection-box select {
    padding: 6px;
}
</style>

<div class="page-header">
    <h2>Lihat & Cetak Jadwal</h2>
    <p>Pilih salah satu opsi di bawah untuk melihat atau mencetak jadwal.</p>
</div>

<div class="view-selection-panel">
    <div class="selection-box">
    <i class="fa-solid fa-file-pdf"></i>
    <form style="display: flex; align-items: center; gap: 10px; width: 100%;" onsubmit="window.location.href='tampil_jadwal.php'; return false;">
        <button type="submit" style="all: unset; cursor: pointer; text-align: left;">
            <h4>Cetak Massal</h4>
            <p>Membuat satu dokumen PDF berisi jadwal untuk semua kelas.</p>
        </button>
    </form>
</div>


    <div class="selection-box">
        <i class="fa-solid fa-school"></i>
        <form action="lihat_jadwal.php" method="GET">
            <select name="kelas_id" onchange="this.form.submit()" required>
                <option value="">-- Pilih Kelas --</option>
                <?php mysqli_data_seek($list_kelas_result, 0); while($kelas = mysqli_fetch_assoc($list_kelas_result)): ?>
                    <option value="<?php echo $kelas['id_kelas']; ?>" <?php if($view_mode == 'kelas' && $id_terpilih == $kelas['id_kelas']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <div>
                <h4>Cetak Jadwal Kelas</h4>
                <p>Mencetak Jadwal untuk Kelas Tertentu</p>
            </div>
        </form>
    </div>

    <div class="selection-box">
        <i class="fa-solid fa-chalkboard-user"></i>
        <form action="lihat_jadwal.php" method="GET">
            <select name="guru_id" onchange="this.form.submit()" required>
                <option value="">-- Pilih Guru --</option>
                <?php mysqli_data_seek($list_guru_result, 0); while($guru = mysqli_fetch_assoc($list_guru_result)): ?>
                    <option value="<?php echo $guru['id_guru']; ?>" <?php if($view_mode == 'guru' && $id_terpilih == $guru['id_guru']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($guru['nama_guru']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <div>
                <h4>Cetak Jadwal Guru</h4>
                <p>Mencetak Jadwal untuk Individu Guru</p>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($view_mode) && !empty($jadwal_data)): ?>
<div class="schedule-view-container">
    <div class="schedule-header">
        <h3><?php echo $nama_header; ?></h3>
        <a href="cetak_jadwal.php?<?php echo $view_mode; ?>_id=<?php echo $id_terpilih; ?>" target="_blank" class="btn btn-primary"><i class="fa-solid fa-print"></i> Cetak / PDF</a>
    </div>

    <div class="schedule-list-view">
        <?php
        $current_day = '';
        mysqli_data_seek($waktu_list, 0);
        while($waktu_row = mysqli_fetch_assoc($waktu_list)):
            if ($current_day != $waktu_row['hari']) {
                if ($current_day != '') echo '</div>';
                $current_day = $waktu_row['hari'];
                echo '<h4>'.$current_day.'</h4><div class="day-schedule-list">';
            }
            $slot_data = $jadwal_data[$waktu_row['id_waktu']] ?? null;
        ?>
            <div class="schedule-slot <?php echo $slot_data ? 'filled' : 'empty'; ?>">
                <div class="slot-time">
                    <i class="fa-regular fa-clock"></i>
                    <span>Jam ke-<?php echo $waktu_row['jam_ke']; ?> (<?php echo $waktu_row['range_waktu']; ?>)</span>
                </div>
                <div class="slot-info">
                    <?php if($slot_data): ?>
                        <?php if($view_mode == 'kelas'): ?>
                            <strong><?php echo htmlspecialchars($slot_data['nama_guru']); ?></strong>
                            <small><?php echo htmlspecialchars($slot_data['nama_mapel']); ?></small>
                        <?php else: ?>
                            <strong><?php echo htmlspecialchars($slot_data['nama_kelas']); ?></strong>
                            <small><?php echo htmlspecialchars($slot_data['nama_mapel']); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="empty-slot-text">-- Istirahat / Jam Kosong --</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; echo '</div>'; ?>
    </div>
</div>
<?php elseif (!empty($view_mode) && empty($jadwal_data)): ?>
    <div class="alert alert-info" style="text-align: center; padding: 2rem;">
        <i class="fa-solid fa-circle-info" style="font-size: 2rem; margin-bottom: 1rem;"></i><br>
        Jadwal belum tersedia untuk pilihan ini. Silakan susun jadwal terlebih dahulu.
    </div>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>
