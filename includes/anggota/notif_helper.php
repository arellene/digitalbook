<?php

function kirimNotif(mysqli $conn, int $uid, string $type, string $judul, string $pesan): bool {
    // type: info | success | warning | danger
    $type  = mysqli_real_escape_string($conn, $type);
    $judul = mysqli_real_escape_string($conn, $judul);
    $pesan = mysqli_real_escape_string($conn, $pesan);

    $sql = "INSERT INTO notifikasi (user_id, type, judul, pesan, is_read, created_at)
            VALUES ($uid, '$type', '$judul', '$pesan', 0, NOW())";

    return (bool) mysqli_query($conn, $sql);
}