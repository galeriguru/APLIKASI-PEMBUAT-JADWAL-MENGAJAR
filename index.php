<?php
require_once 'config/database.php';
include 'templates/header.php';

$total_guru = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(id_guru) AS total FROM tbl_guru"))['total'] ?? 0;
$total_kelas = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(id_kelas) AS total FROM tbl_kelas"))['total'] ?? 0;
$total_mapel = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(id_mapel) AS total FROM tbl_mata_pelajaran"))['total'] ?? 0;
$total_waktu = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(id_waktu) AS total FROM tbl_waktu_pelajaran"))['total'] ?? 0;
$total_jadwal_terisi = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(id_jadwal) AS total FROM tbl_jadwal"))['total'] ?? 0;
$total_jadwal_terkunci = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(id_wajib) AS total FROM tbl_jadwal_wajib"))['total'] ?? 0;

$total_slot_tersedia = $total_kelas * $total_waktu;
?>

<div class="page-header">
    <h2>Dashboard</h2>
    <p>Selamat Datang! Berikut adalah ringkasan sistem penjadwalan Anda.</p>
</div>

<div class="dashboard-stats">
    <div class="stat-card">
        <div class="card-icon icon-guru"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div class="card-content">
            <p>Total Guru</p>
            <span><?php echo $total_guru; ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="card-icon icon-kelas"><i class="fa-solid fa-school"></i></div>
        <div class="card-content">
            <p>Total Kelas</p>
            <span><?php echo $total_kelas; ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="card-icon icon-mapel"><i class="fa-solid fa-book-open-reader"></i></div>
        <div class="card-content">
            <p>Total Mapel</p>
            <span><?php echo $total_mapel; ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="card-icon icon-slot"><i class="fa-solid fa-table-cells"></i></div>
        <div class="card-content">
            <p>Total Slot Tersedia</p>
            <span><?php echo $total_slot_tersedia; ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="card-icon icon-terisi"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="card-content">
            <p>Slot Terisi</p>
            <span><?php echo $total_jadwal_terisi; ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="card-icon icon-kunci"><i class="fa-solid fa-lock"></i></div>
        <div class="card-content">
            <p>Jadwal Terkunci</p>
            <span><?php echo $total_jadwal_terkunci; ?></span>
        </div>
    </div>
</div>

<div class="info-panel">
    <div class="info-header">
        <i class="fa-solid fa-rocket"></i>
        <h3>Panduan Memulai Cepat</h3>
    </div>
    <div class="info-content">
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-icon">1</div>
                <div class="timeline-content">
                    <h4>Input Data Master</h4>
                    <p>Mulai dengan mengisi <a href="guru.php">Data Guru</a>, <a href="kelas.php">Kelas</a>, dan <a href="waktu.php">Waktu</a>. Gunakan fitur import Excel untuk mempercepat proses.</p>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon">2</div>
                <div class="timeline-content">
                    <h4>Kunci Jadwal Penting</h4>
                    <p>Tetapkan jadwal yang tidak boleh berubah (misal: ada guru yang meminta dihari dan jam tertentu atau jadwal upacara) <a href="jadwal_wajib.php">Kunci Jadwal</a>.</p>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon">3</div>
                <div class="timeline-content">
                    <h4>Susun Jadwal</h4>
                    <p>Buka halaman <a href="susun_jadwal.php">Susun Jadwal</a>. Isi slot secara manual atau biarkan sistem mengisinya secara otomatis dengan satu kali klik.</p>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-icon">4</div>
                <div class="timeline-content">
                    <h4>Lihat & Cetak Hasil</h4>
                    <p>Setelah jadwal selesai, lihat hasilnya per kelas atau per guru di menu <a href="lihat_jadwal.php">Lihat & Cetak Jadwal</a> untuk didistribusikan.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'templates/footer.php';
?>