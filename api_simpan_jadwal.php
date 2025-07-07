<?php
header('Content-Type: application/json');
require_once 'config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$id_waktu = (int)($input['id_waktu'] ?? 0);
$id_kelas = (int)($input['id_kelas'] ?? 0);
$id_guru_mapel = (int)($input['id_guru_mapel'] ?? 0);

if ($id_waktu === 0 || $id_kelas === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
    exit;
}

$stmt_check_lock = mysqli_prepare($koneksi, "SELECT id_wajib FROM tbl_jadwal_wajib WHERE id_waktu = ? AND id_kelas = ?");
mysqli_stmt_bind_param($stmt_check_lock, 'ii', $id_waktu, $id_kelas);
mysqli_stmt_execute($stmt_check_lock);
mysqli_stmt_store_result($stmt_check_lock);
if (mysqli_stmt_num_rows($stmt_check_lock) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal: Slot ini telah dikunci dan tidak dapat diubah.']);
    exit;
}

if ($id_guru_mapel > 0) {
    $stmt_check_aturan = mysqli_prepare($koneksi, "SELECT COUNT(*) as total FROM tbl_penugasan_kelas WHERE id_guru_mapel = ?");
    mysqli_stmt_bind_param($stmt_check_aturan, 'i', $id_guru_mapel);
    mysqli_stmt_execute($stmt_check_aturan);
    $punya_aturan = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check_aturan))['total'] > 0);

    if ($punya_aturan) {
        $stmt_check_izin = mysqli_prepare($koneksi, "SELECT COUNT(*) as total FROM tbl_penugasan_kelas WHERE id_guru_mapel = ? AND id_kelas = ?");
        mysqli_stmt_bind_param($stmt_check_izin, 'ii', $id_guru_mapel, $id_kelas);
        mysqli_stmt_execute($stmt_check_izin);
        $diizinkan = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check_izin))['total'] > 0);

        if (!$diizinkan) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal: Penugasan ini tidak diizinkan untuk kelas tersebut.']);
            exit;
        }
    }
}

if ($id_guru_mapel === 0) {
    $query = "DELETE FROM tbl_jadwal WHERE id_waktu = ? AND id_kelas = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $id_waktu, $id_kelas);
} else {
    $query = "INSERT INTO tbl_jadwal (id_waktu, id_kelas, id_guru_mapel) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id_guru_mapel = VALUES(id_guru_mapel)";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, 'iii', $id_waktu, $id_kelas, $id_guru_mapel);
}

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success', 'message' => 'Jadwal berhasil diperbarui!']);
} else {
    $error_message = mysqli_error($koneksi);
    if (stripos($error_message, 'Bentrok') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Bentrok! Guru sudah mengajar di waktu yang sama.']);
    } else if (stripos($error_message, 'Duplicate entry') !== false) {
         echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan. Slot ini mungkin sudah terisi.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $error_message]);
    }
}
?>