<?php
// Letakkan file ini di root aplikasi Anda
include 'templates/header.php'; 
?>

<div class="page-center-container">
    <div class="selection-container">
        <div class="header">
            <h1>Opsi Cetak Jadwal</h1>
            <p>Pilih orientasi halaman yang paling sesuai. Untuk jadwal dengan banyak kelas, kami sangat merekomendasikan orientasi Landscape.</p>
        </div>

        <div class="options-grid">
            <a href="cetak_jadwal.php?mode=semua_kelas&orientasi=portrait" target="_blank" class="option-card portrait">
                <div class="icon-container">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.5"><path d="M7 3.5h10c.828 0 1.5.672 1.5 1.5v14c0 .828-.672 1.5-1.5 1.5H7c-.828 0-1.5-.672-1.5-1.5V5c0-.828.672-1.5 1.5-1.5z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h3>Portrait</h3>
                <p>Ideal untuk jumlah kelas standar (hingga 22 kelas).</p>
            </a>
            
            <a href="cetak_jadwal.php?mode=semua_kelas&orientasi=landscape" target="_blank" class="option-card landscape">
                <div class="icon-container">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.5"><path d="M20.5 10V7c0-.828-.672-1.5-1.5-1.5H5C4.172 5.5 3.5 6.172 3.5 7v10c0 .828.672 1.5 1.5 1.5h14c.828 0 1.5-.672 1.5-1.5v-3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h3>Landscape</h3>
                <p>Sangat direkomendasikan untuk jadwal yang padat (lebih dari 22 kelas).</p>
            </a>
        </div>
        
        <a href="lihat_jadwal.php" class="back-link">← Kembali ke Halaman Utama</a>
    </div>
</div>

<?php 
// Hilangkan footer agar tampilan bersih dan terpusat
// include 'templates/footer.php'; 
?>