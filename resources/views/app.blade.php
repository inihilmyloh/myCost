<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>myCost - Catatan Keuangan PWA & Scanner OCR</title>

  <!-- PWA Settings -->
  <link rel="manifest" href="{{ asset('manifest.json') }}">
  <meta name="theme-color" content="#4f46e5">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="myCost">
  <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">

  <!-- Typography & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Core Styles -->
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

  <!-- Toast Notification Container -->
  <div id="toastContainer" class="toast-container"></div>

  <div class="app-container">

    <!-- Top Navigation Header -->
    <header class="top-nav">
      <a href="./" class="brand-logo">
        <div class="logo-badge">
          <i class="fa-solid fa-wallet"></i>
        </div>
        <div class="brand-name">myCost</div>
      </a>

      <div class="nav-actions">
        <!-- Online/Offline Badge -->
        <div id="onlineStatusBadge" class="status-badge" title="Status Koneksi">
          <span class="status-dot"></span> Online
        </div>

        <!-- DB Status Checker -->
        <button id="dbStatusBtn" class="icon-btn" title="Cek Database MySQL">
          <i class="fa-solid fa-database"></i>
        </button>

        <!-- CSV Export -->
        <button id="exportDataBtn" class="icon-btn" title="Ekspor Data ke CSV">
          <i class="fa-solid fa-file-arrow-down"></i>
        </button>

        <!-- Dark/Light Theme Toggle -->
        <button id="themeToggleBtn" class="icon-btn" title="Ubah Tema">
          <i class="fa-solid fa-sun"></i>
        </button>
      </div>
    </header>

    <!-- Main Balance Hero Widget -->
    <section class="balance-hero">
      <div class="hero-label">Total Saldo Bersih</div>
      <div id="totalBalanceVal" class="hero-amount">Rp 0</div>

      <div class="hero-stats-grid">
        <div class="hero-stat-card">
          <div class="stat-icon-wrap" style="color: #34d399;">
            <i class="fa-solid fa-arrow-down-left"></i>
          </div>
          <div class="stat-info">
            <div class="stat-lbl">Pemasukan Bulan Ini</div>
            <div id="monthIncomeVal" class="stat-val">Rp 0</div>
          </div>
        </div>

        <div class="hero-stat-card">
          <div class="stat-icon-wrap" style="color: #f87171;">
            <i class="fa-solid fa-arrow-up-right"></i>
          </div>
          <div class="stat-info">
            <div class="stat-lbl">Pengeluaran Bulan Ini</div>
            <div id="monthExpenseVal" class="stat-val">Rp 0</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Month Navigation Bar -->
    <div class="month-filter-bar">
      <button id="prevMonthBtn" class="month-nav-btn" title="Bulan Sebelumnya">
        <i class="fa-solid fa-chevron-left"></i>
      </button>
      <div id="currentMonthLabel" class="month-display">
        <i class="fa-regular fa-calendar"></i> Memuat...
      </div>
      <button id="nextMonthBtn" class="month-nav-btn" title="Bulan Selanjutnya">
        <i class="fa-solid fa-chevron-right"></i>
      </button>
    </div>

    <!-- Main Dashboard Grid (Analytics & Transactions) -->
    <main class="dashboard-grid">

      <!-- Left Column: Visual Analytics -->
      <section class="analytics-col">
        <div class="glass-card" style="margin-bottom: 24px;">
          <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-pie"></i> Pengeluaran per Kategori</h2>
          </div>
          <div class="chart-container">
            <canvas id="categoryChart"></canvas>
          </div>
        </div>

        <div class="glass-card">
          <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-simple"></i> Tren Arus Kas (6 Bulan)</h2>
          </div>
          <div class="chart-container">
            <canvas id="trendChart"></canvas>
          </div>
        </div>
      </section>

      <!-- Right Column: Transactions History & Filters -->
      <section class="transactions-col">
        <div class="glass-card">
          <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Transaksi</h2>
            <button class="chip active" id="openScanBtn" style="background: var(--primary-gradient); color: #fff; border: none; padding: 6px 12px; font-size: 12px;">
              <i class="fa-solid fa-camera"></i> Scan Nota
            </button>
          </div>

          <!-- Search & Filter Controls -->
          <div class="filter-container">
            <div class="search-input-wrap">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="searchInput" class="search-input" placeholder="Cari transaksi atau kategori...">
            </div>

            <div class="filter-chips">
              <div class="chip active" data-filter="all">Semua</div>
              <div class="chip" data-filter="pengeluaran">Pengeluaran</div>
              <div class="chip" data-filter="pemasukan">Pemasukan</div>
            </div>
          </div>

          <!-- Dynamic Transactions List -->
          <div id="transactionsList" class="transaction-list">
            <div class="empty-state">
              <i class="fa-solid fa-circle-notch fa-spin"></i>
              <p>Memuat transaksi...</p>
            </div>
          </div>
        </div>
      </section>

    </main>

  </div>

  <!-- Floating Action Button (FAB) -->
  <div class="fab-container">
    <button id="fabAddBtn" class="fab-main" title="Tambah Transaksi">
      <i class="fa-solid fa-plus"></i>
    </button>
  </div>

  <!-- Mobile Bottom Navigation Bar -->
  <nav class="bottom-nav">
    <button class="nav-item active" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
      <i class="fa-solid fa-house"></i>
      <span>Beranda</span>
    </button>
    <button id="navScanBtn" class="nav-item">
      <i class="fa-solid fa-camera"></i>
      <span>Scan Nota</span>
    </button>
    <button id="navAddBtn" class="nav-item">
      <i class="fa-solid fa-circle-plus"></i>
      <span>Tambah</span>
    </button>
  </nav>

  <!-- ============================================== -->
  <!-- MODALS -->
  <!-- ============================================== -->

  <!-- Modal 1: Add / Edit Transaction -->
  <div id="transactionModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle" class="modal-title">Tambah Transaksi</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <form id="transactionForm">
        <!-- Type Switcher -->
        <div class="form-group">
          <label class="form-label">Tipe Transaksi</label>
          <div class="type-toggle-group">
            <button type="button" id="btnTypeExpense" class="type-toggle-btn active expense">
              <i class="fa-solid fa-arrow-up-right"></i> Pengeluaran
            </button>
            <button type="button" id="btnTypeIncome" class="type-toggle-btn">
              <i class="fa-solid fa-arrow-down-left"></i> Pemasukan
            </button>
          </div>
        </div>

        <!-- Amount -->
        <div class="form-group">
          <label class="form-label">Nominal (Rp)</label>
          <div class="input-icon-wrap">
            <span class="input-prefix">Rp</span>
            <input type="number" id="transAmount" class="form-control" placeholder="0" required min="1" step="any">
          </div>
        </div>

        <!-- Category Picker -->
        <div class="form-group">
          <label class="form-label">Kategori</label>
          <div id="categoryGrid" class="category-grid">
            <!-- Dynamic Category Buttons -->
          </div>
        </div>

        <!-- Date -->
        <div class="form-group">
          <label class="form-label">Tanggal Transaksi</label>
          <input type="date" id="transDate" class="form-control" required>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label class="form-label">Catatan / Deskripsi</label>
          <input type="text" id="transNotes" class="form-control" placeholder="Contoh: Belanja bahan masakan">
        </div>

        <!-- Form Action Buttons -->
        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 24px;">
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-check"></i> Simpan Transaksi
          </button>
          <button type="button" id="deleteTransBtn" class="btn-secondary btn-danger" style="display: none;">
            <i class="fa-solid fa-trash-can"></i> Hapus Transaksi Ini
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal 2: Live Camera & OCR Receipt Scanner -->
  <div id="scannerModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title"><i class="fa-solid fa-receipt"></i> Pemindai Nota Pintar (OCR)</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <!-- Viewfinder Viewport -->
      <div class="scanner-viewport">
        <video id="cameraVideo" autoplay playsinline muted></video>
        <div class="scanner-frame">
          <div class="scanner-laser"></div>
        </div>
      </div>
      <canvas id="scannerCanvas" style="display: none;"></canvas>

      <!-- OCR Scanning Progress -->
      <div id="ocrProgressBox" class="ocr-progress-box">
        <div id="ocrProgressText" style="font-size: 13px; font-weight: 600;">Sedang menganalisis teks nota...</div>
        <div class="progress-bar-bg">
          <div id="ocrProgressBar" class="progress-bar-fill"></div>
        </div>
      </div>

      <!-- Scanner Action Controls -->
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <button type="button" id="capturePhotoBtn" class="btn-primary">
          <i class="fa-solid fa-camera"></i> Ambil Foto & Pindai Nota
        </button>

        <label class="btn-secondary" style="cursor: pointer; text-align: center; margin: 0;">
          <i class="fa-solid fa-image"></i> Unggah Foto Struk dari Galeri
          <input type="file" id="uploadReceiptInput" accept="image/*" style="display: none;">
        </label>
      </div>
    </div>
  </div>

  <!-- Modal 3: MySQL Database Connection Status -->
  <div id="dbStatusModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title"><i class="fa-solid fa-server"></i> Status Server & Database</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div id="dbStatusContent" style="padding: 10px 0;">
        <p>Memeriksa koneksi database MySQL...</p>
      </div>
    </div>
  </div>

  <!-- External Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

  <!-- Application Scripts -->
  <script src="{{ asset('js/db-local.js') }}"></script>
  <script src="{{ asset('js/charts.js') }}"></script>
  <script src="{{ asset('js/ocr.js') }}"></script>
  <script src="{{ asset('js/app.js') }}"></script>

</body>
</html>
