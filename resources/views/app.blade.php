<!DOCTYPE html>
<html lang="id" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>myCost - Pengelola Keuangan Pribadi & Scanner OCR</title>

  <!-- PWA Settings -->
  <link rel="manifest" href="{{ asset('manifest.json') }}">
  <meta name="theme-color" content="#10b981">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="myCost">
  <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Core Styles -->
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?v=5.0">
</head>

<body>

  <!-- Toast Container -->
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
        <!-- User Profile Pill -->
        <div id="userProfilePill" class="user-pill" title="Akun Pengguna">
          <div id="userAvatar" class="user-avatar"><i class="fa-solid fa-user"></i></div>
          <span id="userNameLabel" class="user-name-label">Masuk / Daftar</span>
        </div>

        <!-- Online/Offline Badge -->
        <div id="onlineStatusBadge" class="status-badge" title="Status Jaringan">
          <span class="status-dot"></span> Online
        </div>

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

    <!-- Guest Alert Banner (Visible when not logged in) -->
    <div id="guestBanner" class="guest-banner" style="display: none;">
      <i class="fa-solid fa-user-lock"></i>
      <span>Anda belum login. <strong>Klik di sini untuk Masuk atau Buat Akun</strong> agar data keuangan Anda tersimpan
        aman.</span>
      <i class="fa-solid fa-chevron-right"></i>
    </div>

    <!-- Main Feature Navigation Tabs (Firefly III Style) -->
    <nav class="main-tabs-bar">
      <button class="tab-nav-btn active" data-tab="dashboard" onclick="app.switchView('dashboard')">
        <i class="fa-solid fa-chart-line"></i> Dashboard
      </button>
      <button class="tab-nav-btn" data-tab="accounts" onclick="app.switchView('accounts')">
        <i class="fa-solid fa-wallet"></i> Rekening & Dompet
      </button>
      <button class="tab-nav-btn" data-tab="budgets" onclick="app.switchView('budgets')">
        <i class="fa-solid fa-scale-balanced"></i> Anggaran
      </button>
      <button class="tab-nav-btn" data-tab="piggy" onclick="app.switchView('piggy')">
        <i class="fa-solid fa-piggy-bank"></i> Celengan Impian
      </button>
    </nav>

    <!-- ============================================== -->
    <!-- VIEW 1: DASHBOARD -->
    <!-- ============================================== -->
    <section id="viewDashboard" class="view-section active">

      <!-- Main Balance & Net Worth Hero Widget -->
      <section class="balance-hero">
        <div class="hero-label">Total Kekayaan Bersih (Net Worth)</div>
        <div id="totalBalanceVal" class="hero-amount">Rp 0</div>

        <div class="hero-stats-grid">
          <div class="hero-stat-card">
            <div class="stat-icon-wrap" style="color: #34d399;">
              <i class="fa-solid fa-arrow-down"></i>
            </div>
            <div class="stat-info">
              <div class="stat-lbl">Pemasukan Bulan Ini</div>
              <div id="monthIncomeVal" class="stat-val">Rp 0</div>
            </div>
          </div>

          <div class="hero-stat-card">
            <div class="stat-icon-wrap" style="color: #ef4444;">
              <i class="fa-solid fa-arrow-up"></i>
            </div>
            <div class="stat-info">
              <div class="stat-lbl">Pengeluaran Bulan Ini</div>
              <div id="monthExpenseVal" class="stat-val">Rp 0</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Quick Action Bar -->
      <section class="quick-actions">
        <div class="action-card-btn" onclick="app.openTransactionModal({type:'pengeluaran'})">
          <i class="fa-solid fa-circle-minus" style="color: var(--expense);"></i>
          <span>Catat Keluar</span>
        </div>
        <div class="action-card-btn scan" onclick="app.openScannerModal()">
          <i class="fa-solid fa-camera-retro"></i>
          <span>Scan Nota</span>
        </div>
        <div class="action-card-btn" onclick="app.openTransactionModal({type:'pemasukan'})">
          <i class="fa-solid fa-circle-plus" style="color: var(--income);"></i>
          <span>Catat Masuk</span>
        </div>
        <div class="action-card-btn" onclick="app.openTransactionModal({type:'transfer'})">
          <i class="fa-solid fa-money-bill-transfer" style="color: var(--primary-light);"></i>
          <span>Transfer</span>
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
            </div>

            <!-- Search & Filter Controls -->
            <div class="filter-container">
              <div class="search-input-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" class="search-input"
                  placeholder="Cari transaksi, toko, atau kategori...">
              </div>

              <div class="filter-chips">
                <div class="chip active" data-filter="all">Semua</div>
                <div class="chip" data-filter="pengeluaran">Pengeluaran</div>
                <div class="chip" data-filter="pemasukan">Pemasukan</div>
                <div class="chip" data-filter="transfer">Transfer</div>
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

    </section>

    <!-- ============================================== -->
    <!-- VIEW 2: REKENING & DOMPET (ACCOUNTS) -->
    <!-- ============================================== -->
    <section id="viewAccounts" class="view-section">
      <div class="glass-card">
        <div class="card-header">
          <div>
            <h2 class="card-title"><i class="fa-solid fa-wallet"></i> Daftar Rekening & Dompet</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Kelola kas tunai, rekening bank,
              e-wallet, dan pos investasi Anda.</p>
          </div>
          <button type="button" class="btn-primary" style="width: auto; padding: 10px 18px; font-size: 13px;"
            onclick="app.openAccountModal()">
            <i class="fa-solid fa-plus"></i> Tambah Rekening
          </button>
        </div>

        <div id="accountsListContainer" class="accounts-grid">
          <!-- Rendered via JS -->
        </div>
      </div>
    </section>

    <!-- ============================================== -->
    <!-- VIEW 3: ANGGARAN BULANAN (BUDGETS) -->
    <!-- ============================================== -->
    <section id="viewBudgets" class="view-section">
      <div class="glass-card">
        <div class="card-header">
          <div>
            <h2 class="card-title"><i class="fa-solid fa-scale-balanced"></i> Anggaran Bulanan per Kategori</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Tetapkan limit pengeluaran bulanan
              agar keuangan tetap terkendali.</p>
          </div>
          <button type="button" class="btn-primary" style="width: auto; padding: 10px 18px; font-size: 13px;"
            onclick="app.openBudgetModal()">
            <i class="fa-solid fa-plus"></i> Pasang Anggaran
          </button>
        </div>

        <div id="budgetsSummaryBox" style="margin-bottom: 20px;"></div>
        <div id="budgetsListContainer" class="budget-grid">
          <!-- Rendered via JS -->
        </div>
      </div>
    </section>

    <!-- ============================================== -->
    <!-- VIEW 4: CELENGAN IMPIAN (PIGGY BANKS) -->
    <!-- ============================================== -->
    <section id="viewPiggy" class="view-section">
      <div class="glass-card">
        <div class="card-header">
          <div>
            <h2 class="card-title"><i class="fa-solid fa-piggy-bank"></i> Celengan & Target Tabungan</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Wujudkan barang impian atau dana
              darurat dengan menabung teratur.</p>
          </div>
          <button type="button" class="btn-primary" style="width: auto; padding: 10px 18px; font-size: 13px;"
            onclick="app.openPiggyModal()">
            <i class="fa-solid fa-plus"></i> Buat Target Celengan
          </button>
        </div>

        <div id="piggyListContainer" class="piggy-grid">
          <!-- Rendered via JS -->
        </div>
      </div>
    </section>

  </div>

  <!-- Floating Action Button (FAB) -->
  <div class="fab-container">
    <button id="fabAddBtn" class="fab-main" title="Tambah Transaksi">
      <i class="fa-solid fa-plus"></i>
    </button>
  </div>

  <!-- Mobile Bottom Navigation Bar -->
  <nav class="bottom-nav">
    <button class="nav-item active" data-tab="dashboard" onclick="app.switchView('dashboard')">
      <i class="fa-solid fa-house"></i>
      <span>Beranda</span>
    </button>
    <button class="nav-item" data-tab="accounts" onclick="app.switchView('accounts')">
      <i class="fa-solid fa-wallet"></i>
      <span>Rekening</span>
    </button>
    <button class="nav-item" data-tab="budgets" onclick="app.switchView('budgets')">
      <i class="fa-solid fa-scale-balanced"></i>
      <span>Anggaran</span>
    </button>
    <button class="nav-item" data-tab="piggy" onclick="app.switchView('piggy')">
      <i class="fa-solid fa-piggy-bank"></i>
      <span>Celengan</span>
    </button>
    <button id="navAuthBtn" class="nav-item" data-tab="auth"
      onclick="app.openAuthModal(app.currentUser ? 'profile' : 'login')">
      <i class="fa-solid fa-circle-user"></i>
      <span id="navAuthLabel">Akun</span>
    </button>
  </nav>

  <!-- ============================================== -->
  <!-- MODALS -->
  <!-- ============================================== -->

  <!-- Modal 1: Add / Edit Transaction (With Transfer & Account support) -->
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
          <div class="type-toggle-group" style="grid-template-columns: 1fr 1fr 1fr;">
            <button type="button" id="btnTypeExpense" class="type-toggle-btn active expense">
              <i class="fa-solid fa-arrow-up"></i> Keluar
            </button>
            <button type="button" id="btnTypeIncome" class="type-toggle-btn">
              <i class="fa-solid fa-arrow-down"></i> Masuk
            </button>
            <button type="button" id="btnTypeTransfer" class="type-toggle-btn">
              <i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer
            </button>
          </div>
        </div>

        <!-- Account Picker -->
        <div class="form-group">
          <label id="lblAccountSource" class="form-label">Rekening / Dompet</label>
          <select id="transAccountSelect" class="form-control" required>
            <!-- Rendered dynamically -->
          </select>
        </div>

        <!-- Destination Account (For Transfer) -->
        <div id="destAccountGroup" class="form-group" style="display: none;">
          <label class="form-label">Ke Rekening / Dompet Tujuan</label>
          <select id="transDestAccountSelect" class="form-control">
            <!-- Rendered dynamically -->
          </select>
        </div>

        <!-- Detected OCR Candidate Numbers (if any) -->
        <div id="candidateAmountsBox" class="candidate-chips-wrap" style="display: none;">
          <div class="candidate-chips-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Angka Terdeteksi dari Nota
            (Klik untuk pilih):</div>
          <div id="candidateChipsList" class="candidate-chips"></div>
        </div>

        <!-- Amount -->
        <!-- Amount -->
        <div class="form-group">
          <label class="form-label">Total Nominal (Rp)</label>
          <div class="input-icon-wrap">
            <span class="input-prefix">Rp</span>
            <input type="number" id="transAmount" class="form-control" placeholder="0" required min="1" step="any">
          </div>
        </div>

        <!-- Admin Fee / Biaya Admin (For Transfer & Expenses) -->
        <div class="form-group" id="adminFeeGroup">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="form-label" style="margin-bottom: 0;"><i class="fa-solid fa-receipt"
                style="color: var(--primary-light);"></i> Biaya Admin / Transfer</label>
            <span id="adminFeeHelperText" style="font-size: 11px; color: var(--text-muted);">Rp 0 (Gratis)</span>
          </div>
          <div class="admin-fee-chips-wrap">
            <button type="button" class="fee-chip active" data-fee="0" onclick="app.setAdminFee(0)">Gratis (Rp
              0)</button>
            <button type="button" class="fee-chip" data-fee="1000" onclick="app.setAdminFee(1000)">Rp 1.000</button>
            <button type="button" class="fee-chip" data-fee="2500" onclick="app.setAdminFee(2500)">Rp 2.500
              (BI-FAST)</button>
            <button type="button" class="fee-chip" data-fee="6500" onclick="app.setAdminFee(6500)">Rp 6.500
              (Online)</button>
          </div>
          <div class="input-icon-wrap" style="margin-top: 8px;">
            <span class="input-prefix">Rp</span>
            <input type="number" id="transAdminFee" class="form-control" placeholder="0" min="0" step="any" value="0"
              oninput="app.onAdminFeeInput()">
          </div>
          <div id="transTotalDeductionBox" class="deduction-summary-box" style="margin-top: 8px;">
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted);">
              <span>Nominal: <strong id="summaryBaseAmount">Rp 0</strong></span>
              <span>+ Admin: <strong id="summaryAdminFee" style="color: var(--expense);">Rp 0</strong></span>
            </div>
            <div
              style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 800; margin-top: 4px;">
              <span>Total Terpotong dari Rekening:</span>
              <span id="transTotalDeductionVal" style="color: var(--primary-light);">Rp 0</span>
            </div>
          </div>
        </div>

        <!-- Itemized Receipt Breakdown Table (Hidden on transfer) -->
        <div id="itemizedSection" class="items-section">
          <div class="items-header">
            <div class="items-title">
              <i class="fa-solid fa-list-check"></i> Rincian Barang / Struk
            </div>
            <button type="button" id="addItemBtn" class="add-item-btn">
              <i class="fa-solid fa-plus"></i> Tambah Item
            </button>
          </div>

          <div style="overflow-x: auto;">
            <table class="items-table">
              <thead>
                <tr>
                  <th>Nama Barang</th>
                  <th style="width: 65px;">Qty</th>
                  <th style="width: 100px;">Harga Satuan</th>
                  <th style="width: 90px;">Diskon</th>
                  <th style="width: 36px;"></th>
                </tr>
              </thead>
              <tbody id="itemsTableBody">
                <!-- Dynamic Item Rows -->
              </tbody>
            </table>
          </div>

          <div class="breakdown-summary">
            <div class="breakdown-row">
              <span style="color: var(--text-muted);">Subtotal Barang:</span>
              <strong id="breakdownSubtotal">Rp 0</strong>
            </div>
            <div class="breakdown-row">
              <span style="color: var(--income);">Total Diskon / Hemat:</span>
              <strong id="breakdownDiscount" style="color: var(--income);">Rp 0</strong>
            </div>
          </div>
        </div>

        <!-- Category Picker (Hidden on transfer) -->
        <div id="categoryGroup" class="form-group">
          <label class="form-label">Kategori</label>
          <div id="categoryGrid" class="category-grid"></div>
        </div>

        <!-- Date -->
        <div class="form-group">
          <label class="form-label">Tanggal Transaksi</label>
          <input type="date" id="transDate" class="form-control" required>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label class="form-label">Catatan / Keterangan</label>
          <input type="text" id="transNotes" class="form-control"
            placeholder="Contoh: Belanja Bulanan / Transfer Uang Jajan">
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

  <!-- Modal 2: Add / Edit Account -->
  <div id="accountModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="accountModalTitle" class="modal-title">Tambah Rekening / Dompet</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <form id="accountForm">
        <div class="form-group">
          <label class="form-label">Nama Rekening / Dompet</label>
          <input type="text" id="accName" class="form-control" placeholder="Contoh: Seabank / Bank BCA / Dompet Saku"
            required>
        </div>

        <div class="form-group">
          <label class="form-label">Jenis Akun</label>
          <select id="accType" class="form-control" required>
            <option value="bank">Rekening Bank</option>
            <option value="cash">Kas Tunai / Dompet</option>
            <option value="ewallet">E-Wallet (GoPay/OVO/ShopeePay/DANA)</option>
            <option value="investment">Investasi / Reksa Dana / Saham</option>
          </select>
        </div>

        <div id="accSubTypeSection">
        <!-- Account Category: Saldo Biasa vs Tabungan Berbunga -->
        <div class="form-group">
          <label class="form-label">Tipe Saldo Tabungan</label>
          <div class="type-toggle-group" style="grid-template-columns: 1fr 1fr;">
            <button type="button" id="btnAccSubRegular" class="type-toggle-btn active"
              onclick="app.setAccountSubType('regular')">
              <i class="fa-solid fa-wallet"></i> Saldo Biasa
            </button>
            <button type="button" id="btnAccSubSavings" class="type-toggle-btn"
              onclick="app.setAccountSubType('savings')">
              <i class="fa-solid fa-percent"></i> Tabungan Berbunga
            </button>
          </div>
          <p style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">
            *Tabungan Berbunga berfungsi normal untuk transaksi harian & otomatis memperoleh bunga harian ke saldo.
          </p>
        </div>

        <!-- Interest Configuration Section (Visible when savings) -->
        <div id="accInterestSettingsBox" class="interest-settings-box" style="display: none;">
          <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <strong style="font-size: 12px; color: var(--primary-light);"><i class="fa-solid fa-calculator"></i>
                Konfigurasi Suku Bunga Tabungan</strong>
              <span style="font-size: 10px; color: var(--text-muted);">Pilih Preset Cepat:</span>
            </div>
            <div class="preset-buttons-wrap" style="display: flex; flex-wrap: wrap; gap: 6px;">
              <button type="button" class="preset-badge-btn" onclick="app.applyInterestPreset('seabank')">
                <i class="fa-solid fa-building-columns"></i> SeaBank (2 Tier)
              </button>
              <button type="button" class="preset-badge-btn" onclick="app.applyInterestPreset('jago')">
                <i class="fa-solid fa-piggy-bank"></i> Bank Jago (Flat)
              </button>
              <button type="button" class="preset-badge-btn" onclick="app.applyInterestPreset('neobank')">
                <i class="fa-solid fa-coins"></i> NeoBank (3 Tier)
              </button>
              <button type="button" class="preset-badge-btn" onclick="app.applyInterestPreset('custom')">
                <i class="fa-solid fa-pen"></i> Kustom
              </button>
            </div>
          </div>

          <!-- Dynamic Tier Builder -->
          <div class="tier-builder-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span style="font-size: 11px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-layer-group" style="color: var(--primary);"></i> Skema Tingkatan (Tier) Bunga
              </span>
              <button type="button" class="btn-add-tier-pill" onclick="app.addInterestTierRow()">
                <i class="fa-solid fa-plus"></i> Tambah Tier
              </button>
            </div>

            <div id="interestTierRowsList" class="interest-tier-rows-list">
              <!-- Dynamically populated tier rows -->
            </div>
            
            <div style="font-size: 10px; color: var(--text-muted); margin-top: 8px; line-height: 1.4;">
              <i class="fa-solid fa-circle-info" style="color: var(--primary-light);"></i> Anda bisa menambah tier sebanyak yang diinginkan. Bunga akan otomatis aktif sesuai tier saldo Anda.
            </div>
          </div>
          <!-- Monthly Admin Fee Section -->
          <div id="accMonthlyAdminFeeSection" class="monthly-admin-box" style="margin-top: 12px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <strong style="font-size: 12px; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                <i class="fa-regular fa-credit-card" style="color: #f59e0b;"></i> Biaya Admin Bulanan & Kartu Debit
              </strong>
              <span style="font-size: 10px; color: var(--text-muted);">Auto-Pengeluaran</span>
            </div>

            <!-- Preset Cepat Kartu Debit Bank -->
            <div style="margin-bottom: 10px;">
              <span style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 4px;">Preset Kartu Populer:</span>
              <div class="preset-buttons-wrap" style="display: flex; flex-wrap: wrap; gap: 5px;">
                <button type="button" class="preset-badge-btn" onclick="app.applyAdminFeePreset(0)">
                  <i class="fa-solid fa-gift"></i> Rp 0 (SeaBank/Jago)
                </button>
                <button type="button" class="preset-badge-btn" onclick="app.applyAdminFeePreset(12000, 25)">
                  <i class="fa-solid fa-credit-card"></i> Rp 12.000 (BRI)
                </button>
                <button type="button" class="preset-badge-btn" onclick="app.applyAdminFeePreset(14000, 25)">
                  <i class="fa-solid fa-credit-card"></i> Rp 14.000 (BCA Silver)
                </button>
                <button type="button" class="preset-badge-btn" onclick="app.applyAdminFeePreset(16000, 25)">
                  <i class="fa-solid fa-credit-card"></i> Rp 16.000 (BCA Gold)
                </button>
                <button type="button" class="preset-badge-btn" onclick="app.applyAdminFeePreset(12500, 25)">
                  <i class="fa-solid fa-credit-card"></i> Rp 12.500 (Mandiri)
                </button>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 8px;">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 10px;">Biaya Admin per Bulan (Rp)</label>
                <div class="input-icon-wrap">
                  <span class="input-prefix" style="font-size: 10px;">Rp</span>
                  <input type="number" id="accMonthlyAdminFee" class="form-control" placeholder="0" min="0" step="any" value="0">
                </div>
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 10px;">Tgl Potong (1 - 31)</label>
                <input type="number" id="accAdminFeeDate" class="form-control" placeholder="25" min="1" max="31" value="25">
              </div>
            </div>
            <small style="display: block; font-size: 10px; color: var(--text-muted); margin-top: 6px;">
              <i class="fa-solid fa-circle-info"></i> Otomatis dicatat sebagai <strong>Pengeluaran</strong> (Biaya Admin Bank) setiap tanggal yang ditentukan.
            </small>
          </div>
        </div>
        </div><!-- /accSubTypeSection -->

        <div class="form-group">
          <label class="form-label">Saldo Saat Ini (Rp)</label>
          <div class="input-icon-wrap">
            <span class="input-prefix">Rp</span>
            <input type="number" id="accBalance" class="form-control" placeholder="0" required step="any">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Nomor Rekening / No. HP (Opsional)</label>
          <input type="text" id="accNumber" class="form-control" placeholder="9012 9745 4977">
        </div>

        <button type="submit" class="btn-primary" style="margin-top: 16px;">
          <i class="fa-solid fa-check"></i> Simpan Rekening
        </button>
      </form>
    </div>
  </div>

  <!-- Modal 3: Add / Edit Budget -->
  <div id="budgetModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Pasang Batas Anggaran</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <form id="budgetForm">
        <div class="form-group">
          <label class="form-label">Kategori Pengeluaran</label>
          <select id="budgetCategorySelect" class="form-control" required></select>
        </div>

        <div class="form-group">
          <label class="form-label">Batas Anggaran per Bulan (Rp)</label>
          <div class="input-icon-wrap">
            <span class="input-prefix">Rp</span>
            <input type="number" id="budgetLimit" class="form-control" placeholder="1000000" required min="1000"
              step="any">
          </div>
        </div>

        <button type="submit" class="btn-primary" style="margin-top: 16px;">
          <i class="fa-solid fa-check"></i> Simpan Anggaran
        </button>
      </form>
    </div>
  </div>

  <!-- Modal 4: Add / Edit Piggy Bank -->
  <div id="piggyModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="piggyModalTitle" class="modal-title">Target Celengan Impian</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <form id="piggyForm">
        <div class="form-group">
          <label class="form-label">Nama Target Tabungan</label>
          <input type="text" id="piggyName" class="form-control" placeholder="Contoh: Beli Laptop Baru / Dana Darurat"
            required>
        </div>

        <div class="form-group">
          <label class="form-label">Target Nominal yang Ingin Dicapai (Rp)</label>
          <div class="input-icon-wrap">
            <span class="input-prefix">Rp</span>
            <input type="number" id="piggyTarget" class="form-control" placeholder="10000000" required min="1000"
              step="any">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Dana Terkumpul Saat Ini (Rp)</label>
          <div class="input-icon-wrap">
            <span class="input-prefix">Rp</span>
            <input type="number" id="piggyCurrent" class="form-control" placeholder="0" min="0" step="any">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Target Tanggal Tercapai (Opsional)</label>
          <input type="date" id="piggyDate" class="form-control">
        </div>

        <button type="submit" class="btn-primary" style="margin-top: 16px;">
          <i class="fa-solid fa-piggy-bank"></i> Simpan Celengan
        </button>
      </form>
    </div>
  </div>

  <!-- Modal 5: Quick Adjust Piggy Bank (Nabung / Tarik) -->
  <div id="adjustPiggyModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="adjustPiggyTitle" class="modal-title">Nabung ke Celengan</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <form id="adjustPiggyForm">
        <input type="hidden" id="adjustPiggyId">
        <div class="form-group">
          <label class="form-label">Nominal (Rp)</label>
          <div class="input-icon-wrap">
            <span class="input-prefix">Rp</span>
            <input type="number" id="adjustPiggyAmount" class="form-control" placeholder="50000" required min="1"
              step="any">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Aksi</label>
          <div class="type-toggle-group">
            <button type="button" id="btnAdjustDeposit" class="type-toggle-btn active income">
              <i class="fa-solid fa-plus"></i> Tambah Tabungan
            </button>
            <button type="button" id="btnAdjustWithdraw" class="type-toggle-btn">
              <i class="fa-solid fa-minus"></i> Tarik Tabungan
            </button>
          </div>
        </div>

        <button type="submit" class="btn-primary" style="margin-top: 16px;">
          <i class="fa-solid fa-check"></i> Proses Tabungan
        </button>
      </form>
    </div>
  </div>

  <!-- Modal 6: Live Camera & OCR Receipt Scanner -->
  <div id="scannerModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title"><i class="fa-solid fa-receipt"></i> Pemindai Nota Pintar (OCR)</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <!-- Camera Selector (For laptop with IR vs RGB webcam) -->
      <div id="cameraSourceGroup" class="form-group" style="margin-bottom: 12px;">
        <label class="form-label" style="font-size: 12px;"><i class="fa-solid fa-video"></i> Sumber Kamera:</label>
        <select id="cameraSourceSelect" class="form-control" style="font-size: 13px;"
          onchange="receiptScanner.switchCamera('cameraVideo', this.value)">
          <option value="">Pilih Kamera...</option>
        </select>
      </div>

      <!-- Live Viewfinder (Laptop / HTTPS) -->
      <div class="scanner-viewport">
        <video id="cameraVideo" autoplay playsinline muted></video>
        <div class="scanner-frame">
          <div class="scanner-laser"></div>
        </div>
      </div>
      <canvas id="scannerCanvas" style="display: none;"></canvas>

      <!-- Mobile Camera Card (For HTTP Local LAN / High-Resolution Photo) -->
      <div id="mobileCameraNotice"
        style="display: none; text-align: center; padding: 22px 16px; background: var(--bg-card); border: 1px dashed var(--border-glow); border-radius: var(--radius-md); margin-bottom: 16px;">
        <div
          style="width: 52px; height: 52px; border-radius: 50%; background: rgba(16, 185, 129, 0.18); color: var(--primary-light); display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 12px auto;">
          <i class="fa-solid fa-camera"></i>
        </div>
        <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 4px;">Kamera HP Siap Digunakan</h4>
        <p style="font-size: 12px; color: var(--text-muted); line-height: 1.4;">Gunakan kamera bawaan HP untuk foto
          struk beresolusi tinggi dan jernih.</p>
      </div>

      <!-- OCR Scanning Progress -->
      <div id="ocrProgressBox" class="ocr-progress-box">
        <div id="ocrProgressText" style="font-size: 13px; font-weight: 700; color: var(--text-primary);">Sedang
          menganalisis teks nota...</div>
        <div class="progress-bar-bg">
          <div id="ocrProgressBar" class="progress-bar-fill"></div>
        </div>
      </div>

      <!-- Native Camera File Input (Mobile Shutter) -->
      <input type="file" id="cameraDirectInput" accept="image/*" capture="environment" style="display: none;">

      <!-- Scanner Actions -->
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <button type="button" id="capturePhotoBtn" class="btn-primary">
          <i class="fa-solid fa-camera"></i> Ambil Foto & Pindai Nota
        </button>

        <button type="button" id="mobileCameraBtn" class="btn-primary" style="display: none;"
          onclick="document.getElementById('cameraDirectInput').click()">
          <i class="fa-solid fa-camera"></i> Buka Kamera HP & Foto Nota
        </button>

        <label class="btn-secondary" style="cursor: pointer; text-align: center; margin: 0;">
          <i class="fa-solid fa-image"></i> Pilih Foto dari Galeri
          <input type="file" id="uploadReceiptInput" accept="image/*" style="display: none;">
        </label>
      </div>
    </div>
  </div>

  <!-- Modal 7: Authentication (Login / Register / Profile) -->
  <div id="authModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title"><i class="fa-solid fa-user-lock"></i> Akun Pengguna</h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <!-- Auth Tabs -->
      <div class="auth-tabs">
        <button type="button" id="tabLoginBtn" class="auth-tab-btn active">Masuk (Login)</button>
        <button type="button" id="tabRegisterBtn" class="auth-tab-btn">Daftar Akun</button>
      </div>

      <!-- Login Form -->
      <form id="loginForm">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" id="loginEmail" class="form-control" placeholder="nama@email.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" id="loginPassword" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-primary" style="margin-top: 16px;">
          <i class="fa-solid fa-right-to-bracket"></i> Masuk Sekarang
        </button>
      </form>

      <!-- Register Form -->
      <form id="registerForm" style="display: none;">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" id="regName" class="form-control" placeholder="Budi Santoso" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" id="regEmail" class="form-control" placeholder="budi@email.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" id="regPassword" class="form-control" placeholder="Minimal 6 karakter" required
            minlength="6">
        </div>
        <button type="submit" class="btn-primary" style="margin-top: 16px;">
          <i class="fa-solid fa-user-plus"></i> Buat Akun Baru
        </button>
      </form>

      <!-- Profile Logged In View -->
      <div id="profileView" style="display: none; text-align: center; padding: 10px 0;">
        <div
          style="width: 70px; height: 70px; border-radius: 50%; background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; margin: 0 auto 14px auto; box-shadow: 0 4px 18px rgba(16, 185, 129, 0.4);">
          <i class="fa-solid fa-circle-user"></i>
        </div>
        <h4 id="profileUserName" style="font-size: 19px; margin-bottom: 4px; font-weight: 800;">User</h4>
        <p id="profileUserEmail" style="color: var(--text-muted); font-size: 13px; margin-bottom: 12px;">
          email@example.com</p>
        <button type="button" id="logoutBtn" class="btn-secondary btn-danger">
          <i class="fa-solid fa-right-from-bracket"></i> Keluar dari Akun (Logout)
        </button>
      </div>
    </div>
  </div>

  <!-- Modal 8: Simulasi Pendapatan Bunga Harian (SeaBank Style) -->
  <div id="interestSimulationModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 480px;">
      <div class="modal-header">
        <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
          <i class="fa-solid fa-percent" style="color: var(--primary);"></i> Simulasi Pendapatan Bunga Harian
        </h3>
        <button class="close-btn"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <div class="seabank-sim-body">
        <!-- Account Info Pill -->
        <div class="sim-account-header">
          <div class="sim-account-title">
            <i class="fa-solid fa-building-columns" style="color: #10b981;"></i>
            <span id="simAccName">Seabank</span>
          </div>
          <div id="simAccNumber" class="sim-account-num">No. Rek: 9012 9745 4977</div>
        </div>

        <!-- Main Realtime Metrics Card -->
        <div class="sim-stat-card">
          <div class="sim-row">
            <span class="sim-lbl">Saldo Tersedia</span>
            <strong id="simCurrentBalance" class="sim-val-primary">Rp 196.007</strong>
          </div>
          <div class="sim-row">
            <span class="sim-lbl">
              Suku Bunga Saat Ini
              <span id="simTierBadge" class="sim-tier-badge">Top up lagi, dapat 3,5%</span>
            </span>
            <strong id="simActiveRate" class="sim-rate-val" style="color: var(--income);">2,5% p.a.</strong>
          </div>
          <div class="sim-row" style="border-top: 1px dashed var(--border-color); padding-top: 10px; margin-top: 6px;">
            <span class="sim-lbl" style="font-weight: 700; color: var(--text-primary);">Estimasi Pendapatan Bunga
              Harian</span>
            <strong id="simDailyInterest" class="sim-interest-val">Rp 13</strong>
          </div>
        </div>

        <!-- Dynamic Bank Tier Table -->
        <div class="sim-tier-table-wrap">
          <div
            style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">
            Skema Suku Bunga Tabungan
          </div>
          <table class="sim-tier-table">
            <thead>
              <tr>
                <th>Kategori Saldo</th>
                <th style="text-align: right;">Suku Bunga*</th>
              </tr>
            </thead>
            <tbody id="simTierTableBody">
              <!-- Rendered dynamically based on account tier rules -->
            </tbody>
          </table>
          <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
            *Bunga dihitung harian & cair otomatis. Bebas pajak PPh untuk saldo &le; Rp 7.500.000 (PPh 20% jika &gt; Rp
            7.5jt).
          </div>
        </div>

        <!-- Custom Simulation Calculator -->
        <div class="sim-custom-calc-box">
          <div style="font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
            <i class="fa-solid fa-calculator" style="color: var(--primary);"></i> Coba Simulasi Saldo Lain
          </div>
          <div class="input-icon-wrap" style="margin-bottom: 10px;">
            <span class="input-prefix">Rp</span>
            <input type="number" id="customSimInput" class="form-control" placeholder="10000000" step="any" min="0"
              oninput="app.onCustomSimulateInput()">
          </div>

          <div class="sim-breakdown-grid">
            <div class="sim-mini-box">
              <div class="sim-mini-lbl">Harian (1 Hari)</div>
              <div id="simCustomDaily" class="sim-mini-val">Rp 0</div>
            </div>
            <div class="sim-mini-box">
              <div class="sim-mini-lbl">Bulanan (30 Hari)</div>
              <div id="simCustomMonthly" class="sim-mini-val">Rp 0</div>
            </div>
            <div class="sim-mini-box">
              <div class="sim-mini-lbl">Tahunan (365 Hari)</div>
              <div id="simCustomYearly" class="sim-mini-val">Rp 0</div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 18px;">
          <button type="button" id="btnManualAccrueNow" class="btn-primary" onclick="app.manualAccrueCurrentAccount()">
            <i class="fa-solid fa-bolt"></i> Hitung & Cairkan Bunga Sekarang
          </button>
          <button type="button" class="btn-secondary" onclick="app.closeModal('interestSimulationModal')">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

  <!-- Application Scripts -->
  <script src="{{ asset('js/charts.js') }}?v=5.0"></script>
  <script src="{{ asset('js/ocr.js') }}?v=5.0"></script>
  <script src="{{ asset('js/app.js') }}?v=5.0"></script>

</body>

</html>