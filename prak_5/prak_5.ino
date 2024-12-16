#include <EEPROM.h>
#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <LiquidCrystal_I2C.h>
#include <Wire.h>
#include "DHT.h"

// Pin lampu (sesuai yang tersedia)
#define lampu1 D3
#define lampu2 D4
#define lampu3 D6



// Pin dan tipe DHT
#define DHTPIN D5
#define DHTTYPE DHT11

// Informasi WiFi dan MQTT
const char* ssid = "Sam";
const char* password = "samudraft";
const char* mqtt_server = "test.mosquitto.org";
const char* topik = "iot/kendali/G.231.22.0046";

// Inisialisasi objek
WiFiClient espClient;
PubSubClient client(espClient);
DHT dht(DHTPIN, DHTTYPE);
LiquidCrystal_I2C lcd(0x27, 16, 2);
String messages;

// Variabel untuk mengatur waktu tampilan notifikasi
unsigned long notifStartTime = 0;
const long notifDuration = 2000; // Durasi notifikasi (2 detik)
bool isShowingNotif = false;

// Variabel untuk update suhu
unsigned long lastTempUpdate = 0;
const long tempUpdateInterval = 2000; // Update suhu setiap 2 detik

void showTemperatureAndHumidity() {
  float h = dht.readHumidity();
  float t = dht.readTemperature();

  if (isnan(h) || isnan(t)) {
    lcd.setCursor(0, 0);
    lcd.print("Error DHT       ");
    return;
  }

  // Update tampilan tanpa clear
  lcd.setCursor(0, 0);
  String tempStr = "Suhu : " + String(t) + "C    ";
  lcd.print(tempStr);
  
  lcd.setCursor(0, 1);
  String humStr = "Kelemb: " + String(h) + "%    ";
  lcd.print(humStr);
}

void showNotification(String message) {
  lcd.clear(); // Clear hanya saat menampilkan notifikasi
  lcd.setCursor(0, 0);
  lcd.print(message);
  isShowingNotif = true;
  notifStartTime = millis();
}

void callback(char* topic, byte* payload, unsigned int length) {
  Serial.print("Pesan dari MQTT [");
  Serial.print(topic);
  Serial.print("] ");

  messages = "";
  for (int i = 0; i < length; i++) {
    messages += (char)payload[i];
  }
  Serial.println(messages);

  // Kendali lampu dan tampilkan notifikasi
  if (messages == "D3=1") {
    digitalWrite(lampu1, HIGH);
    showNotification("Lampu 1 ON");
  }
  if (messages == "D3=0") {
    digitalWrite(lampu1, LOW);
    showNotification("Lampu 1 OFF");
  }
  if (messages == "D4=1") {
    digitalWrite(lampu2, HIGH);
    showNotification("Lampu 2 ON");
  }
  if (messages == "D4=0") {
    digitalWrite(lampu2, LOW);
    showNotification("Lampu 2 OFF");
  }
  if (messages == "D6=1") {
    digitalWrite(lampu3, HIGH);
    showNotification("Lampu 3 ON");
  }
  if (messages == "D6=0") {
    digitalWrite(lampu3, LOW);
    showNotification("Lampu 3 OFF");
  }


}

void konek_wifi() {
  Serial.print("Menghubungkan ke WiFi...");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected");
  Serial.print("IP Address: ");
  Serial.println(WiFi.localIP());
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Menghubungkan ke MQTT Server: ");
    Serial.print(mqtt_server);
    String clientId = "ESP8266Client-" + String(random(0xffff), HEX);

    if (client.connect(clientId.c_str())) {
      Serial.println("\nTerhubung ke MQTT!");
      client.subscribe(topik);
    } else {
      Serial.print("\nGagal, rc=");
      Serial.print(client.state());
      Serial.println(". Coba lagi dalam 5 detik.");
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  
  // Inisialisasi I2C dan LCD
  Wire.begin(D1, D2);
  lcd.begin(16, 2);
  lcd.backlight();
  
  // Inisialisasi DHT
  dht.begin();
  
  // Koneksi WiFi
  konek_wifi();

  // Setup MQTT
  client.setServer(mqtt_server, 1883);
  client.setCallback(callback);

  // Setup pin lampu
  pinMode(lampu1, OUTPUT);
  pinMode(lampu2, OUTPUT);
  pinMode(lampu3, OUTPUT);



  // Matikan semua lampu
  digitalWrite(lampu1, LOW);
  digitalWrite(lampu2, LOW);
  digitalWrite(lampu3, LOW);


  // Tampilkan pesan awal
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("System Ready!");
  delay(2000);
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    konek_wifi();
  }

  if (!client.connected()) {
    reconnect();
  }

  client.loop();

  unsigned long currentMillis = millis();

  // Cek apakah sedang menampilkan notifikasi
  if (isShowingNotif && (currentMillis - notifStartTime >= notifDuration)) {
    isShowingNotif = false;
    lcd.clear(); // Clear setelah notifikasi selesai
    showTemperatureAndHumidity();
  }

  // Update suhu dan kelembapan jika tidak sedang menampilkan notifikasi
  if (!isShowingNotif && (currentMillis - lastTempUpdate >= tempUpdateInterval)) {
    showTemperatureAndHumidity();
    lastTempUpdate = currentMillis;
  }

  delay(100); // Delay kecil untuk stabilitas
}