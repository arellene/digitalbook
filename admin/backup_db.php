<?php
session_start();
require_once '../config/database.php';

// Cek login & role admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../anggota/dashboard.php");
    exit();
}

// ─── Ambil nama database aktif ────────────────────────────────────────────────
$db_row  = mysqli_fetch_row(mysqli_query($conn, "SELECT DATABASE()"));
$db_name = $db_row[0];

// ─── Nama file output ─────────────────────────────────────────────────────────
$filename = 'backup_' . $db_name . '_' . date('Ymd_His') . '.sql';

// ─── Header HTTP download ─────────────────────────────────────────────────────
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// ─── Fungsi escape nilai SQL ──────────────────────────────────────────────────
function escape_sql_value($conn, $val) {
    if ($val === null) return 'NULL';
    return "'" . mysqli_real_escape_string($conn, $val) . "'";
}

// ─── Header komentar file SQL ─────────────────────────────────────────────────
echo "-- ============================================================\n";
echo "-- Pojok Baca — Database Backup\n";
echo "-- Database : $db_name\n";
echo "-- Tanggal  : " . date('Y-m-d H:i:s') . "\n";
echo "-- ============================================================\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
echo "SET NAMES utf8mb4;\n\n";

// ─── Ambil semua tabel ────────────────────────────────────────────────────────
$tables_result = mysqli_query($conn, "SHOW TABLES");
$tables = [];
while ($row = mysqli_fetch_row($tables_result)) {
    $tables[] = $row[0];
}

// ─── Generate SQL per tabel ───────────────────────────────────────────────────
foreach ($tables as $table) {
    $safe_table = "`$table`";

    echo "-- ────────────────────────────────────────────────────────────\n";
    echo "-- Tabel: $table\n";
    echo "-- ────────────────────────────────────────────────────────────\n\n";

    // DROP + CREATE TABLE
    echo "DROP TABLE IF EXISTS $safe_table;\n";

    $create_result = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE $safe_table"));
    echo $create_result[1] . ";\n\n";

    // INSERT data
    $rows_result = mysqli_query($conn, "SELECT * FROM $safe_table");
    $num_rows    = mysqli_num_rows($rows_result);

    if ($num_rows > 0) {
        // Ambil nama kolom
        $fields_result = mysqli_query($conn, "SHOW COLUMNS FROM $safe_table");
        $columns = [];
        while ($col = mysqli_fetch_assoc($fields_result)) {
            $columns[] = '`' . $col['Field'] . '`';
        }
        $col_list = implode(', ', $columns);

        // Tulis INSERT dalam batch 100 baris
        $batch_size = 100;
        $count = 0;
        $values_batch = [];

        while ($row = mysqli_fetch_row($rows_result)) {
            $values = array_map(fn($v) => escape_sql_value($conn, $v), $row);
            $values_batch[] = '(' . implode(', ', $values) . ')';
            $count++;

            if ($count % $batch_size === 0) {
                echo "INSERT INTO $safe_table ($col_list) VALUES\n";
                echo implode(",\n", $values_batch) . ";\n";
                $values_batch = [];
            }
        }

        // Sisa baris yang belum ditulis
        if (!empty($values_batch)) {
            echo "INSERT INTO $safe_table ($col_list) VALUES\n";
            echo implode(",\n", $values_batch) . ";\n";
        }

        echo "\n";
    } else {
        echo "-- (tidak ada data)\n\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
echo "\n-- ============================================================\n";
echo "-- Backup selesai · " . date('Y-m-d H:i:s') . "\n";
echo "-- ============================================================\n";
exit();