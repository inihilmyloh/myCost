// js/app.js - Main Application Controller for myCost (Firefly III Inspired Local Architecture)

class MyCostApp {
  constructor() {
    this.activeView = 'dashboard'; // dashboard, accounts, budgets, piggy
    this.currentMonth = new Date().toISOString().substring(0, 7); // YYYY-MM
    this.currentFilter = 'all'; // all, pengeluaran, pemasukan, transfer
    this.searchQuery = '';
    
    this.transactions = [];
    this.accounts = [];
    this.budgets = [];
    this.piggyBanks = [];
    this.categories = { pengeluaran: [], pemasukan: [] };
    
    this.activeFormType = 'pengeluaran';
    this.activeCategoryId = 'Makanan & Minuman';
    this.editingTransactionId = null;
    this.editingAccountId = null;
    this.editingBudgetId = null;
    
    this.receiptImageUrl = null;
    this.receiptBase64 = null;
    this.currentUser = JSON.parse(localStorage.getItem('mycost_user') || 'null');
    this.currentItems = [];

    this.init();
  }

  async init() {
    this.initTheme();
    this.bindEvents();
    this.updateUserUI();
    await this.checkAuth();
    await this.fetchCategories();
    await this.loadData();
    this.checkOnlineStatus();
  }

  // ----------------------------------------------------
  // Theme Toggle
  // ----------------------------------------------------
  initTheme() {
    const savedTheme = localStorage.getItem('mycost_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    this.updateThemeIcon(savedTheme);
  }

  toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('mycost_theme', next);
    this.updateThemeIcon(next);
    if (typeof financialCharts !== 'undefined') {
      financialCharts.updateTheme();
    }
  }

  updateThemeIcon(theme) {
    const btn = document.getElementById('themeToggleBtn');
    if (btn) {
      btn.innerHTML = theme === 'dark' 
        ? '<i class="fa-solid fa-sun"></i>' 
        : '<i class="fa-solid fa-moon"></i>';
    }
  }

  checkOnlineStatus() {
    const badge = document.getElementById('onlineStatusBadge');
    if (badge) {
      badge.innerHTML = '<span class="status-dot"></span> Online (Lokal)';
    }
  }

