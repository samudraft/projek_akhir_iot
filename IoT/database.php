<?php
// database.php
$host = "localhost";       // Ganti sesuai konfigurasi server
$username = "root";        // Username database
$password = "";            // Password database
$database = "iot_data";    // Nama database

// Membuat koneksi
$conn = new mysqli($host, $username, $password, $database);

// Cek koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>