#include <EEPROM.h>   
#include <ESP8266WiFi.h>   
#include <PubSubClient.h>   
#include "DHT.h" 

#define lampu1 D3   
#define lampu2 D4   
#define lampu3 D6   
#define DHTPIN D5 
#define DHTTYPE DHT11 

DHT dht(DHTPIN, DHTTYPE); 

const char* ssid = "Sam";   
const char* password = "samudraft";   
const char* mqtt_server = "test.mosquitto.org";   
const char* user_mqtt = "";   
const char* pass_mqtt = "";   

// Topik MQTT
const char* topik_temp = "iot/kendali/samudratemp";
const char* topik_hum = "iot/kendali/samudrahumd";
const char* topik_lampu1 = "iot/kendali/samudralampu1";
const char* topik_lampu2 = "iot/kendali/samudralampu2";
const char* topik_lampu3 = "iot/kendali/samudralampu3";

WiFiClient espClient;   
PubSubClient client(espClient);   
String messages;  // Variabel untuk menyimpan pesan MQTT 

unsigned long previousMillis = 0;  // Variabel waktu sebelumnya untuk interval
const unsigned long interval = 5000;  // Interval pengiriman data (8 detik)

// Fungsi untuk menangani pesan MQTT yang diterima 
void callback(char* topic, byte* payload, unsigned int length) {   
    Serial.print("Pesan dari MQTT [");   
    Serial.print(topic);   
    Serial.print("] : ");   
    messages = "";  // Reset pesan 

    // Menyusun pesan dari payload 
    for (int i = 0; i < length; i++) {   
        messages += (char)payload[i];    
    }   

    Serial.println(messages);  // Cetak pesan untuk debugging 

    // Logika untuk menyalakan atau mematikan lampu berdasarkan pesan 
    if (strcmp(topic, topik_lampu1) == 0) {
        if (messages == "D3=1") { 
            digitalWrite(lampu1, HIGH); 
        } else if (messages == "D3=0") { 
            digitalWrite(lampu1, LOW); 
        }
    }

    if (strcmp(topic, topik_lampu2) == 0) {
        if (messages == "D4=1") { 
            digitalWrite(lampu2, HIGH); 
        } else if (messages == "D4=0") { 
            digitalWrite(lampu2, LOW); 
        }
    }
  
    if (strcmp(topic, topik_lampu3) == 0) {
        if (messages == "D6=1") { 
            digitalWrite(lampu3, HIGH); 
        } else if (messages == "D6=0") { 
            digitalWrite(lampu3, LOW); 
        }
    } 
} 

// Fungsi untuk menghubungkan ke MQTT Server 
void reconnect() {   
    while (!client.connected()) {   
        Serial.print("Menghubungkan ke MQTT Server -> ");   
        Serial.println(mqtt_server);   

        if (client.connect("G.231.22.0046", user_mqtt, pass_mqtt)) {   
            Serial.println("Terhubung!");   
            client.subscribe(topik_lampu1);  // Langganan ke topic
            client.subscribe(topik_lampu2);  // Langganan ke topic
            client.subscribe(topik_lampu3);  // Langganan ke topic
        } else {   
            Serial.print("Gagal, rc=");   
            Serial.println(client.state()); 
            delay(5000);  // Tunggu 5 detik sebelum mencoba lagi 
        }   
    }   
} 

// Fungsi untuk menghubungkan ke WiFi 
void konek_wifi() {   
    WiFi.begin(ssid, password);   
    while (WiFi.status() != WL_CONNECTED) {   
        delay(500);   
        Serial.print(".");   
    }   
    Serial.println("\nWiFi terhubung");   
} 

void setup() {   
    Serial.begin(115200);   
    client.setServer(mqtt_server, 1883);   
    client.setCallback(callback); 
    dht.begin(); 

    // Mengatur pin lampu sebagai output   
    pinMode(lampu1, OUTPUT);   
    pinMode(lampu2, OUTPUT);   
    pinMode(lampu3, OUTPUT);   
} 

void loop() { 
    if (WiFi.status() != WL_CONNECTED) konek_wifi();  // Reconnect WiFi jika terputus   
    if (!client.connected()) reconnect();             // Reconnect MQTT jika terputus   
    client.loop();  // Jalankan client MQTT 

    unsigned long currentMillis = millis();  // Ambil waktu sekarang
    if (currentMillis - previousMillis >= interval) {
        previousMillis = currentMillis;  // Update waktu sebelumnya

        float h = dht.readHumidity(); 
        float t = dht.readTemperature(); 

        Serial.print("Suhu: ");
        Serial.print(t);
        Serial.print(" C, Kelembaban: ");
        Serial.print(h);
        Serial.println(" %");

        char tempString[8];
        char humString[8];
        dtostrf(t, 1, 2, tempString);
        dtostrf(h, 1, 2, humString);

        // Publish data suhu dan kelembapan
        client.publish(topik_temp, tempString);
        client.publish(topik_hum, humString);  

        // Kendali lampu berdasarkan suhu
        if (t >= 31 && t < 33) {
            digitalWrite(lampu1, HIGH);
            digitalWrite(lampu2, LOW);
            digitalWrite(lampu3, LOW);
        } 
        else if (t >= 33 && t < 35) {
            digitalWrite(lampu1, HIGH);
            digitalWrite(lampu2, HIGH);
            digitalWrite(lampu3, LOW);
        } 
        else if (t >= 35) {
            digitalWrite(lampu1, HIGH);
            digitalWrite(lampu2, HIGH);
            digitalWrite(lampu3, HIGH);
        } else {
            digitalWrite(lampu1, LOW);
            digitalWrite(lampu2, LOW);
            digitalWrite(lampu3, LOW);
        }
    }
}