  // ----------------------------------------------------
  // View Switcher (Dashboard / Accounts / Budgets / Piggy)
  // ----------------------------------------------------
  switchView(viewName) {
    this.activeView = viewName;

    document.querySelectorAll('.tab-nav-btn').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.tab === viewName);
    });

    document.querySelectorAll('.bottom-nav .nav-item').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.tab === viewName);
    });

    document.querySelectorAll('.view-section').forEach((sec) => {
      sec.classList.remove('active');
    });

    const targetSec = document.getElementById('view' + viewName.charAt(0).toUpperCase() + viewName.slice(1));
    if (targetSec) targetSec.classList.add('active');

    // Load data specific to view
    if (viewName === 'accounts') this.fetchAccounts();
    if (viewName === 'budgets') this.fetchBudgets();
    if (viewName === 'piggy') this.fetchPiggyBanks();
    if (viewName === 'dashboard') this.loadData();
  }

  // ----------------------------------------------------
  // Headers Helper (Auth + CSRF)
  // ----------------------------------------------------
  getHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    };
    if (token) headers['X-CSRF-TOKEN'] = token;
    if (this.currentUser?.id) headers['X-User-Id'] = this.currentUser.id;
    return headers;
  }

  getEndpoint(laravelPath) {
    return `/api/${laravelPath}`;
  }

  // ----------------------------------------------------
  // User Authentication Logic
  // ----------------------------------------------------
  async checkAuth() {
    try {
      const res = await fetch(this.getEndpoint('auth/me'), { headers: this.getHeaders() });
      const json = await res.json();
      if (json.authenticated && json.user) {
        this.currentUser = json.user;
        localStorage.setItem('mycost_user', JSON.stringify(this.currentUser));
      }
    } catch (e) {}
    this.updateUserUI();
  }

  updateUserUI() {
    const userPill = document.getElementById('userProfilePill');
    const userNameLabel = document.getElementById('userNameLabel');
    const userAvatar = document.getElementById('userAvatar');
    const guestBanner = document.getElementById('guestBanner');
    const navAuthLabel = document.getElementById('navAuthLabel');

    if (this.currentUser) {
      if (userNameLabel) userNameLabel.textContent = this.currentUser.name || 'User';
      if (userAvatar) userAvatar.textContent = (this.currentUser.name || 'U').charAt(0).toUpperCase();
      if (userPill) userPill.style.display = 'flex';
      if (guestBanner) guestBanner.style.display = 'none';
      if (navAuthLabel) navAuthLabel.textContent = (this.currentUser.name || 'Akun').split(' ')[0];
    } else {
      if (userNameLabel) userNameLabel.textContent = 'Masuk / Daftar';
      if (userAvatar) userAvatar.innerHTML = '<i class="fa-solid fa-user"></i>';
      if (userPill) userPill.style.display = 'flex';
      if (guestBanner) guestBanner.style.display = 'flex';
      if (navAuthLabel) navAuthLabel.textContent = 'Masuk';
    }
  }

  openAuthModal(tab = 'login') {
    this.openModal('authModal');
    this.switchAuthTab(tab);
  }

  switchAuthTab(tab) {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const profileView = document.getElementById('profileView');
    const tabLogin = document.getElementById('tabLoginBtn');
    const tabRegister = document.getElementById('tabRegisterBtn');
    const authTabs = document.querySelector('.auth-tabs');

    if (this.currentUser && tab === 'profile') {
      if (loginForm) loginForm.style.display = 'none';
      if (registerForm) registerForm.style.display = 'none';
      if (profileView) profileView.style.display = 'block';
      if (authTabs) authTabs.style.display = 'none';
      
      const profName = document.getElementById('profileUserName');
      const profEmail = document.getElementById('profileUserEmail');
      if (profName) profName.textContent = this.currentUser.name;
      if (profEmail) profEmail.textContent = this.currentUser.email;
      return;
    }

    if (authTabs) authTabs.style.display = 'grid';
    if (profileView) profileView.style.display = 'none';

    if (tab === 'login') {
      if (loginForm) loginForm.style.display = 'block';
      if (registerForm) registerForm.style.display = 'none';
      if (tabLogin) tabLogin.classList.add('active');
      if (tabRegister) tabRegister.classList.remove('active');
    } else {
      if (loginForm) loginForm.style.display = 'none';
      if (registerForm) registerForm.style.display = 'block';
      if (tabRegister) tabRegister.classList.add('active');
      if (tabLogin) tabLogin.classList.remove('active');
    }
  }

  async handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;

    try {
      const res = await fetch(this.getEndpoint('auth/login'), {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify({ email, password })
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.currentUser = json.user;
        localStorage.setItem('mycost_user', JSON.stringify(this.currentUser));
        this.updateUserUI();
        this.closeModal('authModal');
        this.showToast(`Selamat datang, ${this.currentUser.name}!`, 'success');
        await this.loadData();
      } else {
        this.showToast(json.message || 'Login gagal.', 'error');
      }
    } catch (err) {
      this.showToast('Gagal terhubung ke server: ' + err.message, 'error');
    }
  }

  async handleRegister(e) {
    e.preventDefault();
    const name = document.getElementById('regName').value;
    const email = document.getElementById('regEmail').value;
    const password = document.getElementById('regPassword').value;

    try {
      const res = await fetch(this.getEndpoint('auth/register'), {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify({ name, email, password })
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.currentUser = json.user;
        localStorage.setItem('mycost_user', JSON.stringify(this.currentUser));
        this.updateUserUI();
        this.closeModal('authModal');
        this.showToast('Pendaftaran akun berhasil!', 'success');
        await this.loadData();
      } else {
        this.showToast(json.message || 'Registrasi gagal.', 'error');
      }
    } catch (err) {
      this.showToast('Gagal mendaftar: ' + err.message, 'error');
    }
  }

  async handleLogout() {
    try {
      await fetch(this.getEndpoint('auth/logout'), { method: 'POST', headers: this.getHeaders() });
    } catch (e) {}

    this.currentUser = null;
    localStorage.removeItem('mycost_user');
    this.updateUserUI();
    this.closeModal('authModal');
    this.showToast('Anda telah logout.', 'info');
    await this.loadData();
  }

  // ----------------------------------------------------
  // Bind Events
  // ----------------------------------------------------
  bindEvents() {
    document.getElementById('themeToggleBtn')?.addEventListener('click', () => this.toggleTheme());
    document.getElementById('prevMonthBtn')?.addEventListener('click', () => this.changeMonth(-1));
    document.getElementById('nextMonthBtn')?.addEventListener('click', () => this.changeMonth(1));

    document.getElementById('userProfilePill')?.addEventListener('click', () => {
      if (this.currentUser) this.openAuthModal('profile');
      else this.openAuthModal('login');
    });

    document.getElementById('guestBanner')?.addEventListener('click', () => this.openAuthModal('login'));
    document.getElementById('navAuthBtn')?.addEventListener('click', () => {
      if (this.currentUser) this.openAuthModal('profile');
      else this.openAuthModal('login');
    });

    document.getElementById('logoutBtn')?.addEventListener('click', () => this.handleLogout());
    document.getElementById('tabLoginBtn')?.addEventListener('click', () => this.switchAuthTab('login'));
    document.getElementById('tabRegisterBtn')?.addEventListener('click', () => this.switchAuthTab('register'));
    document.getElementById('loginForm')?.addEventListener('submit', (e) => this.handleLogin(e));
    document.getElementById('registerForm')?.addEventListener('submit', (e) => this.handleRegister(e));

    document.getElementById('searchInput')?.addEventListener('input', (e) => {
      this.searchQuery = e.target.value.toLowerCase();
      this.renderTransactionsList();
    });

    document.querySelectorAll('.filter-chips .chip').forEach((chip) => {
      chip.addEventListener('click', (e) => {
        document.querySelectorAll('.filter-chips .chip').forEach((c) => c.classList.remove('active'));
        e.target.classList.add('active');
        this.currentFilter = e.target.dataset.filter || 'all';
        this.renderTransactionsList();
      });
    });

    document.getElementById('fabAddBtn')?.addEventListener('click', () => this.openTransactionModal());
    document.getElementById('exportDataBtn')?.addEventListener('click', () => this.openExportModal());

    document.querySelectorAll('.modal-overlay .close-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        const modal = e.target.closest('.modal-overlay');
        if (modal) this.closeModal(modal.id);
      });
    });

    document.querySelectorAll('.modal-overlay').forEach((modal) => {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) this.closeModal(modal.id);
      });
    });

    document.getElementById('btnTypeExpense')?.addEventListener('click', () => this.setFormType('pengeluaran'));
    document.getElementById('btnTypeIncome')?.addEventListener('click', () => this.setFormType('pemasukan'));
    document.getElementById('btnTypeTransfer')?.addEventListener('click', () => this.setFormType('transfer'));

    document.getElementById('transactionForm')?.addEventListener('submit', (e) => this.handleTransactionSubmit(e));
    document.getElementById('accountForm')?.addEventListener('submit', (e) => this.handleAccountSubmit(e));
    document.getElementById('accType')?.addEventListener('change', (e) => this.toggleAccountSubTypeVisibility(e.target.value));
    document.getElementById('budgetForm')?.addEventListener('submit', (e) => this.handleBudgetSubmit(e));
    document.getElementById('piggyForm')?.addEventListener('submit', (e) => this.handlePiggySubmit(e));
    document.getElementById('adjustPiggyForm')?.addEventListener('submit', (e) => this.handleAdjustPiggySubmit(e));
    document.getElementById('deleteTransBtn')?.addEventListener('click', () => this.handleDeleteTransaction());

    document.getElementById('capturePhotoBtn')?.addEventListener('click', () => this.captureAndProcessScanner());
    document.getElementById('cameraDirectInput')?.addEventListener('change', (e) => this.handleReceiptUploadInput(e));
    document.getElementById('uploadReceiptInput')?.addEventListener('change', (e) => this.handleReceiptUploadInput(e));
    document.getElementById('addItemBtn')?.addEventListener('click', () => this.addItemRow());
  }

  changeMonth(direction) {
    const [year, month] = this.currentMonth.split('-').map(Number);
    const date = new Date(year, month - 1 + direction, 1);
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    this.currentMonth = `${y}-${m}`;
    this.loadData();
  }

  updateMonthDisplay() {
    const [year, month] = this.currentMonth.split('-').map(Number);
    const date = new Date(year, month - 1, 1);
    const label = date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    const el = document.getElementById('currentMonthLabel');
    if (el) el.innerHTML = `<i class="fa-regular fa-calendar"></i> ${label}`;
  }

  // ----------------------------------------------------
  // Load Global Data (Stats, Accounts, Transactions)
  // ----------------------------------------------------
  async loadData() {
    this.updateMonthDisplay();

    try {
      const statsRes = await fetch(this.getEndpoint(`stats?month=${this.currentMonth}`), { headers: this.getHeaders() });
      if (statsRes.ok) {
        const statsJson = await statsRes.json();
        if (statsJson.status === 'success') {
          this.updateDashboardCards(statsJson.data.summary);
          this.accounts = statsJson.data.accounts || [];
          this.renderAccountSelectOptions();

          if (typeof financialCharts !== 'undefined') {
            financialCharts.renderCategoryChart('categoryChart', statsJson.data.categories);
            financialCharts.renderTrendChart('trendChart', statsJson.data.monthly_trend);
          }
        }
      }

      const transRes = await fetch(this.getEndpoint(`transactions?month=${this.currentMonth}`), { headers: this.getHeaders() });
      if (transRes.ok) {
        const transJson = await transRes.json();
        if (transJson.status === 'success') {
          this.transactions = transJson.data || [];
          this.renderTransactionsList();
        }
      }
    } catch (err) {
      console.error('Failed to load data:', err);
      this.showToast('Gagal memuat data dari server lokal.', 'error');
    }
  }

  async fetchCategories() {
    try {
      const res = await fetch(this.getEndpoint('categories'), { headers: this.getHeaders() });
      const json = await res.json();
      if (json.status === 'success') {
        this.categories = json.data;
      }
    } catch (e) {
      this.categories = {
        pengeluaran: [
          { name: 'Makanan & Minuman', icon: 'fa-utensils', color: '#059669' },
          { name: 'Belanja', icon: 'fa-bag-shopping', color: '#f59e0b' },
          { name: 'Transportasi', icon: 'fa-car', color: '#10b981' },
          { name: 'Tagihan & Utilitas', icon: 'fa-receipt', color: '#f43f5e' },
          { name: 'Hiburan', icon: 'fa-gamepad', color: '#fb7185' },
          { name: 'Kesehatan', icon: 'fa-heart-pulse', color: '#0d9488' },
          { name: 'Lainnya', icon: 'fa-circle-question', color: '#64748b' }
        ],
        pemasukan: [
          { name: 'Gaji', icon: 'fa-money-bill-wave', color: '#059669' },
          { name: 'Freelance', icon: 'fa-laptop-code', color: '#10b981' },
          { name: 'Bisnis / Usaha', icon: 'fa-store', color: '#34d399' },
          { name: 'Lainnya', icon: 'fa-circle-question', color: '#64748b' }
        ]
      };
    }
    this.renderCategoryGrid();
    this.renderBudgetCategoryOptions();
  }

  updateDashboardCards(summary) {
    if (!summary) return;
    const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');

    const balEl = document.getElementById('totalBalanceVal');
    const incEl = document.getElementById('monthIncomeVal');
    const expEl = document.getElementById('monthExpenseVal');

    if (balEl) balEl.textContent = formatRp(summary.net_worth ?? summary.net_balance);
    if (incEl) incEl.textContent = formatRp(summary.month_income);
    if (expEl) expEl.textContent = formatRp(summary.month_expense);
  }

  // ----------------------------------------------------
  // ACCOUNTS MODULE (Firefly III)
  // ----------------------------------------------------
  async fetchAccounts() {
    try {
      const res = await fetch(this.getEndpoint('accounts'), { headers: this.getHeaders() });
      const json = await res.json();
      if (json.status === 'success') {
        this.accounts = json.data.accounts || [];
        this.renderAccountsList();
        this.renderAccountSelectOptions();
      }
    } catch (err) {
      this.showToast('Gagal memuat rekening: ' + err.message, 'error');
    }
  }

  renderAccountsList() {
    const container = document.getElementById('accountsListContainer');
    if (!container) return;

    if (this.accounts.length === 0) {
      container.innerHTML = `<div class="empty-state" style="grid-column: 1/-1;"><i class="fa-solid fa-wallet"></i><p>Belum ada rekening. Klik "+ Tambah Rekening" untuk membuat.</p></div>`;
      return;
    }

    container.innerHTML = this.accounts.map((acc) => {
      const iconMap = {
        cash: 'fa-wallet',
        bank: 'fa-building-columns',
        ewallet: 'fa-mobile-screen-button',
        investment: 'fa-chart-line'
      };
      const icon = iconMap[acc.type] || 'fa-wallet';
      const isSavings = acc.account_sub_type === 'savings' || acc.has_interest;
      const interestRate = acc.interest_rate_default || 2.5;

      return `
        <div class="account-card" onclick="app.openEditAccountModal(${acc.id})">
          <div class="account-top">
            <div class="account-icon" style="background: rgba(16, 185, 129, 0.15); color: ${acc.color || '#10b981'};">
              <i class="fa-solid ${icon}"></i>
            </div>
            <div class="account-meta">
              <h4>${acc.name}</h4>
              <span>${isSavings ? 'Tabungan Berbunga' : (acc.type === 'bank' ? 'Rekening Bank' : acc.type)} ${acc.account_number ? '• ' + acc.account_number : ''}</span>
            </div>
          </div>
          <div>
            <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 2px;">Saldo Saat Ini</div>
            <div class="account-balance-val">Rp ${Number(acc.balance).toLocaleString('id-ID')}</div>
          </div>
          ${acc.monthly_admin_fee > 0 ? `
            <div class="account-admin-fee-tag" title="Biaya admin dipotong otomatis tiap tanggal ${acc.admin_fee_date || 25}">
              <i class="fa-regular fa-credit-card"></i> Admin: Rp ${Number(acc.monthly_admin_fee).toLocaleString('id-ID')}/bln <span style="font-size: 10px; opacity: 0.8;">(Tgl ${acc.admin_fee_date || 25})</span>
            </div>
          ` : ''}
          ${isSavings ? `
            <div class="account-interest-pill" onclick="event.stopPropagation(); app.openInterestSimulationModal(${acc.id})" title="Klik untuk lihat simulasi bunga">
              <div class="interest-pill-info">
                <i class="fa-solid fa-bolt interest-pill-icon"></i>
                <span class="interest-pill-rate">${interestRate}% <span>p.a.</span></span>
                <span class="interest-pill-badge">Harian</span>
              </div>
              <div class="interest-pill-action">
                <span>Simulasi</span>
                <i class="fa-solid fa-chevron-right"></i>
              </div>
            </div>
          ` : ''}
        </div>
      `;
    }).join('');
  }

  renderAccountSelectOptions() {
    const sourceSelect = document.getElementById('transAccountSelect');
    const destSelect = document.getElementById('transDestAccountSelect');

    const optionsHtml = this.accounts.map((acc) => `
      <option value="${acc.id}">${acc.name} (Saldo: Rp ${Number(acc.balance).toLocaleString('id-ID')})</option>
    `).join('');

    if (sourceSelect) sourceSelect.innerHTML = optionsHtml;
    if (destSelect) destSelect.innerHTML = optionsHtml;
  }

  setAccountSubType(subType) {
    this.currentAccountSubType = subType;
    const btnReg = document.getElementById('btnAccSubRegular');
    const btnSav = document.getElementById('btnAccSubSavings');
    const settingsBox = document.getElementById('accInterestSettingsBox');

    if (btnReg) btnReg.classList.toggle('active', subType === 'regular');
    if (btnSav) btnSav.classList.toggle('active', subType === 'savings');
    if (settingsBox) settingsBox.style.display = subType === 'savings' ? 'block' : 'none';
  }

  toggleAccountSubTypeVisibility(accType) {
    const section = document.getElementById('accSubTypeSection');
    if (!section) return;
    const showSubType = (accType === 'bank' || accType === 'investment');
    section.style.display = showSubType ? '' : 'none';
    if (!showSubType) {
      this.setAccountSubType('regular');
    }
  }

  // ----------------------------------------------------
  // DYNAMIC INTEREST TIERS BUILDER
  // ----------------------------------------------------
  renderInterestTierRows() {
    const container = document.getElementById('interestTierRowsList');
    if (!container) return;

    if (!this.currentInterestTiers || this.currentInterestTiers.length === 0) {
      this.currentInterestTiers = [{ min: 0, rate: 2.5 }];
    }

    container.innerHTML = this.currentInterestTiers.map((tier, idx) => `
      <div class="tier-row-item" data-index="${idx}">
        <div class="tier-row-header">
          <span class="tier-row-badge">
            <i class="fa-solid fa-layer-group"></i> Tier ${idx + 1} ${idx === 0 ? '(Dasar)' : ''}
          </span>
          ${this.currentInterestTiers.length > 1 ? `
            <button type="button" class="tier-row-remove-btn" onclick="app.removeInterestTierRow(${idx})" title="Hapus Tier Ini">
              <i class="fa-solid fa-trash-can"></i> Hapus
            </button>
          ` : ''}
        </div>
        <div class="tier-row-inputs">
          <div class="form-group tier-input-group">
            <label class="form-label">Saldo Minimum (Rp)</label>
            <div class="input-icon-wrap">
              <span class="input-prefix">Rp</span>
              <input type="number" class="form-control tier-min-input" value="${tier.min}" placeholder="0" min="0" step="any" oninput="app.syncTierDataFromDom()">
            </div>
          </div>
          <div class="form-group tier-input-group">
            <label class="form-label">Suku Bunga (% p.a.)</label>
            <div class="input-icon-wrap">
              <input type="number" class="form-control tier-rate-input" value="${tier.rate}" placeholder="2.5" min="0" max="100" step="0.01" oninput="app.syncTierDataFromDom()">
              <span class="input-prefix" style="left: auto; right: 12px; pointer-events: none;">%</span>
            </div>
          </div>
        </div>
      </div>
    `).join('');
  }

  syncTierDataFromDom() {
    const items = document.querySelectorAll('#interestTierRowsList .tier-row-item');
    const tiers = [];
    items.forEach((item) => {
      const minInput = item.querySelector('.tier-min-input');
      const rateInput = item.querySelector('.tier-rate-input');
      const min = parseFloat(minInput?.value) || 0;
      const rate = parseFloat(rateInput?.value) || 0;
      tiers.push({ min, rate });
    });
    this.currentInterestTiers = tiers;
  }

  addInterestTierRow(customTier = null) {
    this.syncTierDataFromDom();
    if (customTier) {
      this.currentInterestTiers.push(customTier);
    } else {
      const lastTier = this.currentInterestTiers[this.currentInterestTiers.length - 1];
      const nextMin = lastTier ? (lastTier.min > 0 ? lastTier.min * 2 : 100000000) : 100000000;
      const nextRate = lastTier ? (lastTier.rate + 1.0) : 3.5;
      this.currentInterestTiers.push({ min: nextMin, rate: nextRate });
    }
    this.renderInterestTierRows();
  }

  removeInterestTierRow(idx) {
    this.syncTierDataFromDom();
    if (this.currentInterestTiers.length <= 1) return;
    this.currentInterestTiers.splice(idx, 1);
    this.renderInterestTierRows();
  }

  applyInterestPreset(presetName) {
    this.setAccountSubType('savings');
    if (presetName === 'seabank') {
      document.getElementById('accName').value = 'Seabank';
      document.getElementById('accType').value = 'bank';
      this.currentInterestTiers = [
        { min: 0, rate: 2.5 },
        { min: 150000000, rate: 3.5 }
      ];
      this.renderInterestTierRows();
      this.showToast('Preset SeaBank (2 Tier: 2,5% & 3,5% p.a.) diterapkan', 'info');
    } else if (presetName === 'jago') {
      document.getElementById('accName').value = 'Bank Jago';
      document.getElementById('accType').value = 'bank';
      this.currentInterestTiers = [
        { min: 0, rate: 3.75 }
      ];
      this.renderInterestTierRows();
      this.showToast('Preset Bank Jago (Flat 3,75% p.a.) diterapkan', 'info');
    } else if (presetName === 'neobank') {
      document.getElementById('accName').value = 'NeoBank / BNC';
      document.getElementById('accType').value = 'bank';
      this.currentInterestTiers = [
        { min: 0, rate: 2.5 },
        { min: 50000000, rate: 4.0 },
        { min: 150000000, rate: 5.0 }
      ];
      this.renderInterestTierRows();
      this.showToast('Preset NeoBank (3 Tier: 2,5%, 4,0% & 5,0% p.a.) diterapkan', 'info');
    } else if (presetName === 'custom') {
      this.currentInterestTiers = [
        { min: 0, rate: 2.5 }
      ];
      this.renderInterestTierRows();
      this.showToast('Silakan atur atau tambah tier suku bunga sesuai kebutuhan Anda', 'info');
    }
  }

  applyAdminFeePreset(amount, date = 25) {
    const feeInput = document.getElementById('accMonthlyAdminFee');
    const dateInput = document.getElementById('accAdminFeeDate');
    if (feeInput) feeInput.value = amount;
    if (dateInput) dateInput.value = date;
    if (amount === 0) {
      this.showToast('Biaya admin bulanan diatur ke Rp 0 (Bebas Biaya Admin)', 'info');
    } else {
      this.showToast(`Biaya admin bulanan diatur ke Rp ${Number(amount).toLocaleString('id-ID')} (Tgl ${date})`, 'info');
    }
  }

  openAccountModal() {
    this.editingAccountId = null;
    document.getElementById('accountModalTitle').textContent = 'Tambah Rekening / Dompet';
    document.getElementById('accName').value = '';
    document.getElementById('accType').value = 'bank';
    this.toggleAccountSubTypeVisibility('bank');
    document.getElementById('accBalance').value = '0';
    document.getElementById('accNumber').value = '';
    const feeInput = document.getElementById('accMonthlyAdminFee');
    const dateInput = document.getElementById('accAdminFeeDate');
    if (feeInput) feeInput.value = '0';
    if (dateInput) dateInput.value = '25';
    this.currentInterestTiers = [
      { min: 0, rate: 2.5 },
      { min: 150000000, rate: 3.5 }
    ];
    this.renderInterestTierRows();
    this.setAccountSubType('regular');
    this.openModal('accountModal');
  }

  openEditAccountModal(id) {
    const acc = this.accounts.find((a) => a.id === id);
    if (!acc) return;

    this.editingAccountId = id;
    document.getElementById('accountModalTitle').textContent = 'Edit Rekening / Dompet';
    document.getElementById('accName').value = acc.name;
    document.getElementById('accType').value = acc.type;
    this.toggleAccountSubTypeVisibility(acc.type);
    document.getElementById('accBalance').value = acc.balance;
    document.getElementById('accNumber').value = acc.account_number || '';
    const feeInput = document.getElementById('accMonthlyAdminFee');
    const dateInput = document.getElementById('accAdminFeeDate');
    if (feeInput) feeInput.value = acc.monthly_admin_fee || 0;
    if (dateInput) dateInput.value = acc.admin_fee_date || 25;
    
    const isSavings = acc.account_sub_type === 'savings' || acc.has_interest;
    this.setAccountSubType(isSavings ? 'savings' : 'regular');

    if (acc.interest_tiers && Array.isArray(acc.interest_tiers) && acc.interest_tiers.length > 0) {
      this.currentInterestTiers = JSON.parse(JSON.stringify(acc.interest_tiers));
    } else {
      const defRate = acc.interest_rate_default || 2.5;
      const tierRate = acc.interest_rate_tier || defRate;
      const threshold = acc.interest_tier_threshold || 0;
      if (threshold > 0 && tierRate !== defRate) {
        this.currentInterestTiers = [
          { min: 0, rate: defRate },
          { min: threshold, rate: tierRate }
        ];
      } else {
        this.currentInterestTiers = [
          { min: 0, rate: defRate }
        ];
      }
    }
    this.renderInterestTierRows();

    this.openModal('accountModal');
  }

  async handleAccountSubmit(e) {
    e.preventDefault();
    const isSavings = (this.currentAccountSubType === 'savings');
    this.syncTierDataFromDom();

    const payload = {
      name: document.getElementById('accName').value,
      type: document.getElementById('accType').value,
      account_sub_type: isSavings ? 'savings' : 'regular',
      has_interest: isSavings,
      interest_tiers: isSavings ? this.currentInterestTiers : null,
      interest_period: 'daily',
      monthly_admin_fee: parseFloat(document.getElementById('accMonthlyAdminFee')?.value) || 0,
      admin_fee_date: parseInt(document.getElementById('accAdminFeeDate')?.value) || 25,
      balance: parseFloat(document.getElementById('accBalance').value) || 0,
      account_number: document.getElementById('accNumber').value
    };

    try {
      const url = this.editingAccountId 
        ? this.getEndpoint(`accounts/${this.editingAccountId}`)
        : this.getEndpoint('accounts');
      const method = this.editingAccountId ? 'PUT' : 'POST';

      const res = await fetch(url, {
        method,
        headers: this.getHeaders(),
        body: JSON.stringify(payload)
      });
      const json = await res.json();

      if (json.status === 'success') {
        this.showToast(json.message, 'success');
        this.closeModal('accountModal');
        await this.fetchAccounts();
        await this.loadData();
      } else {
        this.showToast(json.message || 'Gagal menyimpan rekening', 'error');
      }
    } catch (err) {
      this.showToast('Gagal: ' + err.message, 'error');
    }
  }

  // ----------------------------------------------------
  // INTEREST SIMULATION MODULE (SeaBank Style)
  // ----------------------------------------------------
  async openInterestSimulationModal(accountId) {
    this.activeSimulationAccountId = accountId;
    const account = this.accounts.find((a) => a.id === accountId);
    if (!account) return;

    document.getElementById('simAccName').textContent = account.name;
    document.getElementById('simAccNumber').textContent = account.account_number ? `No. Rek: ${account.account_number}` : account.type;
    document.getElementById('customSimInput').value = Math.round(account.balance || 1000000);

    this.openModal('interestSimulationModal');
    await this.fetchAndRenderInterestSimulation(account.balance);
  }

  async fetchAndRenderInterestSimulation(balance) {
    if (!this.activeSimulationAccountId) return;
    try {
      const res = await fetch(this.getEndpoint(`accounts/${this.activeSimulationAccountId}/simulate-interest?balance=${balance}`), {
        headers: this.getHeaders()
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.renderInterestSimulationData(json.data.simulation);
      }
    } catch (e) {
      console.error('Error simulating interest:', e);
    }
  }

  renderInterestSimulationData(sim) {
    if (!sim) return;

    const curBalEl = document.getElementById('simCurrentBalance');
    const actRateEl = document.getElementById('simActiveRate');
    const dailyIntEl = document.getElementById('simDailyInterest');
    const badgeEl = document.getElementById('simTierBadge');
    const tableBody = document.getElementById('simTierTableBody');

    if (curBalEl) curBalEl.textContent = 'Rp ' + Number(sim.balance).toLocaleString('id-ID');
    if (actRateEl) actRateEl.textContent = `${sim.active_rate}% p.a.`;
    if (dailyIntEl) dailyIntEl.textContent = sim.daily.net_formatted;

    const nextTierBanner = document.getElementById('simNextTierBanner');
    const nextTierText = document.getElementById('simNextTierText');

    if (sim.next_tier_goal) {
      if (nextTierBanner) nextTierBanner.style.display = 'flex';
      if (nextTierText) {
        nextTierText.innerHTML = `Top up <strong>${sim.next_tier_goal.amount_needed_formatted}</strong> lagi untuk dapat bunga <strong>${sim.next_tier_goal.next_rate}% p.a.</strong>`;
      }
    } else {
      if (nextTierBanner) nextTierBanner.style.display = 'none';
    }

    if (tableBody && sim.tiers) {
      tableBody.innerHTML = sim.tiers.map((t) => `
        <div class="sim-tier-row-card ${t.is_active ? 'active-tier-card' : ''}">
          <div class="tier-card-left">
            <span class="tier-card-badge">Tier ${t.tier_number}</span>
            <div class="tier-card-label">${t.label}</div>
          </div>
          <div class="tier-card-right">
            <div class="tier-card-rate">${t.rate}</div>
            ${t.is_active ? `<span class="tier-active-pill"><i class="fa-solid fa-check"></i> Aktif</span>` : ''}
          </div>
        </div>
      `).join('');
    }

    // Mini simulation results
    const customDailyEl = document.getElementById('simCustomDaily');
    const customMonthlyEl = document.getElementById('simCustomMonthly');
    const customYearlyEl = document.getElementById('simCustomYearly');

    if (customDailyEl) customDailyEl.textContent = sim.daily.net_formatted;
    if (customMonthlyEl) customMonthlyEl.textContent = sim.monthly.net_formatted;
    if (customYearlyEl) customYearlyEl.textContent = sim.yearly.net_formatted;
  }

  async onCustomSimulateInput() {
    const val = parseFloat(document.getElementById('customSimInput')?.value) || 0;
    await this.fetchAndRenderInterestSimulation(val);
  }

  async manualAccrueCurrentAccount() {
    if (!this.activeSimulationAccountId) return;
    try {
      const res = await fetch(this.getEndpoint(`accounts/${this.activeSimulationAccountId}/accrue-interest`), {
        method: 'POST',
        headers: this.getHeaders()
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.showToast(json.message, 'success');
        await this.fetchAccounts();
        await this.loadData();
        const updatedAcc = this.accounts.find((a) => a.id === this.activeSimulationAccountId);
        if (updatedAcc) {
          await this.fetchAndRenderInterestSimulation(updatedAcc.balance);
        }
      } else {
        this.showToast(json.message || 'Gagal mencairkan bunga', 'error');
      }
    } catch (err) {
      this.showToast('Gagal: ' + err.message, 'error');
    }
  }

  // ----------------------------------------------------
  // BUDGETS MODULE (Firefly III)
  // ----------------------------------------------------
  async fetchBudgets() {
    try {
      const res = await fetch(this.getEndpoint(`budgets?month=${this.currentMonth}`), { headers: this.getHeaders() });
      const json = await res.json();
      if (json.status === 'success') {
        this.budgets = json.data.budgets || [];
        this.renderBudgetsList(json.data.summary);
      }
    } catch (err) {
      this.showToast('Gagal memuat anggaran: ' + err.message, 'error');
    }
  }

  renderBudgetsList(summary) {
    const container = document.getElementById('budgetsListContainer');
    const summaryBox = document.getElementById('budgetsSummaryBox');
    if (!container) return;

    if (summary && summaryBox) {
      summaryBox.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; padding: 14px 16px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
          <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Total Anggaran</div>
            <div style="font-size: 16px; font-weight: 800; color: var(--primary-light);">Rp ${Number(summary.total_budget).toLocaleString('id-ID')}</div>
          </div>
          <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Total Terpakai</div>
            <div style="font-size: 16px; font-weight: 800; color: ${summary.total_spent > summary.total_budget ? 'var(--expense)' : 'var(--text-primary)'};">Rp ${Number(summary.total_spent).toLocaleString('id-ID')} (${summary.overall_percentage}%)</div>
          </div>
          <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Sisa Anggaran</div>
            <div style="font-size: 16px; font-weight: 800; color: var(--income);">Rp ${Number(summary.total_remaining).toLocaleString('id-ID')}</div>
          </div>
        </div>
      `;
    }

    if (this.budgets.length === 0) {
      container.innerHTML = `<div class="empty-state"><i class="fa-solid fa-scale-balanced"></i><p>Belum ada batas anggaran bulan ini. Klik "+ Pasang Anggaran".</p></div>`;
      return;
    }

    container.innerHTML = this.budgets.map((b) => {
      let barClass = '';
      if (b.percentage > 90) barClass = 'danger';
      else if (b.percentage > 75) barClass = 'warning';

      return `
        <div class="budget-card" onclick="app.openEditBudgetModal(${b.id})" style="cursor: pointer;">
          <div class="budget-card-header">
            <div class="budget-cat-name">
              <i class="fa-solid fa-tag" style="color: var(--primary);"></i>
              ${b.category}
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <div class="budget-spent-txt">
                <strong>Rp ${Number(b.total_spent).toLocaleString('id-ID')}</strong> / Rp ${Number(b.amount_limit).toLocaleString('id-ID')}
              </div>
              <div class="budget-actions-wrap" onclick="event.stopPropagation()">
                <button type="button" class="budget-action-btn" title="Edit Anggaran" onclick="app.openEditBudgetModal(${b.id})">
                  <i class="fa-solid fa-pen-to-square"></i> Edit
                </button>
                <button type="button" class="budget-action-btn danger" title="Hapus Anggaran" onclick="app.handleDeleteBudget(${b.id})">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="budget-progress-track">
            <div class="budget-progress-bar ${barClass}" style="width: ${Math.min(100, b.percentage)}%;"></div>
          </div>
          <div class="budget-footer">
            <span>${b.percentage}% Terpakai ${b.is_over_budget ? '<strong style="color: var(--expense);">(Melebihi Limit!)</strong>' : ''}</span>
            <span>Sisa: <strong>Rp ${Number(b.remaining).toLocaleString('id-ID')}</strong></span>
          </div>
        </div>
      `;
    }).join('');
  }

  renderBudgetCategoryOptions() {
    const sel = document.getElementById('budgetCategorySelect');
    if (!sel) return;
    const list = this.categories.pengeluaran || [];
    sel.innerHTML = list.map((c) => `<option value="${c.name}">${c.name}</option>`).join('');
  }

  openBudgetModal() {
    this.editingBudgetId = null;
    this.renderBudgetCategoryOptions();

    const title = document.getElementById('budgetModalTitle');
    if (title) title.textContent = 'Pasang Batas Anggaran';

    const catSel = document.getElementById('budgetCategorySelect');
    if (catSel) {
      catSel.disabled = false;
      if (catSel.options.length > 0) catSel.selectedIndex = 0;
    }

    const limitInp = document.getElementById('budgetLimit');
    if (limitInp) limitInp.value = '';

    const delBtn = document.getElementById('deleteBudgetBtn');
    if (delBtn) delBtn.style.display = 'none';

    this.openModal('budgetModal');
  }

  openEditBudgetModal(id) {
    const b = this.budgets.find((item) => item.id === id);
    if (!b) return;

    this.editingBudgetId = id;
    this.renderBudgetCategoryOptions();

    const title = document.getElementById('budgetModalTitle');
    if (title) title.textContent = `Edit Anggaran "${b.category}"`;

    const catSel = document.getElementById('budgetCategorySelect');
    if (catSel) {
      catSel.value = b.category;
      catSel.disabled = false; // Category can be changed freely!
      catSel.onchange = (e) => {
        if (title) title.textContent = `Edit Anggaran "${e.target.value}"`;
      };
    }

    const limitInp = document.getElementById('budgetLimit');
    if (limitInp) limitInp.value = b.amount_limit;

    const delBtn = document.getElementById('deleteBudgetBtn');
    if (delBtn) delBtn.style.display = 'inline-block';

    this.openModal('budgetModal');
  }

  async handleDeleteBudget(id = null) {
    const targetId = id || this.editingBudgetId;
    if (!targetId) return;

    const b = this.budgets.find((item) => item.id === targetId);
    const catName = b ? `kategori "${b.category}"` : 'ini';

    if (!confirm(`Apakah Anda yakin ingin menghapus anggaran ${catName}?`)) return;

    try {
      const res = await fetch(this.getEndpoint(`budgets/${targetId}`), {
        method: 'DELETE',
        headers: this.getHeaders()
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.showToast(json.message || 'Anggaran berhasil dihapus', 'success');
        this.closeModal('budgetModal');
        await this.fetchBudgets();
      } else {
        this.showToast(json.message || 'Gagal menghapus anggaran', 'error');
      }
    } catch (err) {
      this.showToast('Gagal menghapus: ' + err.message, 'error');
    }
  }

  async handleBudgetSubmit(e) {
    e.preventDefault();
    const catSel = document.getElementById('budgetCategorySelect');
    const category = catSel ? catSel.value : '';
    const limitVal = parseFloat(document.getElementById('budgetLimit').value) || 0;

    if (!category) {
      this.showToast('Silakan pilih kategori pengeluaran', 'warning');
      return;
    }

    if (limitVal < 1000) {
      this.showToast('Batas anggaran minimal Rp 1.000', 'warning');
      return;
    }

    const payload = {
      id: this.editingBudgetId || null,
      category: category,
      amount_limit: limitVal,
      month_year: this.currentMonth
    };

    try {
      const res = await fetch(this.getEndpoint('budgets'), {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.showToast(json.message, 'success');
        this.closeModal('budgetModal');
        await this.fetchBudgets();
      } else {
        this.showToast(json.message || 'Gagal menyimpan anggaran', 'error');
      }
    } catch (err) {
      this.showToast('Gagal: ' + err.message, 'error');
    }
  }

  // ----------------------------------------------------
  // PIGGY BANKS MODULE (Firefly III)
  // ----------------------------------------------------
  async fetchPiggyBanks() {
    try {
      const res = await fetch(this.getEndpoint('piggy-banks'), { headers: this.getHeaders() });
      const json = await res.json();
      if (json.status === 'success') {
        this.piggyBanks = json.data.piggy_banks || [];
        this.renderPiggyList();
      }
    } catch (err) {
      this.showToast('Gagal memuat celengan: ' + err.message, 'error');
    }
  }

  renderPiggyList() {
    const container = document.getElementById('piggyListContainer');
    if (!container) return;

    if (this.piggyBanks.length === 0) {
      container.innerHTML = `<div class="empty-state" style="grid-column: 1/-1;"><i class="fa-solid fa-piggy-bank"></i><p>Belum ada target celengan. Klik "+ Buat Target Celengan".</p></div>`;
      return;
    }

    container.innerHTML = this.piggyBanks.map((g) => `
      <div class="piggy-card">
        <div class="piggy-card-header">
          <div class="piggy-title">${g.name}</div>
          <span class="receipt-tag"><i class="fa-solid fa-bullseye"></i> ${g.percentage}%</span>
        </div>

        <div class="piggy-amount-row">
          <span style="color: var(--text-muted);">Terkumpul:</span>
          <div class="piggy-current">Rp ${Number(g.current_amount).toLocaleString('id-ID')}</div>
        </div>
        <div style="font-size: 12px; color: var(--text-muted);">
          Target: <strong>Rp ${Number(g.target_amount).toLocaleString('id-ID')}</strong> (Kurang: Rp ${Number(g.remaining).toLocaleString('id-ID')})
        </div>

        <div class="budget-progress-track">
          <div class="budget-progress-bar" style="width: ${g.percentage}%;"></div>
        </div>

        <div class="piggy-actions">
          <button type="button" class="piggy-btn primary" onclick="app.openAdjustPiggyModal(${g.id}, 'deposit')">
            <i class="fa-solid fa-plus"></i> Nabung
          </button>
          <button type="button" class="piggy-btn" onclick="app.openAdjustPiggyModal(${g.id}, 'withdraw')">
            <i class="fa-solid fa-minus"></i> Tarik
          </button>
        </div>
      </div>
    `).join('');
  }

  openPiggyModal() {
    document.getElementById('piggyName').value = '';
    document.getElementById('piggyTarget').value = '';
    document.getElementById('piggyCurrent').value = '0';
    this.openModal('piggyModal');
  }

  async handlePiggySubmit(e) {
    e.preventDefault();
    const payload = {
      name: document.getElementById('piggyName').value,
      target_amount: parseFloat(document.getElementById('piggyTarget').value) || 0,
      current_amount: parseFloat(document.getElementById('piggyCurrent').value) || 0,
      target_date: document.getElementById('piggyDate').value || null
    };

    try {
      const res = await fetch(this.getEndpoint('piggy-banks'), {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.showToast(json.message, 'success');
        this.closeModal('piggyModal');
        await this.fetchPiggyBanks();
      } else {
        this.showToast(json.message || 'Gagal menyimpan celengan', 'error');
      }
    } catch (err) {
      this.showToast('Gagal: ' + err.message, 'error');
    }
  }

  openAdjustPiggyModal(id, mode = 'deposit') {
    const goal = this.piggyBanks.find((g) => g.id === id);
    if (!goal) return;

    document.getElementById('adjustPiggyId').value = id;
    document.getElementById('adjustPiggyAmount').value = '';
    document.getElementById('adjustPiggyTitle').textContent = mode === 'deposit' ? `Nabung ke "${goal.name}"` : `Tarik dari "${goal.name}"`;

    const btnDep = document.getElementById('btnAdjustDeposit');
    const btnWit = document.getElementById('btnAdjustWithdraw');
    if (mode === 'deposit') {
      btnDep.classList.add('active', 'income');
      btnWit.classList.remove('active', 'income');
    } else {
      btnWit.classList.add('active', 'income');
      btnDep.classList.remove('active', 'income');
    }

    this.openModal('adjustPiggyModal');
  }

  async handleAdjustPiggySubmit(e) {
    e.preventDefault();
    const id = document.getElementById('adjustPiggyId').value;
    const rawAmt = parseFloat(document.getElementById('adjustPiggyAmount').value) || 0;
    const isDeposit = document.getElementById('btnAdjustDeposit').classList.contains('active');
    const amount = isDeposit ? rawAmt : -rawAmt;

    try {
      const res = await fetch(this.getEndpoint(`piggy-banks/${id}/adjust`), {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify({ amount })
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.showToast(json.message, 'success');
        this.closeModal('adjustPiggyModal');
        await this.fetchPiggyBanks();
      } else {
        this.showToast(json.message || 'Gagal memproses tabungan', 'error');
      }
    } catch (err) {
      this.showToast('Gagal: ' + err.message, 'error');
    }
  }

  // ----------------------------------------------------
  // TRANSACTIONS & ITEM ROWS
  // ----------------------------------------------------
  renderTransactionsList() {
    const container = document.getElementById('transactionsList');
    if (!container) return;

    let filtered = this.transactions;

    if (this.currentFilter !== 'all') {
      filtered = filtered.filter((t) => t.type === this.currentFilter);
    }

    if (this.searchQuery) {
      filtered = filtered.filter((t) => 
        (t.notes || '').toLowerCase().includes(this.searchQuery) ||
        (t.category || '').toLowerCase().includes(this.searchQuery)
      );
    }

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-receipt"></i>
          <p>Belum ada transaksi di bulan ini.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = filtered.map((item) => {
      let amtClass = 'expense';
      let sign = '-';
      if (item.type === 'pemasukan') {
        amtClass = 'income';
        sign = '+';
      } else if (item.type === 'transfer') {
        amtClass = 'income';
        sign = '⇄';
      }

      const dateFormatted = new Date(item.transaction_date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
      });
      const itemCount = item.items ? item.items.length : 0;
      const accName = item.account ? item.account.name : '';

      return `
        <div class="trans-item" onclick="app.openEditTransactionModal(${item.id})">
          <div class="trans-left">
            <div class="trans-icon-box ${amtClass}">
              <i class="fa-solid ${this.getCategoryIcon(item.category, item.type)}"></i>
            </div>
            <div class="trans-details">
              <div class="trans-cat">
                ${item.category}
                ${accName ? `<span class="receipt-tag"><i class="fa-solid fa-wallet"></i> ${accName}</span>` : ''}
                ${itemCount > 0 ? `<span class="receipt-tag"><i class="fa-solid fa-list-check"></i> ${itemCount} Item</span>` : ''}
              </div>
              <div class="trans-notes">${item.notes || '-'}</div>
              <div class="trans-date">${dateFormatted}</div>
            </div>
          </div>
          <div class="trans-right">
            <div class="trans-amount ${amtClass}">${sign} Rp ${Number(item.amount).toLocaleString('id-ID')}</div>
            ${item.admin_fee > 0 ? `<span style="font-size: 11px; color: var(--text-muted);">+ Admin: Rp ${Number(item.admin_fee).toLocaleString('id-ID')}</span>` : ''}
            ${item.discount > 0 ? `<span style="font-size: 11px; color: var(--income);">Diskon: Rp ${Number(item.discount).toLocaleString('id-ID')}</span>` : ''}
            ${item.receipt_image_url ? `<span class="receipt-tag"><i class="fa-solid fa-image"></i> Nota</span>` : ''}
          </div>
        </div>
      `;
    }).join('');
  }

  getCategoryIcon(catName, type) {
    if (type === 'transfer') return 'fa-money-bill-transfer';
    const lower = (catName || '').toLowerCase();
    if (lower.includes('bunga')) return 'fa-sack-dollar';
    if (lower.includes('kembali') || lower.includes('refund')) return 'fa-rotate-left';
    if (lower.includes('invest')) return 'fa-chart-line';
    const list = this.categories[type] || [];
    const found = list.find((c) => c.name.toLowerCase() === lower);
    return found ? found.icon : 'fa-receipt';
  }

  setFormType(type) {
    this.activeFormType = type;
    const btnExp = document.getElementById('btnTypeExpense');
    const btnInc = document.getElementById('btnTypeIncome');
    const btnTra = document.getElementById('btnTypeTransfer');
    const destGroup = document.getElementById('destAccountGroup');
    const catGroup = document.getElementById('categoryGroup');
    const itemGroup = document.getElementById('itemizedSection');
    const adminFeeGroup = document.getElementById('adminFeeGroup');

    btnExp.classList.remove('active', 'expense');
    btnInc.classList.remove('active', 'income');
    btnTra.classList.remove('active', 'transfer');

    if (type === 'pengeluaran') {
      btnExp.classList.add('active', 'expense');
      if (destGroup) destGroup.style.display = 'none';
      if (catGroup) catGroup.style.display = 'block';
      if (itemGroup) itemGroup.style.display = 'block';
      if (adminFeeGroup) adminFeeGroup.style.display = 'block';
    } else if (type === 'pemasukan') {
      btnInc.classList.add('active', 'income');
      if (destGroup) destGroup.style.display = 'none';
      if (catGroup) catGroup.style.display = 'block';
      if (itemGroup) itemGroup.style.display = 'block';
      if (adminFeeGroup) adminFeeGroup.style.display = 'none';
    } else if (type === 'transfer') {
      btnTra.classList.add('active', 'transfer');
      if (destGroup) destGroup.style.display = 'block';
      if (catGroup) catGroup.style.display = 'none';
      if (itemGroup) itemGroup.style.display = 'none';
      if (adminFeeGroup) adminFeeGroup.style.display = 'block';
    }

    this.renderCategoryGrid();
    this.updateDeductionSummary();
  }

  renderCategoryGrid() {
    const container = document.getElementById('categoryGrid');
    if (!container) return;

    const list = this.categories[this.activeFormType] || [];
    if (!list.some((c) => c.name === this.activeCategoryId)) {
      this.activeCategoryId = list[0]?.name || 'Lainnya';
    }

    container.innerHTML = list.map((cat) => `
      <div class="cat-btn ${cat.name === this.activeCategoryId ? 'selected' : ''}" onclick="app.selectCategory('${cat.name}')">
        <i class="fa-solid ${cat.icon}"></i>
        <span>${cat.name}</span>
      </div>
    `).join('');
  }

  selectCategory(name) {
    this.activeCategoryId = name;
    this.renderCategoryGrid();
  }

  // ----------------------------------------------------
  // ADMIN FEE MANAGEMENT
  // ----------------------------------------------------
  setAdminFee(val) {
    const feeInput = document.getElementById('transAdminFee');
    if (feeInput) feeInput.value = val;

    document.querySelectorAll('.admin-fee-chips-wrap .fee-chip').forEach((chip) => {
      chip.classList.toggle('active', parseFloat(chip.dataset.fee) === val);
    });

    this.updateDeductionSummary();
  }

  onAdminFeeInput() {
    const feeVal = parseFloat(document.getElementById('transAdminFee')?.value) || 0;
    document.querySelectorAll('.admin-fee-chips-wrap .fee-chip').forEach((chip) => {
      chip.classList.toggle('active', parseFloat(chip.dataset.fee) === feeVal);
    });
    this.updateDeductionSummary();
  }

  updateDeductionSummary() {
    const amount = parseFloat(document.getElementById('transAmount')?.value) || 0;
    const adminFee = parseFloat(document.getElementById('transAdminFee')?.value) || 0;
    const totalDeduct = amount + adminFee;

    const helperText = document.getElementById('adminFeeHelperText');
    const baseAmtEl = document.getElementById('summaryBaseAmount');
    const feeAmtEl = document.getElementById('summaryAdminFee');
    const totalEl = document.getElementById('transTotalDeductionVal');

    if (helperText) {
      helperText.textContent = adminFee > 0 ? `+ Rp ${Number(adminFee).toLocaleString('id-ID')}` : 'Rp 0 (Gratis)';
    }
    if (baseAmtEl) baseAmtEl.textContent = 'Rp ' + Number(amount).toLocaleString('id-ID');
    if (feeAmtEl) feeAmtEl.textContent = 'Rp ' + Number(adminFee).toLocaleString('id-ID');
    if (totalEl) totalEl.textContent = 'Rp ' + Number(totalDeduct).toLocaleString('id-ID');
  }

  // ----------------------------------------------------
  // ITEM ROWS
  // ----------------------------------------------------
  renderItemRows() {
    const tbody = document.getElementById('itemsTableBody');
    if (!tbody) return;

    if (this.currentItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 10px;">Belum ada rincian barang. Klik "+ Tambah Item" jika ingin merinci nota.</td></tr>`;
      return;
    }

    tbody.innerHTML = this.currentItems.map((item, idx) => `
      <tr>
        <td>
          <input type="text" class="item-input" value="${item.item_name || ''}" placeholder="Nama barang" onchange="app.updateItemField(${idx}, 'item_name', this.value)">
        </td>
        <td style="width: 60px;">
          <input type="number" class="item-input" value="${item.qty || 1}" min="1" step="any" onchange="app.updateItemField(${idx}, 'qty', this.value)">
        </td>
        <td style="width: 100px;">
          <input type="number" class="item-input" value="${item.unit_price || 0}" min="0" step="any" onchange="app.updateItemField(${idx}, 'unit_price', this.value)">
        </td>
        <td style="width: 90px;">
          <input type="number" class="item-input" value="${item.discount || 0}" min="0" step="any" onchange="app.updateItemField(${idx}, 'discount', this.value)">
        </td>
        <td style="width: 36px; text-align: center;">
          <button type="button" class="delete-item-btn" onclick="app.deleteItemRow(${idx})"><i class="fa-solid fa-trash"></i></button>
        </td>
      </tr>
    `).join('');
  }

  addItemRow(initial = null) {
    const newItem = initial || {
      item_name: '',
      qty: 1,
      unit_price: 0,
      discount: 0,
      total_price: 0
    };
    this.currentItems.push(newItem);
    this.renderItemRows();
    this.recalculateTotalsFromItems();
  }

  deleteItemRow(index) {
    this.currentItems.splice(index, 1);
    this.renderItemRows();
    this.recalculateTotalsFromItems();
  }

  updateItemField(index, field, value) {
    if (!this.currentItems[index]) return;
    if (field === 'qty' || field === 'unit_price' || field === 'discount') {
      this.currentItems[index][field] = parseFloat(value) || 0;
    } else {
      this.currentItems[index][field] = value;
    }
    const it = this.currentItems[index];
    it.total_price = Math.max(0, (it.qty * it.unit_price) - it.discount);
    this.recalculateTotalsFromItems();
  }

  recalculateTotalsFromItems() {
    if (this.currentItems.length === 0) return;

    let subtotal = 0;
    let totalDiscount = 0;

    this.currentItems.forEach((it) => {
      const q = it.qty || 1;
      const p = it.unit_price || 0;
      const d = it.discount || 0;
      subtotal += (q * p);
      totalDiscount += d;
    });

    const grandTotal = Math.max(0, subtotal - totalDiscount);
    document.getElementById('transAmount').value = grandTotal;
    const subtotalEl = document.getElementById('breakdownSubtotal');
    const discountEl = document.getElementById('breakdownDiscount');
    if (subtotalEl) subtotalEl.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    if (discountEl) discountEl.textContent = 'Rp ' + totalDiscount.toLocaleString('id-ID');
    this.updateDeductionSummary();
  }

  openTransactionModal(prefill = null) {
    this.editingTransactionId = null;
    this.receiptImageUrl = null;
    this.receiptBase64 = null;
    this.currentItems = [];

    document.getElementById('modalTitle').textContent = 'Tambah Transaksi';
    document.getElementById('deleteTransBtn').style.display = 'none';
    document.getElementById('transAmount').value = prefill?.amount || '';
    document.getElementById('transDate').value = prefill?.date || new Date().toISOString().split('T')[0];
    document.getElementById('transNotes').value = prefill?.notes || '';

    this.setAdminFee(prefill?.admin_fee || 0);

    this.setFormType(prefill?.type || 'pengeluaran');
    if (prefill?.category) {
      this.activeCategoryId = prefill.category;
      this.renderCategoryGrid();
    }

    // Always reset breakdown totals cleanly
    const subtotalEl = document.getElementById('breakdownSubtotal');
    const discountEl = document.getElementById('breakdownDiscount');
    const initialSubtotal = prefill?.subtotal || prefill?.amount || 0;
    const initialDiscount = prefill?.discount || 0;
    if (subtotalEl) subtotalEl.textContent = 'Rp ' + Number(initialSubtotal).toLocaleString('id-ID');
    if (discountEl) discountEl.textContent = 'Rp ' + Number(initialDiscount).toLocaleString('id-ID');

    if (prefill?.items && prefill.items.length > 0) {
      this.currentItems = [...prefill.items];
      this.renderItemRows();
      if (prefill?.amount) {
        document.getElementById('transAmount').value = prefill.amount;
      } else {
        this.recalculateTotalsFromItems();
      }
    } else {
      this.currentItems = [];
      this.renderItemRows();
    }

    const candidateBox = document.getElementById('candidateAmountsBox');
    const candidateList = document.getElementById('candidateChipsList');
    if (candidateBox && candidateList) {
      if (prefill?.candidates && prefill.candidates.length > 0) {
        candidateBox.style.display = 'block';
        candidateList.innerHTML = prefill.candidates.map((c) => `
          <div class="candidate-chip" onclick="document.getElementById('transAmount').value = ${c}; app.updateDeductionSummary();">Rp ${Number(c).toLocaleString('id-ID')}</div>
        `).join('');
      } else {
        candidateBox.style.display = 'none';
        candidateList.innerHTML = '';
      }
    }

    this.renderAccountSelectOptions();

    // Auto-select account if matched by name (e.g. Seabank / GoPay / BCA)
    if (prefill?.account_type || prefill?.notes || prefill?.account_hint) {
      const hint = `${prefill?.account_hint || ''} ${prefill?.account_type || ''} ${prefill?.notes || ''}`.toLowerCase();
      const matchedAcc = this.accounts.find((a) => hint.includes(a.name.toLowerCase()) || hint.includes(a.type.toLowerCase()));
      if (matchedAcc) {
        const sel = document.getElementById('transAccountSelect');
        if (sel) sel.value = matchedAcc.id;
      }
    }

    // Listen to amount changes for deduction preview
    const transAmtInp = document.getElementById('transAmount');
    if (transAmtInp) {
      transAmtInp.oninput = () => this.updateDeductionSummary();
    }

    // Reset upload receipt file input
    const fileInp = document.getElementById('uploadReceiptInput');
    if (fileInp) fileInp.value = '';

    this.updateDeductionSummary();
    this.openModal('transactionModal');
  }

  openEditTransactionModal(id) {
    const item = this.transactions.find((t) => t.id === id);
    if (!item) return;

    this.editingTransactionId = id;
    this.receiptImageUrl = item.receipt_image_url;
    this.currentItems = item.items || [];

    document.getElementById('modalTitle').textContent = 'Edit Transaksi';
    document.getElementById('deleteTransBtn').style.display = 'block';
    document.getElementById('transAmount').value = item.amount;
    document.getElementById('transDate').value = item.transaction_date;
    document.getElementById('transNotes').value = item.notes || '';

    this.setAdminFee(item.admin_fee || 0);

    this.setFormType(item.type);
    if (item.category) {
      this.activeCategoryId = item.category;
      this.renderCategoryGrid();
    }

    this.renderAccountSelectOptions();
    if (item.account_id) document.getElementById('transAccountSelect').value = item.account_id;
    if (item.destination_account_id) document.getElementById('transDestAccountSelect').value = item.destination_account_id;

    const transAmtInp = document.getElementById('transAmount');
    if (transAmtInp) {
      transAmtInp.oninput = () => this.updateDeductionSummary();
    }

    this.renderItemRows();
    this.recalculateTotalsFromItems();
    this.updateDeductionSummary();
    this.openModal('transactionModal');
  }

  async handleTransactionSubmit(e) {
    e.preventDefault();

    const amount = parseFloat(document.getElementById('transAmount').value);
    const adminFee = parseFloat(document.getElementById('transAdminFee')?.value) || 0;
    const date = document.getElementById('transDate').value;
    const notes = document.getElementById('transNotes').value;
    const accountId = document.getElementById('transAccountSelect').value || null;
    const destAccountId = document.getElementById('transDestAccountSelect').value || null;

    if (!amount || amount <= 0) {
      this.showToast('Nominal harus lebih dari 0', 'error');
      return;
    }

    let subtotal = amount;
    let discount = 0;
    if (this.currentItems.length > 0) {
      subtotal = this.currentItems.reduce((acc, it) => acc + ((it.qty || 1) * (it.unit_price || 0)), 0);
      discount = this.currentItems.reduce((acc, it) => acc + (it.discount || 0), 0);
    }

    const payload = {
      type: this.activeFormType,
      amount: amount,
      subtotal: subtotal,
      discount: discount,
      admin_fee: adminFee,
      category: this.activeFormType === 'transfer' ? 'Transfer Antar Rekening' : this.activeCategoryId,
      account_id: accountId,
      destination_account_id: this.activeFormType === 'transfer' ? destAccountId : null,
      transaction_date: date,
      notes: notes,
      receipt_image_url: this.receiptImageUrl,
      items: this.currentItems.filter((it) => it.item_name && it.item_name.trim() !== '')
    };

    try {
      const url = this.editingTransactionId 
        ? this.getEndpoint(`transactions/${this.editingTransactionId}`)
        : this.getEndpoint('transactions');
      const method = this.editingTransactionId ? 'PUT' : 'POST';

      const res = await fetch(url, {
        method,
        headers: this.getHeaders(),
        body: JSON.stringify(payload)
      });
      const json = await res.json();

      if (json.status === 'success') {
        this.showToast(json.message, 'success');
        this.closeModal('transactionModal');
        await this.loadData();
      } else {
        this.showToast(json.message || 'Gagal menyimpan transaksi', 'error');
      }
    } catch (err) {
      this.showToast('Gagal: ' + err.message, 'error');
    }
  }

  async handleDeleteTransaction() {
    if (!this.editingTransactionId) return;
    if (!confirm('Apakah Anda yakin ingin menghapus transaksi ini?')) return;

    try {
      const res = await fetch(this.getEndpoint(`transactions/${this.editingTransactionId}`), {
        method: 'DELETE',
        headers: this.getHeaders()
      });
      const json = await res.json();

      if (json.status === 'success') {
        this.showToast('Transaksi berhasil dihapus', 'success');
        this.closeModal('transactionModal');
        await this.loadData();
      } else {
        this.showToast(json.message || 'Gagal menghapus transaksi', 'error');
      }
    } catch (err) {
      this.showToast('Gagal menghapus: ' + err.message, 'error');
    }
  }

  // ----------------------------------------------------
  // OCR CAMERA & RECEIPT SCANNER
  // ----------------------------------------------------
  openScannerModal() {
    this.openModal('scannerModal');
    this.showProgressBar(false);

    const hasMediaDevices = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    const cameraSourceGroup = document.getElementById('cameraSourceGroup');
    const scannerViewport = document.querySelector('.scanner-viewport');
    const capturePhotoBtn = document.getElementById('capturePhotoBtn');
    const mobileCameraNotice = document.getElementById('mobileCameraNotice');
    const mobileCameraBtn = document.getElementById('mobileCameraBtn');

    if (!hasMediaDevices) {
      // Mobile HTTP LAN without SSL: navigator.mediaDevices is blocked by browser security
      if (cameraSourceGroup) cameraSourceGroup.style.display = 'none';
      if (scannerViewport) scannerViewport.style.display = 'none';
      if (capturePhotoBtn) capturePhotoBtn.style.display = 'none';
      if (mobileCameraNotice) mobileCameraNotice.style.display = 'block';
      if (mobileCameraBtn) mobileCameraBtn.style.display = 'flex';
    } else {
      if (cameraSourceGroup) cameraSourceGroup.style.display = 'block';
      if (scannerViewport) scannerViewport.style.display = 'flex';
      if (capturePhotoBtn) capturePhotoBtn.style.display = 'flex';
      if (mobileCameraNotice) mobileCameraNotice.style.display = 'none';
      if (mobileCameraBtn) mobileCameraBtn.style.display = 'none';

      if (typeof receiptScanner !== 'undefined') {
        receiptScanner.startCamera('cameraVideo').catch((err) => {
          console.warn('Live stream camera not available, falling back to native camera:', err);
          if (cameraSourceGroup) cameraSourceGroup.style.display = 'none';
          if (scannerViewport) scannerViewport.style.display = 'none';
          if (capturePhotoBtn) capturePhotoBtn.style.display = 'none';
          if (mobileCameraNotice) mobileCameraNotice.style.display = 'block';
          if (mobileCameraBtn) mobileCameraBtn.style.display = 'flex';
        });
      }
    }
  }

  async captureAndProcessScanner() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('scannerCanvas');
    const captureBtn = document.getElementById('capturePhotoBtn');
    if (!video || !canvas || typeof receiptScanner === 'undefined') return;

    if (captureBtn) captureBtn.disabled = true;
    const dataUrl = receiptScanner.captureFrame(video, canvas);
    receiptScanner.stopCamera();

    this.showProgressBar(true, 'Sedang memproses foto struk...');
    const result = await receiptScanner.processImage(dataUrl, (p) => {
      this.updateProgress(p.progress * 100, p.status);
    });
    this.showProgressBar(false);
    if (captureBtn) captureBtn.disabled = false;
    this.closeModal('scannerModal');

    if (result && result.success) {
      this.showToast(`OCR Berhasil: Toko "${result.store_name || 'Struk'}"`, 'success');
      this.openTransactionModal({
        amount: result.total_amount || result.amount || 0,
        subtotal: result.subtotal || result.total_amount || 0,
        discount: result.discount || 0,
        type: 'pengeluaran',
        category: result.category || 'Belanja',
        date: result.transaction_date || new Date().toISOString().split('T')[0],
        notes: result.notes || (result.store_name ? `Belanja di ${result.store_name}` : 'Belanja Struk OCR'),
        account_hint: result.account_hint || '',
        items: result.items || [],
        candidates: result.all_detected_numbers || []
      });
    } else {
      this.showToast('Gagal mendeteksi teks nota secara jelas.', 'info');
      this.openTransactionModal();
    }
  }

  handleReceiptUploadInput(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (event) => {
      const dataUrl = event.target.result;
      receiptScanner.stopCamera();

      // Keep modal open and show active progress
      this.showProgressBar(true, 'Sedang membaca teks nota dengan Tesseract OCR...');
      const result = await receiptScanner.processImage(dataUrl, (p) => {
        this.updateProgress(p.progress * 100, p.status);
      });
      this.showProgressBar(false);
      this.closeModal('scannerModal');

      if (result && result.success) {
        this.showToast(`Nota terdeteksi: ${result.store_name || 'Struk'}`, 'success');
        this.openTransactionModal({
          amount: result.total_amount || result.amount || 0,
          subtotal: result.subtotal || result.total_amount || 0,
          discount: result.discount || 0,
          type: 'pengeluaran',
          category: result.category || 'Belanja',
          date: result.transaction_date || new Date().toISOString().split('T')[0],
          notes: result.notes || (result.store_name ? `Belanja di ${result.store_name}` : 'Belanja Struk OCR'),
          account_hint: result.account_hint || '',
          items: result.items || [],
          candidates: result.all_detected_numbers || []
        });
      } else {
        this.showToast('Gagal membaca teks nota.', 'info');
        this.openTransactionModal();
      }
    };
    reader.readAsDataURL(file);
  }

  showProgressBar(show, text = '') {
    const box = document.getElementById('ocrProgressBox');
    const txt = document.getElementById('ocrProgressText');
    const bar = document.getElementById('ocrProgressBar');
    if (box) box.style.display = show ? 'block' : 'none';
    if (txt && text) txt.textContent = text;
    if (bar && !show) bar.style.width = '0%';
  }

  updateProgress(percent, statusText) {
    const bar = document.getElementById('ocrProgressBar');
    const txt = document.getElementById('ocrProgressText');
    if (bar) bar.style.width = `${Math.min(100, percent)}%`;
    if (txt && statusText) txt.textContent = statusText;
  }

  // ----------------------------------------------------
  // MODAL HELPERS & CSV EXPORT
  // ----------------------------------------------------
  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
  }

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
    if (modalId === 'scannerModal' && typeof receiptScanner !== 'undefined') {
      receiptScanner.stopCamera();
    }
  }

  showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
    toast.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(50px)';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  // ----------------------------------------------------
  // EXPORT TO EXCEL MODAL & PROCESSOR
  // ----------------------------------------------------
  openExportModal() {
    const [y, m] = this.currentMonth.split('-').map(Number);
    const dateObj = new Date(y, m - 1, 1);
    const monthName = dateObj.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    
    const labelEl = document.getElementById('exportCurrentMonthLabel');
    if (labelEl) labelEl.innerText = monthName;

    // Populate Year Dropdown dynamically
    const yearSelect = document.getElementById('exportSelectYear');
    if (yearSelect) {
      yearSelect.innerHTML = '';
      const currentY = new Date().getFullYear();
      for (let yr = currentY - 3; yr <= currentY + 3; yr++) {
        const opt = document.createElement('option');
        opt.value = String(yr);
        opt.innerText = String(yr);
        if (yr === y) opt.selected = true;
        yearSelect.appendChild(opt);
      }
    }

    const monthSelect = document.getElementById('exportSelectMonth');
    if (monthSelect) {
      monthSelect.value = String(m).padStart(2, '0');
    }

    this.syncCustomMonthInput();

    const radioCurrent = document.querySelector('input[name="exportScope"][value="current"]');
    if (radioCurrent) radioCurrent.checked = true;

    this.onExportScopeChange('current');
    this.openModal('exportModal');
  }

  syncCustomMonthInput() {
    const m = document.getElementById('exportSelectMonth')?.value || '01';
    const y = document.getElementById('exportSelectYear')?.value || new Date().getFullYear();
    const customInput = document.getElementById('exportCustomMonthInput');
    if (customInput) {
      customInput.value = `${y}-${m}`;
    }
  }

  onExportScopeChange(scope) {
    document.querySelectorAll('.export-option-card').forEach((card) => card.classList.remove('active'));
    
    if (scope === 'current') {
      document.getElementById('exportCardCurrent')?.classList.add('active');
      const grp = document.getElementById('exportCustomMonthGroup');
      if (grp) grp.style.display = 'none';
    } else if (scope === 'all') {
      document.getElementById('exportCardAll')?.classList.add('active');
      const grp = document.getElementById('exportCustomMonthGroup');
      if (grp) grp.style.display = 'none';
    } else if (scope === 'custom') {
      document.getElementById('exportCardCustom')?.classList.add('active');
      const grp = document.getElementById('exportCustomMonthGroup');
      if (grp) grp.style.display = 'block';
      this.syncCustomMonthInput();
    }
  }

  async executeExport() {
    const scopeRadio = document.querySelector('input[name="exportScope"]:checked');
    const scope = scopeRadio ? scopeRadio.value : 'current';
    const btn = document.getElementById('btnProcessExport');
    const originalBtnText = btn ? btn.innerHTML : '';

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyiapkan Data...';
    }

    try {
      let exportTransactions = [];
      let scopeLabel = '';
      let fileSlug = '';

      if (scope === 'current') {
        const [y, m] = this.currentMonth.split('-').map(Number);
        const dateObj = new Date(y, m - 1, 1);
        const monthName = dateObj.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        scopeLabel = `Bulan ${monthName}`;
        fileSlug = `bulan_${this.currentMonth}`;

        if (this.transactions && this.transactions.length > 0) {
          exportTransactions = this.transactions;
        } else {
          const res = await fetch(this.getEndpoint(`transactions?month=${this.currentMonth}`), { headers: this.getHeaders() });
          const json = await res.json();
          exportTransactions = json.data || [];
        }
      } else if (scope === 'custom') {
        const customVal = document.getElementById('exportCustomMonthInput')?.value;
        if (!customVal) {
          this.showToast('Silakan pilih bulan dan tahun terlebih dahulu', 'warning');
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalBtnText;
          }
          return;
        }

        const [cy, cm] = customVal.split('-').map(Number);
        const dateObj = new Date(cy, cm - 1, 1);
        const customMonthName = dateObj.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        scopeLabel = `Bulan ${customMonthName}`;
        fileSlug = `bulan_${customVal}`;

        const res = await fetch(this.getEndpoint(`transactions?month=${customVal}`), { headers: this.getHeaders() });
        const json = await res.json();
        exportTransactions = json.data || [];
      } else if (scope === 'all') {
        scopeLabel = 'Semua Periode (Sepanjang Waktu)';
        fileSlug = 'semua_data';

        const res = await fetch(this.getEndpoint('transactions'), { headers: this.getHeaders() });
        const json = await res.json();
        exportTransactions = json.data || [];
      }

      if (!exportTransactions || exportTransactions.length === 0) {
        this.showToast(`Tidak ada data transaksi untuk ${scopeLabel}`, 'info');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = originalBtnText;
        }
        return;
      }

      this.generateExcelFile(exportTransactions, scopeLabel, fileSlug);
      this.closeModal('exportModal');
    } catch (err) {
      console.error('Failed to export data:', err);
      this.showToast('Gagal memuat data untuk ekspor', 'error');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalBtnText;
      }
    }
  }

  generateExcelFile(transactions, scopeLabel, fileSlug) {
    const typeLabel = (type) => {
      if (type === 'pengeluaran') return 'Pengeluaran';
      if (type === 'pemasukan') return 'Pemasukan';
      if (type === 'transfer') return 'Transfer Antar Rekening';
      return type || '-';
    };

    const num = (v) => Number(v) || 0;

    // ----------------------------------------------------
    // Financial Aggregate Totals
    // ----------------------------------------------------
    let totalIncome = 0;
    let totalExpense = 0;
    let totalAdminFee = 0;
    let totalDiscount = 0;
    let totalTax = 0;
    let totalItemCount = 0;

    transactions.forEach((t) => {
      const amt = num(t.amount);
      const fee = num(t.admin_fee);
      const disc = num(t.discount);
      const tax = num(t.tax);

      if (t.type === 'pemasukan') {
        totalIncome += amt;
      } else if (t.type === 'pengeluaran') {
        totalExpense += amt;
      }

      totalAdminFee += fee;
      totalDiscount += disc;
      totalTax += tax;

      if (Array.isArray(t.items)) {
        totalItemCount += t.items.length;
      }
    });

    const netCashflow = totalIncome - totalExpense;

    if (typeof XLSX !== 'undefined') {
      try {
        const wb = XLSX.utils.book_new();

        // ----------------------------------------------------
        // SHEET 1: RINGKASAN & DAFTAR TRANSAKSI
        // ----------------------------------------------------
        const sheet1Data = [
          ['LAPORAN KEUANGAN & TRANSAKSI - myCost'],
          ['Periode Data:', scopeLabel],
          ['Waktu Unduh:', new Date().toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' })],
          [],
          ['=== RINGKASAN EKSEKUTIF KEUANGAN ===', 'JUMLAH (RP)'],
          ['Total Pemasukan (+)', totalIncome],
          ['Total Pengeluaran (-)', totalExpense],
          ['Arus Kas Bersih (Surplus / Defisit)', netCashflow],
          ['Total Biaya Admin', totalAdminFee],
          ['Total Diskon Diperoleh', totalDiscount],
          ['Total Pajak', totalTax],
          ['Total Transaksi Tercatat', transactions.length],
          ['Total Item & Produk Terinci', totalItemCount],
          [],
          ['=== DAFTAR TRANSAKSI LENGKAP ==='],
          [
            'No',
            'ID Transaksi',
            'Tanggal',
            'Tipe Transaksi',
            'Kategori',
            'Dari Rekening/Dompet',
            'Rekening Tujuan',
            'Jumlah Item',
            'Rincian Produk/Item',
            'Subtotal (Rp)',
            'Diskon Transaksi (Rp)',
            'Pajak (Rp)',
            'Biaya Admin (Rp)',
            'Total Akhir (Rp)',
            'Catatan Transaksi'
          ]
        ];

        transactions.forEach((t, idx) => {
          const items = Array.isArray(t.items) ? t.items : [];
          const itemsSummary = items.length > 0
            ? items.map(it => `${it.item_name || 'Item'} (x${it.qty || 1} @ Rp ${(it.unit_price || 0).toLocaleString('id-ID')})`).join('; ')
            : '-';

          const sourceAcc = t.account ? t.account.name : '-';
          const destAcc = t.destination_account ? t.destination_account.name : (t.destinationAccount ? t.destinationAccount.name : '-');

          sheet1Data.push([
            idx + 1,
            t.id,
            t.transaction_date || '-',
            typeLabel(t.type),
            t.category || '-',
            sourceAcc,
            destAcc,
            items.length,
            itemsSummary,
            num(t.subtotal || t.amount),
            num(t.discount),
            num(t.tax),
            num(t.admin_fee),
            num(t.amount),
            t.notes || ''
          ]);
        });

        // ----------------------------------------------------
        // SHEET 2: RINCIAN ITEM & PRODUK (1 Baris per Produk)
        // ----------------------------------------------------
        const sheet2Data = [
          [
            'No',
            'ID Transaksi',
            'Tanggal Transaksi',
            'Tipe Transaksi',
            'Kategori',
            'Rekening',
            'Nama Produk / Item',
            'Jumlah (Qty)',
            'Harga Satuan (Rp)',
            'Diskon Item (Rp)',
            'Total Harga Item (Rp)',
            'Catatan Transaksi'
          ]
        ];

        let itemCounter = 1;
        transactions.forEach((t) => {
          const items = Array.isArray(t.items) ? t.items : [];
          const accName = t.account ? t.account.name : (t.destination_account ? t.destination_account.name : '-');

          if (items.length > 0) {
            items.forEach((it) => {
              sheet2Data.push([
                itemCounter++,
                t.id,
                t.transaction_date || '-',
                typeLabel(t.type),
                t.category || '-',
                accName,
                it.item_name || 'Item',
                num(it.qty || 1),
                num(it.unit_price),
                num(it.discount),
                num(it.total_price || (num(it.qty || 1) * num(it.unit_price) - num(it.discount))),
                t.notes || ''
              ]);
            });
          } else {
            sheet2Data.push([
              itemCounter++,
              t.id,
              t.transaction_date || '-',
              typeLabel(t.type),
              t.category || '-',
              accName,
              `[Non-Itemized: ${t.category || 'Transaksi'}]`,
              1,
              num(t.amount),
              num(t.discount),
              num(t.amount),
              t.notes || ''
            ]);
          }
        });

        // ----------------------------------------------------
        // SHEET 3: LAPORAN LENGKAP FLAT (Pivot & Analisis)
        // ----------------------------------------------------
        const sheet3Data = [
          [
            'No Transaksi',
            'ID Transaksi',
            'Tanggal',
            'Tipe Transaksi',
            'Kategori',
            'Rekening Asal',
            'Rekening Tujuan',
            'Nama Produk / Item',
            'Qty',
            'Harga Satuan (Rp)',
            'Diskon Item (Rp)',
            'Total Harga Item (Rp)',
            'Subtotal Transaksi (Rp)',
            'Diskon Transaksi (Rp)',
            'Pajak (Rp)',
            'Biaya Admin (Rp)',
            'Total Akhir Transaksi (Rp)',
            'Catatan'
          ]
        ];

        transactions.forEach((t, tIdx) => {
          const items = Array.isArray(t.items) ? t.items : [];
          const sourceAcc = t.account ? t.account.name : '-';
          const destAcc = t.destination_account ? t.destination_account.name : (t.destinationAccount ? t.destinationAccount.name : '-');

          if (items.length > 0) {
            items.forEach((it, iIdx) => {
              sheet3Data.push([
                `${tIdx + 1}.${iIdx + 1}`,
                t.id,
                t.transaction_date || '-',
                typeLabel(t.type),
                t.category || '-',
                sourceAcc,
                destAcc,
                it.item_name || 'Item',
                num(it.qty || 1),
                num(it.unit_price),
                num(it.discount),
                num(it.total_price || (num(it.qty || 1) * num(it.unit_price) - num(it.discount))),
                num(t.subtotal || t.amount),
                num(t.discount),
                num(t.tax),
                num(t.admin_fee),
                num(t.amount),
                t.notes || ''
              ]);
            });
          } else {
            sheet3Data.push([
              `${tIdx + 1}`,
              t.id,
              t.transaction_date || '-',
              typeLabel(t.type),
              t.category || '-',
              sourceAcc,
              destAcc,
              '-',
              '-',
              '-',
              '-',
              '-',
              num(t.subtotal || t.amount),
              num(t.discount),
              num(t.tax),
              num(t.admin_fee),
              num(t.amount),
              t.notes || ''
            ]);
          }
        });

        // Helper to adjust column widths dynamically
        const autoFit = (aoa) => {
          const widths = [];
          aoa.forEach(row => {
            row.forEach((cell, colIdx) => {
              const str = cell !== null && cell !== undefined ? String(cell) : '';
              const len = Math.min(Math.max(str.length + 3, 10), 60);
              widths[colIdx] = Math.max(widths[colIdx] || 0, len);
            });
          });
          return widths.map(w => ({ wch: w }));
        };

        const ws1 = XLSX.utils.aoa_to_sheet(sheet1Data);
        ws1['!cols'] = autoFit(sheet1Data);
        XLSX.utils.book_append_sheet(wb, ws1, 'Ringkasan & Transaksi');

        const ws2 = XLSX.utils.aoa_to_sheet(sheet2Data);
        ws2['!cols'] = autoFit(sheet2Data);
        XLSX.utils.book_append_sheet(wb, ws2, 'Detail Produk & Item');

        const ws3 = XLSX.utils.aoa_to_sheet(sheet3Data);
        ws3['!cols'] = autoFit(sheet3Data);
        XLSX.utils.book_append_sheet(wb, ws3, 'Laporan Lengkap Flat');

        const filename = `mycost_laporan_${fileSlug}.xlsx`;
        XLSX.writeFile(wb, filename);
        this.showToast(`Laporan Excel (${scopeLabel}) berhasil diunduh!`, 'success');
        return;
      } catch (err) {
        console.error('Error generating Excel file with SheetJS:', err);
      }
    }

    // Fallback: Rich CSV Export
    const csvHeaders = ['ID', 'Tanggal', 'Tipe', 'Kategori', 'Rekening', 'Rekening Tujuan', 'Item Produk', 'Subtotal', 'Diskon', 'Pajak', 'Admin Fee', 'Total Akhir', 'Catatan'];
    const csvRows = transactions.map((t) => {
      const items = Array.isArray(t.items) ? t.items : [];
      const itemsStr = items.map(it => `${it.item_name} (x${it.qty} @${it.unit_price})`).join('; ');
      return [
        t.id,
        t.transaction_date,
        typeLabel(t.type),
        `"${t.category || ''}"`,
        `"${t.account ? t.account.name : ''}"`,
        `"${t.destination_account ? t.destination_account.name : (t.destinationAccount ? t.destinationAccount.name : '')}"`,
        `"${itemsStr.replace(/"/g, '""')}"`,
        t.subtotal || t.amount,
        t.discount || 0,
        t.tax || 0,
        t.admin_fee || 0,
        t.amount,
        `"${(t.notes || '').replace(/"/g, '""')}"`
      ];
    });

    const csvContent = '\uFEFF' + [
      `LAPORAN KEUANGAN - myCost (${scopeLabel})`,
      `Total Pemasukan: Rp ${totalIncome.toLocaleString('id-ID')}`,
      `Total Pengeluaran: Rp ${totalExpense.toLocaleString('id-ID')}`,
      `Arus Kas Bersih: Rp ${netCashflow.toLocaleString('id-ID')}`,
      '',
      csvHeaders.join(','),
      ...csvRows.map((r) => r.join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', `mycost_laporan_${fileSlug}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    this.showToast(`Laporan CSV (${scopeLabel}) berhasil diunduh!`, 'success');
  }

  exportToExcel() {
    this.openExportModal();
  }

  exportToCSV() {
    this.openExportModal();
  }
}

// Global App Instance
const app = new MyCostApp();

