# Master Plan: PWA Pencatat Keuangan (Frontend + Backend MySQL)

Dokumen ini berisi rencana tahap demi tahap untuk membangun aplikasi Progressive Web App (PWA) pencatat keuangan harian dengan fitur pemindai nota (OCR) dan menggunakan database MySQL.

## 1. Arsitektur Sistem
Karena PWA berjalan di sisi klien (browser), sistem akan dibagi menjadi dua bagian utama:
*   **Frontend (Client-Side):** PWA yang dibangun (misalnya menggunakan Flutter Web) melalui Visual Studio Code. Ini adalah UI yang diakses pengguna.
*   **Backend (Server-Side):** REST API (bisa menggunakan Node.js/Express, PHP/Laravel, atau Python) yang bertugas sebagai jembatan antara aplikasi dan database.
*   **Database:** MySQL untuk penyimpanan data utama secara permanen.

## 2. Struktur Database MySQL (Rancangan Awal)
Buat database bernama `finance_app`, dengan tabel utama `transactions`:
```sql
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('pemasukan', 'pengeluaran') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    category VARCHAR(50),
    transaction_date DATE NOT NULL,
    notes TEXT,
    receipt_image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 3. Fase 1: Pengembangan Backend & Database
1.  **Inisiasi Proyek API:** Buat folder proyek backend di VS Code.
2.  **Koneksi Database:** Konfigurasikan koneksi dari backend ke MySQL.
3.  **Buat Endpoints REST API:**
    *   `POST /api/transactions` -> Untuk menambah data baru (manual atau hasil scan).
    *   `GET /api/transactions` -> Untuk mengambil riwayat transaksi.
    *   `POST /api/upload` -> (Opsional) Untuk mengunggah foto nota ke server dan mengembalikan URL gambar.
4.  **Uji Coba API:** Gunakan ekstensi seperti Thunder Client atau Postman untuk memastikan API bisa membaca dan menulis ke MySQL.

## 4. Fase 2: Pengembangan Frontend (PWA)
1.  **Setup Proyek:** Buat proyek frontend baru di VS Code dan pastikan dukungan PWA diaktifkan.
2.  **Desain UI (User Interface):**
    *   **Halaman Dashboard:** Menampilkan total saldo, daftar transaksi terakhir, dan tombol aksi mengambang (FAB) untuk tambah data.
    *   **Form Input:** Form dengan opsi tipe (pemasukan/pengeluaran), nominal, kategori, tanggal, dan tombol buka kamera.
3.  **Integrasi Kamera & OCR:**
    *   Gunakan library akses kamera.
    *   Gunakan library OCR *client-side* seperti Tesseract.js (jika berbasis JS) atau paket OCR Flutter untuk membaca teks pada gambar nota.
    *   Otomatiskan pengisian field `amount` dan `transaction_date` dari teks hasil scan.
4.  **Integrasi API:** Hubungkan form di aplikasi dengan Endpoint `POST /api/transactions` yang sudah dibuat di backend.
5.  **Strategi Offline (Opsional tapi Direkomendasikan):**
    *   Gunakan IndexedDB atau SQLite lokal untuk menyimpan data saat internet putus.
    *   Sinkronisasikan data lokal ke MySQL melalui API saat perangkat kembali *online*.

## 5. Fase 3: Deployment (Publikasi)
Mengingat GitHub Pages hanya mendukung *Static Hosting*, deployment harus dipisah:
1.  **Frontend (PWA):**
    *   Lakukan proses *build* proyek menjadi file statis web.
    *   *Push* hasil *build* ke repositori GitHub.
    *   Aktifkan **GitHub Pages** untuk meluncurkan PWA secara gratis dengan HTTPS bawaan.
2.  **Backend & Database MySQL:**
    *   Deploy Backend API dan database MySQL ke layanan cloud seperti Render, Railway, Vercel (jika Serverless), atau *shared hosting* biasa.
    *   Pastikan URL API di frontend sudah diarahkan ke alamat backend yang baru di-hosting.
