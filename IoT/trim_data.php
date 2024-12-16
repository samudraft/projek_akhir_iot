<?php
// Koneksi ke database
$conn = new mysqli('localhost', 'root', '', 'iot_data');

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$excessCount = json_decode(file_get_contents('php://input'), true)['excessCount'];

// Hapus data lama
$conn->query("DELETE FROM sensor_data ORDER BY id ASC LIMIT $excessCount");

if ($conn->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Old logs trimmed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to trim logs']);
}

$conn->close();
?>