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
            background-color: #f0f0f0;
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
            background-color: #f5f5f5;
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
            background-color: #FFD700;
            box-shadow: 0 0 20px #FFD700;
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

        <!-- Temperature and Humidity Graph -->
        <div class="card">
            <h2>Temperature & Humidity Graph</h2>
            <canvas id="data-chart"></canvas>
        </div>
    </div>

    <script>
        // MQTT Connection
        const client = mqtt.connect('ws://test.mosquitto.org:8080');

        // Debug: Log connection status
        client.on('connect', () => {
            console.log('Connected to MQTT broker');
            // Subscribe ke topik
            client.subscribe('iot/kendali/samudratemp');
            client.subscribe('iot/kendali/samudrahumd');
            console.log('Subscribed to topics');
        });

        // Debug: Log connection errors
        client.on('error', (error) => {
            console.error('Connection error:', error);
        });

        // Data arrays for the graph
        const tempData = [];
        const humidData = [];
        const labels = []; // For timestamps
        const MAX_DATA_POINTS = 10;

        // Chart.js Initialization
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

        // Debug: Log when messages are received
        client.on('message', (topic, message) => {
            console.log('Received message:', topic, message.toString());
            const value = parseFloat(message.toString());
            const now = new Date().toLocaleTimeString(); // Current timestamp

            if (topic === 'iot/kendali/firmantemp') {
                document.getElementById('temp-value').textContent = ${value.toFixed(1)}°C;
                updateLights(value);
                saveToDatabase('temperature', value); // Save to database
                tempData.push(value);
                labels.push(now);

                // Remove old data if exceeding maximum points
                if (tempData.length > MAX_DATA_POINTS) {
                    tempData.shift();
                    labels.shift(); }
            } else if (topic === 'iot/kendali/firmanhumd') {
                document.getElementById('humid-value').textContent = ${value.toFixed(1)}%;
                saveToDatabase('humidity', value); // Save to database
                humidData.push(value);
                // Remove old data if exceeding maximum points
                if (humidData.length > MAX_DATA_POINTS) {
                    humidData.shift();
                }
            }

            // Update chart
            chart.update();
        });

        function updateLights(temp) {
            const light1 = document.getElementById('light1');
            const light2 = document.getElementById('light2');
            const light3 = document.getElementById('light3');
            
            // Reset all lights
            light1.classList.remove('on');
            light2.classList.remove('on');
            light3.classList.remove('on');
            
            // Update lights based on temperature thresholds
            if (temp >= 31) {
                light1.classList.add('on');
                if (temp >= 33) light2.classList.add('on');
                if (temp >= 35) light3.classList.add('on');
            }
        }

        function saveToDatabase(temp, humid) {
    fetch('save_data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ temperature: temp, humidity: humid }),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log('Data saved:', data);
        })
        .catch((error) => {
            console.error('Error saving data:', error);
        });
}
    </script>
</body>
</html>