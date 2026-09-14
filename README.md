<p align="center">
  <img src="public/icons/icon.svg" width="100" height="100" alt="myCost Logo">
</p>

<h1 align="center">myCost — Pengelola Keuangan Pribadi & Pemindai Nota OCR</h1>

<p align="center">
  Aplikasi Manajemen Keuangan Pribadi Mandiri (Self-Hosted) Terinspirasi dari <strong>Firefly III</strong> dengan Tampilan Modern <strong>Emerald Green</strong>, Fitur Multi-Rekening, Anggaran Bulanan, Celengan Impian, serta Pemindai Nota Pintar (OCR) Berjalan 100% Lokal.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/PWA-Ready-10b981?style=for-the-badge&logo=pwa&logoColor=white" alt="PWA">
  <img src="https://img.shields.io/badge/OCR-Tesseract.js-34d399?style=for-the-badge" alt="OCR">
  <img src="https://img.shields.io/badge/Theme-Emerald%20Dark-059669?style=for-the-badge" alt="Emerald Theme">
</p>

---

## 🌟 Fitur Utama

### 1. 💼 Multi-Rekening & Dompet (Firefly III Inspired)
* Kelola berbagai jenis rekening: **Kas Tunai / Dompet**, **Rekening Bank (BCA, Mandiri, BRI, dll.)**, **E-Wallet (GoPay, OVO, ShopeePay, DANA)**, dan **Pos Investasi**.
* **Mutasi Saldo Otomatis & Real-time**:
  * **Pemasukan:** Menambah (+) saldo rekening terkait.
  * **Pengeluaran:** Mengurangi (-) saldo rekening terkait.
  * **Transfer Antar Rekening:** Otomatis memotong rekening asal dan menambah rekening tujuan.
  * **Rollback Aman:** Mengedit atau menghapus transaksi otomatis menyesuaikan saldo ke kondisi semula tanpa risiko selisih.

### 2. 🧾 Pemindai Nota Pintar (Universal OCR Scanner)
* Menggunakan **Tesseract.js** yang berjalan 100% di browser / lokal tanpa mengirim data privasi ke server luar.
* Mendukung berbagai format nota:
  * **Minimarket / Retail (Indomaret, Alfamart):** Mendeteksi pola kuantitas x harga satuan dan diskon per item.
  * **Faktur / Invoice Usaha (B2B):** Mendeteksi rincian item, nomor urut, subtotal, dan diskon faktur.
  * **Restoran & Kafe (POS):** Mendeteksi nama menu, modifier (*level pedas, topping*), PB1/Pajak resto, dan biaya layanan.
  * **E-Wallet / Transfer Singkat (GoPay, QRIS):** Deteksi total bayar dengan pembersihan otomatis.
* **Camera Selector:** Otomatis memfilter sensor IR Windows Hello dan memprioritaskan webcam RGB laptop maupun kamera HP.

### 3. ⚖️ Anggaran Bulanan (Budgets)
* Tetapkan limit anggaran pengeluaran per kategori (Makanan, Belanja, Transportasi, dll.).
* Progress bar interaktif dengan indikator warna (*Aman*, *Peringatan >75%*, *Bahaya / Over Budget >90%*).

### 4. 🐷 Celengan & Target Tabungan (Piggy Banks)
* Pasang target tabungan untuk barang impian atau dana darurat.
* Tombol aksi cepat **Nabung (+)** atau **Tarik (-)** langsung dari kartu celengan.

### 5. 📱 PWA & Akses Multi-Device Lokal
* Pasang aplikasi langsung ke layar utama HP atau desktop Windows via Progressive Web App (PWA).
* Akses via jaringan Wi-Fi lokal rumah/kantor tanpa internet.

### 6. ⚡ 1-Click Auto-Start Launcher
* Dilengkapi skrip **`Buka-myCost.bat`** dan **`Buka-myCost-Background.vbs`** yang otomatis memeriksa & menyalakan database MySQL Laragon serta server Laravel di port **7777** secara instan.

---

## 💻 Kebutuhan Sistem (Prerequisites)

* **PHP:** Versi 8.2 atau lebih tinggi (Ekstensi `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` aktif).
* **Database:** MySQL 8.0+ / MariaDB (Direkomendasikan menggunakan **Laragon** atau **XAMPP**).
* **Composer:** Versi 2.x+.
* **Browser:** Google Chrome, Microsoft Edge, Firefox, atau Safari versi modern.

---

## 🛠️ Panduan Instalasi (Setup Guide)

### Langkah 1: Clone Repository
```bash
git clone https://github.com/inihilmyloh/myCost.git
cd myCost
```

