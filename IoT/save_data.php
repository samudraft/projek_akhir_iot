<?php
header('Content-Type: application/json'); // Set header untuk response JSON

// Konfigurasi database
$host = 'localhost';
$dbname = 'iot_data';
$username = 'root';
$password = '';

try {
    // Koneksi ke database
    $conn = new mysqli($host, $username, $password, $dbname);

    // Periksa koneksi
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Baca data JSON dari permintaan
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    // Cek jika data JSON valid
    if ($data === null) {
        throw new Exception("Invalid JSON data or no data received.");
    }

    // Cek apakah data yang diperlukan ada
    if (!isset($data['temperature']) || !isset($data['humidity'])) {
        throw new Exception("Invalid data: temperature and humidity are required.");
    }

    $temperature = $data['temperature'];
    $humidity = $data['humidity'];

    // Validasi jika temperature dan humidity adalah angka
    if (!is_numeric($temperature) || !is_numeric($humidity)) {
        throw new Exception("Temperature and humidity must be numeric values.");
    }

    // Query untuk menyimpan data dengan timestamp
    $sql = "INSERT INTO sensor_data (temperature, humidity, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Error in preparing SQL statement: " . $conn->error);
    }

    // Bind suhu dan kelembapan sebagai float (double)
    $stmt->bind_param("dd", $temperature, $humidity);

    // Eksekusi query
    if (!$stmt->execute()) {
        throw new Exception("Error executing query: " . $stmt->error);
    }

    // Response sukses
    echo json_encode([
        'success' => true,
        'message' => 'Data successfully saved',
        'data' => [
            'temperature' => $temperature,
            'humidity' => $humidity,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

    $stmt->close();

} catch (Exception $e) {
    // Response error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Tutup koneksi
    if (isset($conn)) {
        $conn->close();
    }
}
?>