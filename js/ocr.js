// js/ocr.js - Universal Multi-Format Intelligent Receipt Parser (Indomaret/Alfamart, Invoices/Nota Pembelian, Cafe/Resto, Minimarket)

class ReceiptScanner {
  constructor() {
    this.videoStream = null;
    this.isProcessing = false;
    this.availableCameras = [];
    this.selectedCameraId = null;
  }

  // Get list of available video devices, prioritizing real RGB cameras (ignoring IR/Windows Hello cameras)
  async getAvailableCameras() {
    try {
      if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
        return [];
      }
      const devices = await navigator.mediaDevices.enumerateDevices();
      const videoDevices = devices.filter((d) => d.kind === 'videoinput');

      // Filter out IR / Infrared / Windows Hello Face sensors that cause black screens on laptops
      this.availableCameras = videoDevices.map((d, index) => {
        const label = d.label || `Kamera ${index + 1}`;
        const isIR = /ir\b|infra|infrared|hello\s*face|depth/i.test(label);
        return {
          deviceId: d.deviceId,
          label: label,
          isIR: isIR
        };
      });

      return this.availableCameras;
    } catch (e) {
      console.warn('Could not enumerate cameras:', e);
      return [];
    }
  }

  // Start camera stream on a video element or video ID
  async startCamera(videoElementOrId, preferredDeviceId = null) {
    try {
      this.stopCamera();

      const videoElement = typeof videoElementOrId === 'string' 
        ? document.getElementById(videoElementOrId) 
        : videoElementOrId;

      if (!videoElement) {
        throw new Error('Elemen video kamera tidak ditemukan');
      }

      // Prepare video attributes for modern desktop & mobile browsers
      videoElement.setAttribute('autoplay', '');
      videoElement.setAttribute('playsinline', '');
      videoElement.setAttribute('muted', '');
      videoElement.muted = true;

      // Check device list
      await this.getAvailableCameras();

      // Populate camera switcher select if present
      const camSelect = document.getElementById('cameraSourceSelect');
      if (camSelect && this.availableCameras.length > 0) {
        camSelect.style.display = 'block';
        camSelect.innerHTML = this.availableCameras.map((c) => `
          <option value="${c.deviceId}" ${c.isIR ? 'style="color:#888;"' : ''}>
            ${c.label} ${c.isIR ? '(Sensor IR)' : ''}
          </option>
        `).join('');

        if (preferredDeviceId) {
          camSelect.value = preferredDeviceId;
        } else {
          // Select first non-IR camera
          const rgbCam = this.availableCameras.find((c) => !c.isIR);
          if (rgbCam) {
            camSelect.value = rgbCam.deviceId;
            preferredDeviceId = rgbCam.deviceId;
          }
        }
      }

      // Try multiple constraint strategies progressively
      let stream = null;
      const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

      // Strategy 1: Specific RGB camera device ID if available
      if (preferredDeviceId) {
        try {
          stream = await navigator.mediaDevices.getUserMedia({
            video: {
              deviceId: { exact: preferredDeviceId },
              width: { ideal: 1280 },
              height: { ideal: 720 }
            }
          });
        } catch (e1) {
          console.warn('Strategy 1 (exact deviceId) failed, trying fallback...', e1);
        }
      }

      // Strategy 2: Facing mode (environment on mobile, user on laptop/desktop)
      if (!stream) {
        try {
          const facing = isMobile ? { ideal: 'environment' } : 'user';
          stream = await navigator.mediaDevices.getUserMedia({
            video: {
              facingMode: facing,
              width: { ideal: 1280 },
              height: { ideal: 720 }
            }
          });
        } catch (e2) {
          console.warn('Strategy 2 (facingMode) failed, trying generic video...', e2);
        }
      }

      // Strategy 3: Standard fallback video: true
      if (!stream) {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
      }

      this.videoStream = stream;
      videoElement.srcObject = stream;

      // Wait for stream metadata to be loaded then play
      await new Promise((resolve) => {
        videoElement.onloadedmetadata = () => {
          videoElement.play().then(resolve).catch(resolve);
        };
        setTimeout(() => {
          videoElement.play().then(resolve).catch(resolve);
        }, 300);
      });

      return true;
    } catch (err) {
      console.error('Camera access error:', err);
      throw new Error('Tidak dapat mengakses webcam laptop. Pastikan izin kamera aktif dan pilih kamera non-IR.');
    }
  }

  // Switch camera when user selects from dropdown
  async switchCamera(videoElementOrId, deviceId) {
    this.selectedCameraId = deviceId;
    return await this.startCamera(videoElementOrId, deviceId);
  }

  // Stop camera stream
  stopCamera() {
    if (this.videoStream) {
      this.videoStream.getTracks().forEach((track) => track.stop());
      this.videoStream = null;
    }
  }

  // Capture frame from video to canvas with image preprocessing
  captureFrame(videoElement, canvasElement) {
    const video = typeof videoElement === 'string' ? document.getElementById(videoElement) : videoElement;
    const canvas = typeof canvasElement === 'string' ? document.getElementById(canvasElement) : canvasElement;
    if (!video || !canvas) return null;

    const context = canvas.getContext('2d');
    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.95);
  }

  // Pre-process image on canvas to boost OCR accuracy (Grayscale, High Contrast, Sharp Text)
  preprocessImage(imageElement, canvas) {
    const ctx = canvas.getContext('2d');
    const w = imageElement.naturalWidth || imageElement.width || 1200;
    const h = imageElement.naturalHeight || imageElement.height || 1600;
    canvas.width = w;
    canvas.height = h;
    ctx.drawImage(imageElement, 0, 0, w, h);

    const imgData = ctx.getImageData(0, 0, w, h);
    const data = imgData.data;

    // Convert to grayscale and enhance contrast for sharp receipt text
    const contrast = 1.45;
    const factor = (259 * (contrast * 255 + 255)) / (255 * (259 - contrast * 255));

    for (let i = 0; i < data.length; i += 4) {
      const avg = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
      const adjusted = factor * (avg - 128) + 128;
      const finalVal = Math.min(255, Math.max(0, adjusted));
      data[i] = finalVal;
      data[i + 1] = finalVal;
      data[i + 2] = finalVal;
    }

    ctx.putImageData(imgData, 0, 0);
    return canvas.toDataURL('image/jpeg', 0.94);
  }

  // Main recognition method (aliases to processImage)
  async processImage(imageSource, onProgress = null) {
    return await this.recognize(imageSource, onProgress);
  }

  // Process image using Tesseract.js with progress callbacks & Universal parser
  async recognize(imageSource, onProgress = null) {
    if (this.isProcessing) return null;
    this.isProcessing = true;

    try {
      if (typeof Tesseract === 'undefined') {
        throw new Error('Library Tesseract.js belum dimuat. Periksa koneksi internet.');
      }

      let processedSrc = imageSource;
      try {
        const offCanvas = document.createElement('canvas');
        const img = new Image();
        img.src = imageSource;
        await new Promise((res) => (img.onload = res));
        processedSrc = this.preprocessImage(img, offCanvas);
      } catch (e) {
        console.warn('Preprocessing skipped:', e);
      }

      const result = await Tesseract.recognize(
        processedSrc,
        'ind+eng',
        {
          logger: (m) => {
            if (onProgress) {
              const progress = m.progress || 0;
              let status = 'Sedang memproses struk...';
              if (m.status === 'loading tesseract core') status = 'Memuat engine OCR...';
              else if (m.status === 'loading language traineddata') status = 'Memuat data bahasa Indonesia...';
              else if (m.status === 'recognizing text') status = `Mengenali teks nota... (${Math.round(progress * 100)}%)`;
              onProgress({ progress, status });
            }
          }
        }
      );

      const parsedData = this.parseReceiptText(result.data.text);
      return {
        success: true,
        rawText: result.data.text,
        confidence: result.data.confidence,
        store_name: parsedData.detectedMerchant,
        total_amount: parsedData.amount,
        subtotal: parsedData.subtotal,
        discount: parsedData.discount,
        transaction_date: parsedData.date,
        category: parsedData.category,
        notes: parsedData.notes,
        items: parsedData.items || [],
        all_detected_numbers: parsedData.candidates || []
      };
    } catch (err) {
      console.error('OCR Processing error:', err);
      return {
        success: false,
        message: err.message,
        total_amount: 0,
        items: []
      };
    } finally {
      this.isProcessing = false;
    }
  }

  // Universal Indonesian & Global receipt parser
  parseReceiptText(text) {
    if (!text) {
      return { 
        amount: 0, 
        subtotal: 0, 
        discount: 0, 
        date: new Date().toISOString().split('T')[0], 
        category: 'Belanja', 
        notes: 'Scan Nota', 
        detectedMerchant: '', 
        candidates: [], 
        items: [] 
      };
    }

    const rawLines = text.split('\n').map((l) => l.trim()).filter((l) => l.length > 0);
    const items = [];
    let totalDiscount = 0;
    let serviceFee = 0;
    let taxFee = 0;
    let grandTotal = 0;
    let subtotal = 0;
    let detectedDate = null;
    let detectedStore = '';
    let category = 'Belanja';
    let finishedItems = false;
    const candidates = [];

    // Footers / metadata
    const stopRegex = /^(?:harga\s*jual|total\s*pembayaran|total\s*harga|jumlah\s*pembelian|subtotal|payment\s*details|payment\s*method|metode\s*pembayaran|terima\s*kasih|thank\s*you|layanan\s*konsumen|call\b|sms\b|email\b|info\s*lengkap|www\.|pos\b|catatan|paid\b|tunai|cash|kembali|kembalian|anda\s*hemat)/i;
    
    // Header lines to skip from item extraction
    const headerRegex = /^(?:nota\s*pembelian|faktur|invoice|struk|nota|no\b|nomor\b|alamat|telepon|pemasok|tanggal|tgl|date|cashier|trx\s*id|customer|pax|dine\s*in|table|take\s*away|npwp|jl\.|=====+|-----+|\*\*\*\*\*+)/i;

    // 1. Detect Store Name & Category from top lines
    for (let i = 0; i < Math.min(rawLines.length, 8); i++) {
      const line = rawLines[i];
      if (/pemasok\s*:\s*(.+)$/i.test(line)) {
        const match = line.match(/pemasok\s*:\s*(.+)$/i);
        detectedStore = match ? match[1].trim() : '';
        break;
      } else if (/indomaret|indomarco/i.test(line)) {
        detectedStore = 'Indomaret';
        category = 'Belanja';
        break;
      } else if (/alfamart/i.test(line)) {
        detectedStore = 'Alfamart';
        category = 'Belanja';
        break;
      } else if (/alfamidi/i.test(line)) {
        detectedStore = 'Alfamidi';
        category = 'Belanja';
        break;
      } else if (/superindo/i.test(line)) {
        detectedStore = 'Superindo';
        category = 'Belanja';
        break;
      } else if (/restaurant|resto|cafe|bistro|kitchen|eatery|bakso|kopi\s*makmur/i.test(line)) {
        detectedStore = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        category = 'Makanan & Minuman';
        break;
      } else if (i === 0 && !/^[=\-*#\d\s]+$/.test(line) && line.length > 3 && !/nota\s*pembelian/i.test(line)) {
        detectedStore = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
      }
    }

    if (!detectedStore && rawLines.length > 0) {
      detectedStore = 'Struk Belanja';
    }

    // 2. Parse Line-by-Line
    for (const rawLine of rawLines) {
      // Normalize currency formatting and spacing
      let line = rawLine
        .replace(/([.,])\s+([0-9])/g, '$1$2')
        .replace(/([^\s])(rp\.?)/gi, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim();

      // Date detection (Indonesian / English / Numeric formats)
      if (!detectedDate) {
        // Pattern 1: "19 Agustus 2023" or "19-Aug-2023"
        const dm1 = line.match(/\b([0-9]{1,2})\s+([a-zA-Z]{3,10})\s+([0-9]{4})\b/i);
        if (dm1) {
          const monthNum = this.monthNameToNumber(dm1[2]);
          detectedDate = `${dm1[3]}-${monthNum}-${String(dm1[1]).padStart(2, '0')}`;
        }
        // Pattern 2: "Oct 23 2023"
        const dm2 = line.match(/\b([a-zA-Z]{3,10})\s+([0-9]{1,2})\s+([0-9]{4})\b/i);
        if (dm2) {
          const monthNum = this.monthNameToNumber(dm2[1]);
          detectedDate = `${dm2[3]}-${monthNum}-${String(dm2[2]).padStart(2, '0')}`;
        }
        // Pattern 3: "14. 09. 16" or "14/09/2023"
        const dm3 = line.match(/\b(\d{1,2})[.\/-]\s*(\d{1,2})[.\/-]\s*(\d{2,4})\b/);
        if (dm3) {
          let yr = dm3[3];
          if (yr.length === 2) yr = '20' + yr;
          detectedDate = `${yr}-${String(dm3[2]).padStart(2, '0')}-${String(dm3[1]).padStart(2, '0')}`;
        }
      }

      // Grand Total Detection (e.g. "Total Pembayaran : Rp 2.740.250" or "Total Rp201.025" or "TOTAL : 33,900")
      const totalMatch = line.match(/^(?:total\s*pembayaran|grand\s*total|total)\s*[:=]?\s*(?:rp\.?\s*)?([0-9.,\s]+)/i);
      if (totalMatch) {
        const amt = this.cleanAmount(totalMatch[1]);
        if (amt > 0) {
          grandTotal = amt;
          candidates.push(amt);
        }
        finishedItems = true;
        continue;
      }

      // Subtotal / Jumlah Pembelian Detection (e.g. "Jumlah Pembelian : Rp 2.825.000" or "Subtotal Rp200.000" or "HARGA JUAL : 35,200")
      const subtotalMatch = line.match(/^(?:jumlah\s*pembelian|harga\s*jual|subtotal)\s*[:=]?\s*(?:rp\.?\s*)?([0-9.,\s]+)/i);
      if (subtotalMatch) {
        const amt = this.cleanAmount(subtotalMatch[1]);
        if (amt > 0) {
          subtotal = amt;
          candidates.push(amt);
        }
        finishedItems = true;
        continue;
      }

      // Service Fee (e.g. "Service Rp12.750")
      const serviceMatch = line.match(/^service(?:\s*charge|\s*\d+%)?\s*[:=]?\s*(?:rp\.?\s*)?([0-9.,\s]+)/i);
      if (serviceMatch) {
        const svc = this.cleanAmount(serviceMatch[1]);
        if (svc > 0) {
          serviceFee = svc;
          items.push({
            item_name: 'Biaya Layanan (Service)',
            qty: 1,
            unit_price: svc,
            discount: 0,
            total_price: svc
          });
        }
        continue;
      }

      // Tax / PB1 (e.g. "PB1 Rp18.275" or "Pajak Resto Rp18.275")
      const taxMatch = line.match(/^(?:pb1|pajak|tax|ppn)(?:\s*resto|\s*\d+%)?\s*[:=]?\s*(?:rp\.?\s*)?([0-9.,\s]+)/i);
      if (taxMatch) {
        const tx = this.cleanAmount(taxMatch[1]);
        if (tx > 0) {
          taxFee = tx;
          items.push({
            item_name: 'Pajak Resto (PB1)',
            qty: 1,
            unit_price: tx,
            discount: 0,
            total_price: tx
          });
        }
        continue;
      }

      // Diskon Line Detection (e.g. "Diskon (3%) : Rp 84.750" or "Discount -Rp30.000" or "DISKON FRISIAN FLAG : (1,300)")
      const discountMatch = line.match(/(?:diskon|discount)(?:\s*\([^\)]*\)|\s+[a-zA-Z0-9\s]+)?\s*[:=]?\s*(?:[-−–]\s*)?(?:rp\.?\s*)?\(?([0-9.,\s]+)\)?/i);
      if (discountMatch) {
        const disc = this.cleanAmount(discountMatch[1]);
        if (disc > 0) {
          totalDiscount += disc;
        }
        continue;
      }

      // If reached footer or payment details, stop adding item rows
      if (finishedItems || stopRegex.test(line)) {
        finishedItems = true;
        continue;
      }

      // Skip headers, table column headers, and decorative lines
      if (headerRegex.test(line) || /^(?:no\s+nama|item\s+name|description|qty\s+price)/i.test(line)) {
        continue;
      }

      // Clean line from leading list numbering e.g. "1. Arabika Mandheling" -> "Arabika Mandheling"
      const itemLine = line.replace(/^\d+\.\s*/, '').trim();

      // Pattern A: [Name] [Qty] [Unit Price Rp...] [Total Price Rp...]
      // e.g. "Arabika Mandheling 10 Rp80.000 Rp 800.000" or "S/ROTI KRIM KEJU 72G 4 4500 18,000"
      const itemPatternA = itemLine.match(/^(.*?)\s+(\d+)\s+(?:rp\.?\s*)?([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]{1,7})\s+(?:rp\.?\s*)?([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]{1,7})$/i);
      if (itemPatternA) {
        const name = itemPatternA[1].trim();
        const qty = parseInt(itemPatternA[2]) || 1;
        const unitPrice = this.cleanAmount(itemPatternA[3]);
        const lineTotal = this.cleanAmount(itemPatternA[4]);

        if (qty > 0 && unitPrice > 0 && name.length >= 2 && !/^(?:tunai|kembali|total|diskon)/i.test(name)) {
          items.push({
            item_name: name,
            qty: qty,
            unit_price: unitPrice,
            discount: 0,
            total_price: lineTotal > 0 ? lineTotal : (qty * unitPrice)
          });
          candidates.push(lineTotal > 0 ? lineTotal : (qty * unitPrice));
          continue;
        }
      }

      // Pattern B1: Explicit x[Qty] or @[Qty] (e.g. "Fried Kwetiau x1 Rp99.000")
      const itemPatternB1 = itemLine.match(/^(.*?)\s+(?:x|@)\s*(\d+)\s+(?:rp\.?\s*)?([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]{3,7})$/i);
      if (itemPatternB1) {
        const name = itemPatternB1[1].trim();
        const qty = parseInt(itemPatternB1[2]) || 1;
        const price = this.cleanAmount(itemPatternB1[3]);

        if (price > 0 && name.length >= 2 && !/^(?:tunai|kembali|total|diskon|service|pb1|pajak|tax|subtotal)/i.test(name)) {
          items.push({
            item_name: name,
            qty: qty,
            unit_price: Math.round(price / qty),
            discount: 0,
            total_price: price
          });
          candidates.push(price);
          continue;
        }
      }

      // Pattern B2: Explicit [Qty]x (e.g. "Fried Kwetiau 2x Rp99.000")
      const itemPatternB2 = itemLine.match(/^(.*?)\s+(\d+)x\s+(?:rp\.?\s*)?([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]{3,7})$/i);
      if (itemPatternB2) {
        const name = itemPatternB2[1].trim();
        const qty = parseInt(itemPatternB2[2]) || 1;
        const price = this.cleanAmount(itemPatternB2[3]);

        if (price > 0 && name.length >= 2 && !/^(?:tunai|kembali|total|diskon|service|pb1|pajak|tax|subtotal)/i.test(name)) {
          items.push({
            item_name: name,
            qty: qty,
            unit_price: Math.round(price / qty),
            discount: 0,
            total_price: price
          });
          candidates.push(price);
          continue;
        }
      }

      // Pattern C: [Name with optional words/numbers/levels] [Price] (e.g. "Spicy lv 1 Rp1.000" or "Orange Juice Rp50.000")
      const itemPatternC = itemLine.match(/^(.*?)\s+(?:rp\.?\s*)?([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]{3,7})$/i);
      if (itemPatternC) {
        const name = itemPatternC[1].trim();
        const price = this.cleanAmount(itemPatternC[2]);

        if (price > 0 && name.length >= 2 && !/^(?:tunai|kembali|total|diskon|service|pb1|pajak|tax|subtotal|table|dine|notes)/i.test(name)) {
          items.push({
            item_name: name,
            qty: 1,
            unit_price: price,
            discount: 0,
            total_price: price
          });
          candidates.push(price);
        }
      }
    }

    // Final calculations
    if (grandTotal === 0 && subtotal > 0) {
      grandTotal = Math.max(0, subtotal - totalDiscount + serviceFee + taxFee);
    }
    if (grandTotal === 0 && items.length > 0) {
      grandTotal = Math.max(0, items.reduce((sum, it) => sum + (it.total_price || (it.qty * it.unit_price)), 0) - totalDiscount);
    }
    if (subtotal === 0 && items.length > 0) {
      subtotal = items.reduce((sum, it) => sum + (it.qty * it.unit_price), 0);
    }

    const uniqueCandidates = [...new Set([grandTotal, subtotal, ...candidates])].filter((c) => c >= 500);

    return {
      amount: grandTotal,
      subtotal: subtotal,
      discount: totalDiscount,
      date: detectedDate || new Date().toISOString().split('T')[0],
      category: category,
      notes: detectedStore ? `Belanja di ${detectedStore}` : 'Belanja Struk OCR',
      detectedMerchant: detectedStore,
      candidates: uniqueCandidates.slice(0, 6),
      items: items
    };
  }

  // Convert string number with commas, dots, spaces into valid integer
  cleanAmount(str) {
    if (!str) return 0;
    let clean = String(str).trim();
    // Fix space after comma or dot (e.g. "33, 900" -> "33,900")
    clean = clean.replace(/([.,])\s+([0-9])/g, '$1$2');
    clean = clean.replace(/[^\d.,]/g, '');

    if (clean.includes('.') && clean.includes(',')) {
      if (clean.lastIndexOf('.') > clean.lastIndexOf(',')) {
        clean = clean.replace(/,/g, '');
      } else {
        clean = clean.replace(/\./g, '').replace(',', '.');
      }
    } else if (clean.includes('.')) {
      const parts = clean.split('.');
      if (parts.length > 1 && parts[parts.length - 1].length === 3) {
        clean = clean.replace(/\./g, '');
      } else if (parts.length > 2) {
        clean = clean.replace(/\./g, '');
      }
    } else if (clean.includes(',')) {
      const parts = clean.split(',');
      if (parts.length > 1 && parts[parts.length - 1].length === 3) {
        clean = clean.replace(/,/g, '');
      } else {
        clean = clean.replace(/,/g, '.');
      }
    }

    const num = parseFloat(clean);
    return isNaN(num) ? 0 : Math.round(num);
  }

  monthNameToNumber(monthStr) {
    const m = String(monthStr).toLowerCase().substring(0, 3);
    const months = {
      jan: '01', feb: '02', mar: '03', apr: '04', mei: '05', may: '05',
      jun: '06', jul: '07', agu: '08', aug: '08', sep: '09', okt: '10',
      oct: '10', nov: '11', des: '12', dec: '12'
    };
    return months[m] || '01';
  }
}

// Global instance
const receiptScanner = new ReceiptScanner();
