<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MQTT Monitoring Dashboard with Database</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mqtt/4.3.7/mqtt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Untuk grafik -->
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #FFCFEF;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .card {
            background-color: #fff;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .value-display {
            background-color: #FFCFEF;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        .value-display.temperature {
            background-color: #4CAF50;
            color: white;
        }
        .value-display.humidity {
            background-color: #2196F3;
            color: white;
        }
        .lights {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }
        .light {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        .light.on {
            background-color: #FF8000;
            box-shadow: 0 0 20px #FF8000;
        }
        .light-label {
            margin-top: 10px;
            font-size: 14px;
            color: #333;
        }
        h1, h2 {
            color: #333;
            text-align: center;
        }
        canvas {
            margin: 20px 0;
        }

        .humidity-status {
            margin-top: 20px;
            padding: 15px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            background-color: #f0f0f0;
            border-radius: 8px;
            color: #333;
        }

        /* Tambahan CSS untuk Database Display */
        .database-records {
            max-height: 400px;
            overflow-y: auto;
        }
        .record-item {
            background-color: #f8f9fa;
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .record-item:nth-child(even) {
            border-left-color: #2196F3;
        }
        .record-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #666;
            font-size: 0.9em;
        }
        .record-data {
            display: flex;
            justify-content: space-between;
        }
        .record-value {
            font-size: 1.1em;
            font-weight: bold;
        }
        .temperature-value {
            color: #4CAF50;
        }
        .humidity-value {
            color: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>MQTT Monitoring Dashboard</h1>
        
        <!-- Temperature Display -->
        <div class="card">
            <h2>Temperature</h2>
            <div class="value-display temperature" id="temp-value">--°C</div>
        </div>

        <!-- Humidity Display -->
        <div class="card">
            <h2>Humidity</h2>
            <div class="value-display humidity" id="humid-value">--%</div>
        </div>

        <!-- Lights Display -->
        <div class="card">
            <h2>Lights Status</h2>
            <div class="lights">
                <div class="light" id="light1">
                    <span class="light-label">Lamp 1</span>
                </div>
                <div class="light" id="light2">
                    <span class="light-label">Lamp 2</span>
                </div>
                <div class="light" id="light3">
                    <span class="light-label">Lamp 3</span>
                </div>
            </div>
        </div>

        <!-- Humidity Status Display -->
        <div class="card">
            <h2>Humidity Status</h2>
            <div class="humidity-status" id="humidity-status">--</div>
        </div>

        <!-- Temperature and Humidity Graph -->
        <div class="card">
            <h2>Temperature & Humidity Graph</h2>
            <canvas id="data-chart"></canvas>
        </div>

        <!-- Database Display - NEW -->
        <div class="card">
            <h2>Latest Database Records</h2>
            <div class="database-records" id="database-records">
                <!-- Data akan ditampilkan di sini -->
            </div>
        </div>
    </div>

    <script>
        // MQTT Connection
        const client = mqtt.connect('ws://test.mosquitto.org:8080');

        client.on('connect', () => {
            console.log('Connected to MQTT broker');
            client.subscribe('iot/kendali/samudratemp');
            client.subscribe('iot/kendali/samudrahumd');
            console.log('Subscribed to topics');
        });

        client.on('error', (error) => {
            console.error('Connection error:', error);
        });

        const updateHumidityStatus = (humidity) => {
            const statusElement = document.getElementById('humidity-status');

            if (humidity >= 70) {
                statusElement.textContent = 'Terdapat banyak uap air';
                statusElement.style.backgroundColor = '#FFC107';
            } else if (humidity >= 60) {
                statusElement.textContent = 'Mulai banyak uap air / Normal';
                statusElement.style.backgroundColor = '#4CAF50';
            } else if (humidity >= 30) {
                statusElement.textContent = 'Kering / Aman';
                statusElement.style.backgroundColor = '#2196F3';
            } else {
                statusElement.textContent = 'Data tidak valid';
                statusElement.style.backgroundColor = '#f44336';
            }
        };


        const tempData = [];
        const humidData = [];
        const labels = [];
        const MAX_DATA_POINTS = 10;

        const ctx = document.getElementById('data-chart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: tempData,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.4,
                    },
                    {
                        label: 'Humidity (%)',
                        data: humidData,
                        borderColor: 'rgba(153, 102, 255, 1)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        tension: 0.4,
                    }
                ],
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Time',
                        },
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Value',
                        },
                    },
                },
            },
        });

        client.on('message', (topic, message) => {
            console.log('Received message:', topic, message.toString());
            const value = parseFloat(message.toString());
            const now = new Date().toLocaleTimeString();

            if (topic === 'iot/kendali/samudratemp') {
                document.getElementById('temp-value').textContent = ${value.toFixed(1)}°C;
                updateLights(value);
                saveToDatabase('temperature', value);
                tempData.push(value);
                labels.push(now);

                if (tempData.length > MAX_DATA_POINTS) {
                    tempData.shift();
                    labels.shift();
                }
            } else if (topic === 'iot/kendali/samudrahumd') {
                document.getElementById('humid-value').textContent = ${value.toFixed(1)}%;
                saveToDatabase('humidity', value);
                humidData.push(value);

                if (humidData.length > MAX_DATA_POINTS) {
                    humidData.shift();
                }
            }

            chart.update();
        });

        function updateLights(temp) {
            const light1 = document.getElementById('light1');
            const light2 = document.getElementById('light2');
            const light3 = document.getElementById('light3');

            light1.classList.remove('on');
            light2.classList.remove('on');
            light3.classList.remove('on');

            if (temp >= 31) {
                light1.classList.add('on');
                if (temp >= 33) light2.classList.add('on');
                if (temp >= 35) light3.classList.add('on');
            }
        }

        // Database Records Array and Functions - NEW
        let databaseRecords = [];
        const maxRecords = 15;

        function saveToDatabase(sensorType, value) {
            let data = {};
            
            if (sensorType === 'temperature') {
                data.temperature = value;
                data.humidity = humidData[humidData.length - 1] || 0;
            } else {
                data.humidity = value;
                data.temperature = tempData[tempData.length - 1] || 0;
            }

            // Add to local records
            const timestamp = new Date().toLocaleString();
            const newRecord = {
                timestamp,
                temperature: data.temperature,
                humidity: data.humidity
            };

            // Add to beginning of array
            databaseRecords.unshift(newRecord);

            // Limit to maxRecords
            if (databaseRecords.length > maxRecords) {
                databaseRecords.pop();
            }

            // Update display
            updateDatabaseDisplay();

            // Original AJAX call
            fetch('save_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    console.log('Data berhasil disimpan:', result.data);
                } else {
                    console.error('Gagal menyimpan data:', result.message);
                }
            })
            .catch(error => {
                console.error('Error saat menyimpan data:', error);
            });
        }

        function updateDatabaseDisplay() {
            const container = document.getElementById('database-records');
            container.innerHTML = databaseRecords.map((record, index) => `
                <div class="record-item">
                    <div class="record-header">
                        <span>Record #${databaseRecords.length - index}</span>
                        <span>${record.timestamp}</span>
                    </div>
                    <div class="record-data">
                        <div>
                            <span>Temperature: </span>
                            <span class="record-value temperature-value">${record.temperature.toFixed(1)}°C</span>
                        </div>
                        <div>
                            <span>Humidity: </span>
                            <span class="record-value humidity-value">${record.humidity.toFixed(1)}%</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    </script>
</body>
</html>