### Langkah 2: Install Dependensi PHP
```bash
composer install
```

### Langkah 3: Konfigurasi File Lingkungan (`.env`)
Salin file `.env.example` menjadi `.env`:
```bash
copy .env.example .env
```
Buka file `.env` dan pastikan pengaturan database sesuai:
```env
APP_NAME=myCost
APP_URL=http://localhost:7777

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mycost_db
DB_USERNAME=root
DB_PASSWORD=
```

### Langkah 4: Generate App Key
```bash
php artisan key:generate
```

### Langkah 5: Buat Database & Jalankan Migrasi
1. Buat database baru di MySQL bernama **`mycost_db`** (lewat HeidiSQL, phpMyAdmin, atau MySQL CLI).
2. Jalankan perintah migrasi & seed data awal:
```bash
php artisan migrate --seed
```

---

## 🚀 Panduan Menjalankan Aplikasi

### Cara 1: Menggunakan Skrip 1-Klik (Rekomendasi Windows)
Cukup klik ganda salah satu file di root direktori proyek:
* **`Buka-myCost-Background.vbs`** ➔ Menyalakan MySQL & Server di background (tanpa popup jendela hitam) dan otomatis membuka browser ke `http://localhost:7777`.
* **`Buka-myCost.bat`** ➔ Menyalakan server dengan log konsol interaktif.

> 💡 **Tips Otomatis Saat Booting Laptop:**  
> Tekan `Windows + R`, ketik `shell:startup`, lalu buat *shortcut* dari `Buka-myCost-Background.vbs` ke dalam folder tersebut. myCost akan otomatis siap pakai setiap kali laptop Anda menyala!

### Cara 2: Menjalankan Manual via Terminal
```bash
php artisan serve --host=0.0.0.0 --port=7777
```
Buka browser dan akses **`http://localhost:7777`** atau `http://127.0.0.1:7777`.

---

## 📱 Membuka dari HP (Jaringan Wi-Fi Lokal)

1. Pastikan laptop dan HP terhubung ke jaringan Wi-Fi yang sama.
2. Cek alamat IP lokal laptop Anda (misal: `192.168.1.10` atau `172.16.100.242`) melalui perintah `ipconfig` di CMD.
3. Buka browser di HP dan ketik:
   ```text
   http://[IP_LAPTOP_ANDA]:7777
   ```
4. Klik opsi browser **"Tambahkan ke Layar Utama" (Add to Home Screen)** untuk menginstal myCost sebagai aplikasi PWA.

---

## 📁 Struktur Direktori Penting

```text
myCost/
├── app/
│   ├── Http/Controllers/
│   │   ├── AccountController.php       # Manajemen Rekening Bank & E-Wallet
│   │   ├── AuthController.php          # Otentikasi Pengguna
│   │   ├── BudgetController.php        # Manajemen Batas Anggaran
│   │   ├── CategoryController.php      # Kategori Transaksi
│   │   ├── PiggyBankController.php     # Celengan & Tabungan Impian
│   │   ├── StatsController.php         # Analytics & Dashboard Summary
│   │   └── TransactionController.php   # CRUD Transaksi & Mutasi Saldo
│   └── Models/
│       ├── Account.php
│       ├── Budget.php
│       ├── PiggyBank.php
│       ├── Transaction.php
│       └── TransactionItem.php
├── database/
│   ├── migrations/                     # Skema Database MySQL
│   └── seeders/DatabaseSeeder.php      # Seeder Akun Utama (Izlude)
├── public/
│   ├── css/style.css                   # Desain Modern Emerald Green
│   ├── js/
│   │   ├── app.js                      # Core Frontend Logic & PWA State
│   │   ├── charts.js                   # Visualisasi Chart.js
│   │   └── ocr.js                      # Engine Scanner Tesseract.js Universal
│   ├── manifest.json                   # Konfigurasi PWA
│   └── sw.js                           # Service Worker Offline Cache
├── resources/views/
│   └── app.blade.php                   # Single Page Interface View
├── Buka-myCost.bat                     # Windows Quick Launcher
├── Buka-myCost-Background.vbs          # Windows Silent Startup Launcher
└── routes/
    ├── api.php                         # REST API Endpoints
    └── web.php                         # Web Routes
```

---

## 🛡️ Keamanan & Privasi
* Seluruh data transaksi, mutasi rekening, dan gambar struk disimpan **100% di komputer/server lokal Anda** (`mycost_db`).
* Tidak ada pelacak pihak ketiga atau pengiriman data keuangan ke cloud luar.

---

## 📄 Lisensi
Aplikasi ini bersifat open-source di bawah lisensi [MIT License](LICENSE).
