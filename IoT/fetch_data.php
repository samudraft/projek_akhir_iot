<?php
// Koneksi ke database
$conn = new mysqli('localhost', 'root', '', 'iot_data');

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Ambil data dari tabel
$result = $conn->query("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 15");

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Balikkan data dalam urutan terbaru
echo json_encode(array_reverse($data));

$conn->close();
?>