// js/app.js - Main Application Controller for myCost (Multi-User & Itemized Breakdown)

class MyCostApp {
  constructor() {
    this.currentMonth = new Date().toISOString().substring(0, 7); // YYYY-MM
    this.currentFilter = 'all'; // all, pengeluaran, pemasukan
    this.currentCategory = '';
    this.searchQuery = '';
    this.transactions = [];
    this.categories = { pengeluaran: [], pemasukan: [] };
    this.activeFormType = 'pengeluaran';
    this.activeCategoryId = 'Makanan & Minuman';
    this.editingTransactionId = null;
    this.receiptImageUrl = null;
    this.receiptBase64 = null;
    this.currentUser = JSON.parse(localStorage.getItem('mycost_user') || 'null');
    this.currentItems = [];

    this.init();
  }

  async init() {
    this.initTheme();
    this.initPWA();
    this.bindEvents();
    this.updateUserUI();
    await this.checkAuth();
    await this.fetchCategories();
    await this.loadData();
    this.checkOnlineStatus();
    this.setupUrlActionHandler();
  }

  // ----------------------------------------------------
  // Theme & PWA Registration
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

  initPWA() {
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
          .then((reg) => console.log('ServiceWorker registered:', reg.scope))
          .catch((err) => console.warn('ServiceWorker registration failed:', err));
      });
    }

    window.addEventListener('online', () => {
      this.checkOnlineStatus();
      this.syncPendingData();
    });

    window.addEventListener('offline', () => {
      this.checkOnlineStatus();
    });
  }

  checkOnlineStatus() {
    const badge = document.getElementById('onlineStatusBadge');
    const isOnline = navigator.onLine;

    if (badge) {
      if (isOnline) {
        badge.classList.remove('offline');
        badge.innerHTML = '<span class="status-dot"></span> Online';
      } else {
        badge.classList.add('offline');
        badge.innerHTML = '<span class="status-dot"></span> Offline';
      }
    }
  }

  setupUrlActionHandler() {
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');
    if (action === 'add') {
      this.openTransactionModal();
    } else if (action === 'scan') {
      this.openScannerModal();
    }
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

  // ----------------------------------------------------
  // User Authentication Logic
  // ----------------------------------------------------
  async checkAuth() {
    try {
      const endpoint = this.getEndpoint('auth/me', 'api/auth.php?action=me');
      const res = await fetch(endpoint, { headers: this.getHeaders() });
      const json = await res.json();
      if (json.authenticated && json.user) {
        this.currentUser = json.user;
        localStorage.setItem('mycost_user', JSON.stringify(this.currentUser));
      }
    } catch (e) {
      // Keep local user if any
    }
    this.updateUserUI();
  }

  updateUserUI() {
    const userPill = document.getElementById('userProfilePill');
    const userNameLabel = document.getElementById('userNameLabel');
    const userAvatar = document.getElementById('userAvatar');

    if (this.currentUser) {
      if (userNameLabel) userNameLabel.textContent = this.currentUser.name || 'User';
      if (userAvatar) userAvatar.textContent = (this.currentUser.name || 'U').charAt(0).toUpperCase();
      if (userPill) userPill.style.display = 'flex';
    } else {
      if (userNameLabel) userNameLabel.textContent = 'Login';
      if (userAvatar) userAvatar.textContent = '<i class="fa-solid fa-user"></i>';
      if (userPill) userPill.style.display = 'flex';
    }
  }

  openAuthModal(tab = 'login') {
    this.openModal('authModal');
    this.switchAuthTab(tab);
  }

  switchAuthTab(tab) {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const tabLogin = document.getElementById('tabLoginBtn');
    const tabRegister = document.getElementById('tabRegisterBtn');

    if (tab === 'login') {
      loginForm.style.display = 'block';
      registerForm.style.display = 'none';
      tabLogin.classList.add('active');
      tabRegister.classList.remove('active');
    } else {
      loginForm.style.display = 'none';
      registerForm.style.display = 'block';
      tabRegister.classList.add('active');
      tabLogin.classList.remove('active');
    }
  }

  async handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;

    try {
      const endpoint = this.getEndpoint('auth/login', 'api/auth.php?action=login');
      const res = await fetch(endpoint, {
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
      const endpoint = this.getEndpoint('auth/register', 'api/auth.php?action=register');
      const res = await fetch(endpoint, {
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
      const endpoint = this.getEndpoint('auth/logout', 'api/auth.php?action=logout');
      await fetch(endpoint, { method: 'POST', headers: this.getHeaders() });
    } catch (e) {}

    this.currentUser = null;
    localStorage.removeItem('mycost_user');
    this.updateUserUI();
    this.closeModal('authModal');
    this.showToast('Anda telah logout.', 'info');
    await this.loadData();
  }

  getEndpoint(laravelPath, phpPath) {
    // If running in environment with Laravel API route
    if (window.location.pathname.includes('/public') || window.location.port === '8000') {
      return `api/${laravelPath}`;
    }
    return phpPath;
  }

  // ----------------------------------------------------
  // Event Listeners
  // ----------------------------------------------------
  bindEvents() {
    document.getElementById('themeToggleBtn')?.addEventListener('click', () => this.toggleTheme());
    document.getElementById('prevMonthBtn')?.addEventListener('click', () => this.changeMonth(-1));
    document.getElementById('nextMonthBtn')?.addEventListener('click', () => this.changeMonth(1));

    document.getElementById('userProfilePill')?.addEventListener('click', () => {
      if (this.currentUser) {
        if (confirm(`Login sebagai ${this.currentUser.name} (${this.currentUser.email}). Apakah Anda ingin logout?`)) {
          this.handleLogout();
        }
      } else {
        this.openAuthModal('login');
      }
    });

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
    document.getElementById('openScanBtn')?.addEventListener('click', () => this.openScannerModal());
    document.getElementById('navAddBtn')?.addEventListener('click', () => this.openTransactionModal());
    document.getElementById('navScanBtn')?.addEventListener('click', () => this.openScannerModal());
    document.getElementById('exportDataBtn')?.addEventListener('click', () => this.exportToCSV());
    document.getElementById('dbStatusBtn')?.addEventListener('click', () => this.openDbStatusModal());

    document.querySelectorAll('.modal-overlay .close-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        const modal = e.target.closest('.modal-overlay');
        if (modal) this.closeModal(modal.id);
      });
    });

    document.querySelectorAll('.modal-overlay').forEach((modal) => {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          this.closeModal(modal.id);
        }
      });
    });

    document.getElementById('btnTypeExpense')?.addEventListener('click', () => this.setFormType('pengeluaran'));
    document.getElementById('btnTypeIncome')?.addEventListener('click', () => this.setFormType('pemasukan'));
    document.getElementById('transactionForm')?.addEventListener('submit', (e) => this.handleFormSubmit(e));
    document.getElementById('deleteTransBtn')?.addEventListener('click', () => this.handleDeleteTransaction());
    document.getElementById('capturePhotoBtn')?.addEventListener('click', () => this.captureAndProcessScanner());
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
    if (el) el.textContent = label;
  }

  // ----------------------------------------------------
  // API & Data Fetching
  // ----------------------------------------------------
  async fetchCategories() {
    try {
      const endpoint = this.getEndpoint('categories', 'api/categories.php');
      const res = await fetch(endpoint, { headers: this.getHeaders() });
      const json = await res.json();
      if (json.status === 'success' && json.data) {
        this.categories = json.data;
      }
    } catch (e) {
      this.categories = {
        pengeluaran: [
          { name: 'Makanan & Minuman', icon: 'fa-utensils', color: '#10b981' },
          { name: 'Belanja', icon: 'fa-bag-shopping', color: '#06b6d4' },
          { name: 'Transportasi', icon: 'fa-car', color: '#3b82f6' },
          { name: 'Tagihan & Utilitas', icon: 'fa-receipt', color: '#ef4444' },
          { name: 'Hiburan', icon: 'fa-gamepad', color: '#f59e0b' },
          { name: 'Kesehatan', icon: 'fa-heart-pulse', color: '#14b8a6' },
          { name: 'Lainnya', icon: 'fa-circle-question', color: '#64748b' }
        ],
        pemasukan: [
          { name: 'Gaji', icon: 'fa-money-bill-wave', color: '#10b981' },
          { name: 'Freelance', icon: 'fa-laptop-code', color: '#3b82f6' },
          { name: 'Bisnis / Usaha', icon: 'fa-store', color: '#06b6d4' },
          { name: 'Lainnya', icon: 'fa-circle-question', color: '#64748b' }
        ]
      };
    }
    this.renderCategoryGrid();
  }

  async loadData() {
    this.updateMonthDisplay();
    this.showLoading(true);

    try {
      const statsUrl = this.getEndpoint(`stats?month=${this.currentMonth}`, `api/stats.php?month=${this.currentMonth}`);
      const statsRes = await fetch(statsUrl, { headers: this.getHeaders() });
      if (statsRes.ok) {
        const statsJson = await statsRes.json();
        if (statsJson.status === 'success') {
          this.updateDashboardCards(statsJson.data.summary);
          financialCharts.renderCategoryChart('categoryChart', statsJson.data.categories);
          financialCharts.renderTrendChart('trendChart', statsJson.data.monthly_trend);
        }
      }

      const transUrl = this.getEndpoint(`transactions?month=${this.currentMonth}`, `api/transactions.php?month=${this.currentMonth}`);
      const transRes = await fetch(transUrl, { headers: this.getHeaders() });
      if (transRes.ok) {
        const transJson = await transRes.json();
        if (transJson.status === 'success') {
          this.transactions = transJson.data || [];
          if (typeof localDB !== 'undefined') {
            await localDB.cacheTransactions(this.transactions);
          }
        }
      }
    } catch (err) {
      console.warn('Network error, loading data from offline cache:', err);
      if (typeof localDB !== 'undefined') {
        const localData = await localDB.getAllTransactions();
        this.transactions = localData.filter((t) => (t.transaction_date || '').startsWith(this.currentMonth));
        this.calculateLocalStats();
      }
      this.showToast('Mode Offline: Memuat data dari memori lokal.', 'info');
    } finally {
      this.showLoading(false);
      this.renderTransactionsList();
    }
  }

  calculateLocalStats() {
    let income = 0;
    let expense = 0;
    const catMap = {};

    this.transactions.forEach((t) => {
      const amt = parseFloat(t.amount);
      if (t.type === 'pemasukan') {
        income += amt;
      } else {
        expense += amt;
        catMap[t.category] = (catMap[t.category] || 0) + amt;
      }
    });

    const catArray = Object.keys(catMap).map((k) => ({
      category: k,
      total_amount: catMap[k]
    }));

    this.updateDashboardCards({
      net_balance: income - expense,
      month_income: income,
      month_expense: expense
    });

    financialCharts.renderCategoryChart('categoryChart', catArray);
  }

  updateDashboardCards(summary) {
    if (!summary) return;
    const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');

    const balEl = document.getElementById('totalBalanceVal');
    const incEl = document.getElementById('monthIncomeVal');
    const expEl = document.getElementById('monthExpenseVal');

    if (balEl) balEl.textContent = formatRp(summary.net_balance);
    if (incEl) incEl.textContent = formatRp(summary.month_income || summary.total_income);
    if (expEl) expEl.textContent = formatRp(summary.month_expense || summary.total_expense);
  }

  // ----------------------------------------------------
  // Transactions Rendering & Filtering
  // ----------------------------------------------------
  renderTransactionsList() {
    const listEl = document.getElementById('transactionsList');
    if (!listEl) return;

    let filtered = this.transactions.filter((item) => {
      if (this.currentFilter !== 'all' && item.type !== this.currentFilter) {
        return false;
      }
      if (this.searchQuery) {
        const notes = (item.notes || '').toLowerCase();
        const cat = (item.category || '').toLowerCase();
        if (!notes.includes(this.searchQuery) && !cat.includes(this.searchQuery)) {
          return false;
        }
      }
      return true;
    });

    if (filtered.length === 0) {
      listEl.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-receipt"></i>
          <p>Belum ada transaksi di bulan ini.</p>
        </div>
      `;
      return;
    }

    listEl.innerHTML = filtered.map((item) => {
      const isIncome = item.type === 'pemasukan';
      const amtClass = isIncome ? 'income' : 'expense';
      const sign = isIncome ? '+' : '-';
      const dateFormatted = new Date(item.transaction_date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
      });

      const itemCount = item.items ? item.items.length : 0;

      return `
        <div class="trans-item" onclick="app.openEditTransactionModal(${item.id})">
          <div class="trans-left">
            <div class="trans-icon-box ${amtClass}">
              <i class="fa-solid ${this.getCategoryIcon(item.category, item.type)}"></i>
            </div>
            <div class="trans-details">
              <div class="trans-cat">
                ${item.category}
                ${itemCount > 0 ? `<span class="receipt-tag"><i class="fa-solid fa-list-check"></i> ${itemCount} Item</span>` : ''}
                ${item.is_local_pending ? '<span class="receipt-tag"><i class="fa-solid fa-cloud-arrow-up"></i> Pending</span>' : ''}
              </div>
              <div class="trans-notes">${item.notes || '-'}</div>
              <div class="trans-date">${dateFormatted}</div>
            </div>
          </div>
          <div class="trans-right">
            <div class="trans-amount ${amtClass}">${sign} Rp ${Number(item.amount).toLocaleString('id-ID')}</div>
            ${item.discount > 0 ? `<span style="font-size: 11px; color: var(--income);">Diskon: Rp ${Number(item.discount).toLocaleString('id-ID')}</span>` : ''}
            ${item.receipt_image_url ? `<span class="receipt-tag"><i class="fa-solid fa-image"></i> Nota</span>` : ''}
          </div>
        </div>
      `;
    }).join('');
  }

  getCategoryIcon(catName, type) {
    const list = this.categories[type] || [];
    const found = list.find((c) => c.name.toLowerCase() === (catName || '').toLowerCase());
    return found ? found.icon : 'fa-receipt';
  }

  setFormType(type) {
    this.activeFormType = type;
    const btnExpense = document.getElementById('btnTypeExpense');
    const btnIncome = document.getElementById('btnTypeIncome');

    if (type === 'pengeluaran') {
      btnExpense.classList.add('active', 'expense');
      btnIncome.classList.remove('active', 'income');
    } else {
      btnIncome.classList.add('active', 'income');
      btnExpense.classList.remove('active', 'expense');
    }

    this.renderCategoryGrid();
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
  // Itemized Receipt Rows Management
  // ----------------------------------------------------
  renderItemRows() {
    const tbody = document.getElementById('itemsTableBody');
    if (!tbody) return;

    if (this.currentItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 10px;">Belum ada rincian barang. Klik "+ Tambah Item" jika ingin merinci.</td></tr>`;
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
  }

  // ----------------------------------------------------
  // Transaction Modal (Add / Edit)
  // ----------------------------------------------------
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

    this.setFormType(prefill?.type || 'pengeluaran');
    if (prefill?.category) {
      this.activeCategoryId = prefill.category;
      this.renderCategoryGrid();
    }

    // Prefill items from OCR
    if (prefill?.items && prefill.items.length > 0) {
      this.currentItems = prefill.items;
      this.renderItemRows();
      this.recalculateTotalsFromItems();
    } else {
      this.renderItemRows();
    }

    // OCR Candidates
    const candidateBox = document.getElementById('candidateAmountsBox');
    const candidateList = document.getElementById('candidateChipsList');
    if (candidateBox && candidateList) {
      if (prefill?.candidates && prefill.candidates.length > 0) {
        candidateBox.style.display = 'block';
        candidateList.innerHTML = prefill.candidates.map((c) => `
          <div class="candidate-chip" onclick="document.getElementById('transAmount').value = ${c};">Rp ${Number(c).toLocaleString('id-ID')}</div>
        `).join('');
      } else {
        candidateBox.style.display = 'none';
      }
    }

    if (prefill?.receipt_image_url) {
      this.receiptImageUrl = prefill.receipt_image_url;
    }

    this.openModal('transactionModal');
  }

  openEditTransactionModal(id) {
    const item = this.transactions.find((t) => t.id == id);
    if (!item) return;

    this.editingTransactionId = item.id;
    this.receiptImageUrl = item.receipt_image_url;
    this.receiptBase64 = null;
    this.currentItems = item.items || [];

    document.getElementById('modalTitle').textContent = 'Edit Transaksi';
    document.getElementById('deleteTransBtn').style.display = 'block';
    document.getElementById('transAmount').value = item.amount;
    document.getElementById('transDate').value = item.transaction_date;
    document.getElementById('transNotes').value = item.notes || '';

    this.setFormType(item.type);
    this.activeCategoryId = item.category;
    this.renderCategoryGrid();
    this.renderItemRows();

    const candidateBox = document.getElementById('candidateAmountsBox');
    if (candidateBox) candidateBox.style.display = 'none';

    this.openModal('transactionModal');
  }

  async handleFormSubmit(e) {
    e.preventDefault();

    const amount = parseFloat(document.getElementById('transAmount').value);
    const date = document.getElementById('transDate').value;
    const notes = document.getElementById('transNotes').value;

    if (!amount || amount <= 0) {
      this.showToast('Harap masukkan nominal yang valid.', 'error');
      return;
    }

    // Compute subtotal & discount
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
      category: this.activeCategoryId,
      transaction_date: date,
      notes: notes,
      receipt_image_url: this.receiptImageUrl,
      items: this.currentItems
    };

    if (this.editingTransactionId) {
      payload.id = this.editingTransactionId;
    }

    try {
      if (navigator.onLine) {
        const laravelUrl = this.editingTransactionId ? `transactions/${this.editingTransactionId}` : 'transactions';
        const phpUrl = 'api/transactions.php';
        const endpoint = this.getEndpoint(laravelUrl, phpUrl);
        const method = this.editingTransactionId ? 'PUT' : 'POST';

        const res = await fetch(endpoint, {
          method: method,
          headers: this.getHeaders(),
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.status === 'success') {
          this.showToast(this.editingTransactionId ? 'Transaksi diperbarui!' : 'Transaksi berhasil dicatat!', 'success');
        } else {
          throw new Error(json.message);
        }
      } else {
        const action = this.editingTransactionId ? 'update' : 'create';
        await localDB.enqueueOffline(action, payload);
        this.showToast('Tersimpan offline. Akan disinkronkan saat online.', 'info');
      }

      this.closeModal('transactionModal');
      await this.loadData();
    } catch (err) {
      console.error('Submit error:', err);
      this.showToast('Gagal menyimpan ke server, dialihkan ke offline: ' + err.message, 'error');
      await localDB.enqueueOffline('create', payload);
      this.closeModal('transactionModal');
      await this.loadData();
    }
  }

  async handleDeleteTransaction() {
    if (!this.editingTransactionId) return;

    if (!confirm('Apakah Anda yakin ingin menghapus transaksi ini?')) return;

    try {
      if (navigator.onLine) {
        const laravelUrl = `transactions/${this.editingTransactionId}`;
        const phpUrl = `api/transactions.php?id=${this.editingTransactionId}`;
        const endpoint = this.getEndpoint(laravelUrl, phpUrl);

        const res = await fetch(endpoint, {
          method: 'DELETE',
          headers: this.getHeaders()
        });
        const json = await res.json();
        if (json.status === 'success') {
          this.showToast('Transaksi berhasil dihapus.', 'success');
        }
      } else {
        await localDB.enqueueOffline('delete', { id: this.editingTransactionId });
        this.showToast('Transaksi dihapus secara offline.', 'info');
      }

      this.closeModal('transactionModal');
      await this.loadData();
    } catch (err) {
      this.showToast('Error menghapus transaksi: ' + err.message, 'error');
    }
  }

  // ----------------------------------------------------
  // OCR Scanner Controls
  // ----------------------------------------------------
  async openScannerModal() {
    this.openModal('scannerModal');
    const video = document.getElementById('cameraVideo');
    try {
      await receiptScanner.startCamera(video);
    } catch (err) {
      console.warn('Camera start:', err);
    }
  }

  closeScannerModal() {
    receiptScanner.stopCamera();
    this.closeModal('scannerModal');
  }

  async captureAndProcessScanner() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('scannerCanvas');
    const progressBox = document.getElementById('ocrProgressBox');
    const progressBar = document.getElementById('ocrProgressBar');
    const progressText = document.getElementById('ocrProgressText');

    progressBox.style.display = 'block';
    progressText.textContent = 'Mengambil gambar & memproses OCR...';
    progressBar.style.width = '15%';

    const imageSource = receiptScanner.captureFrame(video, canvas);
    this.receiptBase64 = imageSource;

    try {
      const result = await receiptScanner.recognize(imageSource, (percent) => {
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `Menganalisis teks nota (${percent}%)...`;
      });

      this.closeScannerModal();
      this.showToast('Nota berhasil dipindai!', 'success');

      this.openTransactionModal({
        type: 'pengeluaran',
        amount: result.parsed.amount,
        date: result.parsed.date,
        category: result.parsed.category,
        notes: result.parsed.notes,
        candidates: result.parsed.candidates,
        items: result.parsed.items || []
      });

      this.uploadReceiptPhotoBase64(imageSource);

    } catch (err) {
      console.error('OCR Error:', err);
      this.showToast('Gagal memproses nota: ' + err.message, 'error');
    } finally {
      progressBox.style.display = 'none';
    }
  }

  async handleReceiptUploadInput(e) {
    const file = e.target.files[0];
    if (!file) return;

    const progressBox = document.getElementById('ocrProgressBox');
    const progressBar = document.getElementById('ocrProgressBar');
    const progressText = document.getElementById('ocrProgressText');

    progressBox.style.display = 'block';
    progressText.textContent = 'Membaca file nota...';
    progressBar.style.width = '20%';

    const reader = new FileReader();
    reader.onload = async (evt) => {
      const imageSource = evt.target.result;
      this.receiptBase64 = imageSource;

      try {
        const result = await receiptScanner.recognize(imageSource, (percent) => {
          progressBar.style.width = `${percent}%`;
          progressText.textContent = `Menganalisis teks nota (${percent}%)...`;
        });

        this.closeScannerModal();
        this.showToast('Nota berhasil dipindai!', 'success');

        this.openTransactionModal({
          type: 'pengeluaran',
          amount: result.parsed.amount,
          date: result.parsed.date,
          category: result.parsed.category,
          notes: result.parsed.notes,
          candidates: result.parsed.candidates,
          items: result.parsed.items || []
        });

        this.uploadReceiptPhotoBase64(imageSource);

      } catch (err) {
        console.error('OCR Error:', err);
        this.showToast('Gagal memproses nota: ' + err.message, 'error');
      } finally {
        progressBox.style.display = 'none';
      }
    };
    reader.readAsDataURL(file);
  }

  async uploadReceiptPhotoBase64(base64Data) {
    if (!navigator.onLine) return;
    try {
      const endpoint = this.getEndpoint('upload', 'api/upload.php');
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify({ image_base64: base64Data })
      });
      const json = await res.json();
      if (json.status === 'success') {
        this.receiptImageUrl = json.url;
      }
    } catch (e) {
      console.warn('Background upload receipt failed:', e);
    }
  }

  // ----------------------------------------------------
  // Sync Queue (Offline -> Online)
  // ----------------------------------------------------
  async syncPendingData() {
    if (typeof localDB === 'undefined' || !navigator.onLine) return;

    const queue = await localDB.getQueue();
    if (queue.length === 0) return;

    this.showToast(`Menyinkronkan ${queue.length} transaksi tertunda ke MySQL...`, 'info');

    try {
      const itemsToSync = queue
        .filter((q) => q.action === 'create')
        .map((q) => q.data);

      if (itemsToSync.length > 0) {
        const endpoint = this.getEndpoint('transactions', 'api/transactions.php');
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: this.getHeaders(),
          body: JSON.stringify({ sync: true, items: itemsToSync })
        });
        const json = await res.json();
        if (json.status === 'success') {
          await localDB.clearQueue();
          this.showToast('Semua transaksi berhasil disinkronkan ke MySQL!', 'success');
          await this.loadData();
        }
      }
    } catch (e) {
      console.error('Sync failed:', e);
    }
  }

  // ----------------------------------------------------
  // Export Data to CSV
  // ----------------------------------------------------
  exportToCSV() {
    if (!this.transactions || this.transactions.length === 0) {
      this.showToast('Tidak ada data transaksi untuk diekspor.', 'error');
      return;
    }

    const headers = ['ID', 'Tipe', 'Nominal (Rp)', 'Subtotal', 'Diskon', 'Kategori', 'Tanggal', 'Catatan', 'Rincian Barang'];
    const rows = this.transactions.map((t) => {
      const itemDetails = (t.items || []).map((it) => `${it.item_name} (${it.qty}x @${it.unit_price})`).join('; ');
      return [
        t.id,
        t.type,
        t.amount,
        t.subtotal || t.amount,
        t.discount || 0,
        `"${(t.category || '').replace(/"/g, '""')}"`,
        t.transaction_date,
        `"${(t.notes || '').replace(/"/g, '""')}"`,
        `"${itemDetails.replace(/"/g, '""')}"`
      ];
    });

    const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + 
      [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `myCost_Transaksi_${this.currentMonth}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    this.showToast('Data CSV berhasil diunduh!', 'success');
  }

  // ----------------------------------------------------
  // DB Status Modal
  // ----------------------------------------------------
  async openDbStatusModal() {
    this.openModal('dbStatusModal');
    const container = document.getElementById('dbStatusContent');
    container.innerHTML = '<p><i class="fa-solid fa-spinner fa-spin"></i> Memeriksa koneksi database MySQL...</p>';

    try {
      const endpoint = this.getEndpoint('../setup-check', 'api/setup.php');
      const res = await fetch(endpoint);
      const json = await res.json();
      if (json.connected) {
        container.innerHTML = `
          <div style="text-align: center; padding: 10px;">
            <i class="fa-solid fa-circle-check" style="font-size: 48px; color: var(--income); margin-bottom: 12px;"></i>
            <h3 style="margin-bottom: 8px;">Database MySQL Terhubung!</h3>
            <p style="color: var(--text-secondary); font-size: 14px;">Total Transaksi: <strong>${json.total_transactions}</strong></p>
            <p style="color: var(--income); font-size: 13px; margin-top: 8px;"><i class="fa-solid fa-shield-halved"></i> Multi-User & Itemized Data Aktif</p>
          </div>
        `;
      } else {
        container.innerHTML = `
          <div style="text-align: center; padding: 10px;">
            <i class="fa-solid fa-circle-exclamation" style="font-size: 48px; color: var(--expense); margin-bottom: 12px;"></i>
            <h3 style="margin-bottom: 8px;">MySQL Belum Terhubung</h3>
            <p style="color: var(--text-secondary); font-size: 14px;">${json.message}</p>
          </div>
        `;
      }
    } catch (e) {
      container.innerHTML = `
        <div style="text-align: center; padding: 10px;">
          <i class="fa-solid fa-triangle-exclamation" style="font-size: 48px; color: var(--warning); margin-bottom: 12px;"></i>
          <h3 style="margin-bottom: 8px;">Status Offline</h3>
          <p style="color: var(--text-secondary); font-size: 14px;">Aplikasi sedang beroperasi dalam mode offline (IndexedDB).</p>
        </div>
      `;
    }
  }

  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
  }

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
    if (modalId === 'scannerModal') {
      receiptScanner.stopCamera();
    }
  }

  showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
      success: 'fa-circle-check',
      error: 'fa-circle-exclamation',
      info: 'fa-circle-info'
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <i class="fa-solid ${icons[type] || 'fa-bell'}"></i>
      <span>${message}</span>
    `;

    container.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(50px)';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  showLoading(isLoading) {
    const listEl = document.getElementById('transactionsList');
    if (isLoading && listEl && this.transactions.length === 0) {
      listEl.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-circle-notch fa-spin"></i>
          <p>Memuat data...</p>
        </div>
      `;
    }
  }
}

let app;
document.addEventListener('DOMContentLoaded', () => {
  app = new MyCostApp();
});
