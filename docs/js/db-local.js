// js/db-local.js - IndexedDB Manager for myCost Offline Storage & Sync Queue

const DB_NAME = 'mycost_idb';
const DB_VERSION = 1;

class LocalDB {
  constructor() {
    this.db = null;
    this.readyPromise = this.init();
  }

  init() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        
        // Object store for cached transactions
        if (!db.objectStoreNames.contains('transactions')) {
          const transStore = db.createObjectStore('transactions', { keyPath: 'id' });
          transStore.createIndex('transaction_date', 'transaction_date', { unique: false });
          transStore.createIndex('type', 'type', { unique: false });
        }

        // Object store for pending offline operations (sync queue)
        if (!db.objectStoreNames.contains('sync_queue')) {
          db.createObjectStore('sync_queue', { keyPath: 'local_id', autoIncrement: true });
        }
      };

      request.onsuccess = (event) => {
        this.db = event.target.result;
        resolve(this.db);
      };

      request.onerror = (event) => {
        console.error('IndexedDB error:', event.target.error);
        reject(event.target.error);
      };
    });
  }

  async ensureReady() {
    if (!this.db) {
      await this.readyPromise;
    }
  }

  // Save list of transactions from server to local cache
  async cacheTransactions(transactions) {
    await this.ensureReady();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction('transactions', 'readwrite');
      const store = tx.objectStore('transactions');
      
      // Clear old cache before filling
      store.clear().onsuccess = () => {
        transactions.forEach((item) => {
          store.put(item);
        });
      };

      tx.oncomplete = () => resolve(true);
      tx.onerror = (e) => reject(e.target.error);
    });
  }

  // Get all cached transactions
  async getAllTransactions() {
    await this.ensureReady();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction('transactions', 'readonly');
      const store = tx.objectStore('transactions');
      const req = store.getAll();

      req.onsuccess = () => {
        const results = req.result || [];
        // Sort by date desc
        results.sort((a, b) => new Date(b.transaction_date) - new Date(a.transaction_date));
        resolve(results);
      };
      req.onerror = (e) => reject(e.target.error);
    });
  }

  // Add a pending offline item to sync queue
  async enqueueOffline(action, data) {
    await this.ensureReady();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(['sync_queue', 'transactions'], 'readwrite');
      const queueStore = tx.objectStore('sync_queue');
      const transStore = tx.objectStore('transactions');

      const queueItem = {
        action: action, // 'create', 'update', 'delete'
        data: data,
        timestamp: new Date().toISOString()
      };

      queueStore.add(queueItem);

      // If it's a create, also add a temporary item to local cache so user sees it right away
      if (action === 'create') {
        const tempItem = {
          ...data,
          id: 'temp_' + Date.now(),
          is_local_pending: true
        };
        transStore.add(tempItem);
      } else if (action === 'delete' && data.id) {
        transStore.delete(data.id);
      }

      tx.oncomplete = () => resolve(true);
      tx.onerror = (e) => reject(e.target.error);
    });
  }

  // Get all queued items
  async getQueue() {
    await this.ensureReady();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction('sync_queue', 'readonly');
      const store = tx.objectStore('sync_queue');
      const req = store.getAll();

      req.onsuccess = () => resolve(req.result || []);
      req.onerror = (e) => reject(e.target.error);
    });
  }

  // Clear sync queue after successful sync
  async clearQueue() {
    await this.ensureReady();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction('sync_queue', 'readwrite');
      const store = tx.objectStore('sync_queue');
      const req = store.clear();

      req.onsuccess = () => resolve(true);
      req.onerror = (e) => reject(e.target.error);
    });
  }
}

// Global instance
const localDB = new LocalDB();
