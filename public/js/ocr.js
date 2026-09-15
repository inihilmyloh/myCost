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
        account_hint: parsedData.detectedAccount,
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
        detectedAccount: '',
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
    let detectedPaymentAccount = '';
    let category = 'Belanja';
    let finishedItems = false;
    const candidates = [];

    // 1. Detect store name & payment method from full text
    const fullText = text.toLowerCase();
    if (/indomaret|indomarco|\bidm\b|kontak@indomaret/i.test(fullText)) {
      detectedStore = 'Indomaret';
      category = 'Belanja';
    } else if (/alfamart|alfamidi|alfa\s*midi/i.test(fullText)) {
      detectedStore = 'Alfamart';
      category = 'Belanja';
    } else if (/superindo/i.test(fullText)) {
      detectedStore = 'Superindo';
      category = 'Belanja';
    } else if (/hypermart|transmart|hero\b/i.test(fullText)) {
      detectedStore = 'Supermarket';
      category = 'Belanja';
    }

    // Detect payment account from receipt
    if (/\bseabank\b/i.test(fullText)) detectedPaymentAccount = 'Seabank';
    else if (/\bbca\b/i.test(fullText)) detectedPaymentAccount = 'BCA';
    else if (/\bmandiri\b/i.test(fullText)) detectedPaymentAccount = 'Mandiri';
    else if (/\bbri\b/i.test(fullText)) detectedPaymentAccount = 'BRI';
    else if (/\bbni\b/i.test(fullText)) detectedPaymentAccount = 'BNI';
    else if (/\bgopay\b/i.test(fullText)) detectedPaymentAccount = 'GoPay';
    else if (/shopeepay|shopee\s*pay/i.test(fullText)) detectedPaymentAccount = 'ShopeePay';
    else if (/\bovo\b/i.test(fullText)) detectedPaymentAccount = 'OVO';
    else if (/\bdana\b/i.test(fullText)) detectedPaymentAccount = 'DANA';
    else if (/\bqris\b/i.test(fullText)) detectedPaymentAccount = 'QRIS';

    // 2. Identify Store from first lines if not detected
    if (!detectedStore) {
      for (let i = 0; i < Math.min(rawLines.length, 6); i++) {
        const line = rawLines[i];
        if (/pemasok\s*:\s*(.+)$/i.test(line)) {
          const m = line.match(/pemasok\s*:\s*(.+)$/i);
          detectedStore = m ? m[1].trim() : '';
          break;
        } else if (/restaurant|resto|cafe|bistro|kitchen|eatery|bakso|kopi|warung|kedai/i.test(line)) {
          detectedStore = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
          category = 'Makanan & Minuman';
          break;
        } else if (i === 0 && !/^[=\-*#\d\s]+$/.test(line) && line.length > 3 && !/nota|faktur|struk|jl\.|alamat/i.test(line)) {
          detectedStore = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        }
      }
    }
    if (!detectedStore) detectedStore = 'Struk Belanja';

    // Helpers to filter address and metadata
    const isAddressLine = (str) => {
      return /^(?:jl\.|jln\b|jalan\b|kel\.|kelurahan\b|kec\.|kecamatan\b|kota\b|kab\.|kabupaten\b|gedongkiwo|mantrijeron|yogyakarta|bantul|jakarta|surabaya|semarang|bandung)/i.test(str)
        || /\b(?:kel\.|kelurahan|kec\.|kecamatan|kab\.|kabupaten)\s+[a-z]+/i.test(str)
        || /,\s*(?:kota|kab|yogyakarta|jakarta|bantul|indonesia)/i.test(str);
    };

    const isMetadataLine = (str) => {
      return /^(?:nota\s*pembelian|faktur|invoice|struk|no\b|nomor\b|alamat|telepon|pemasok|cashier|kasir|pos\s*\d+|trx\s*id|customer|pax|dine\s*in|table|take\s*away|npwp|shift|counter|terminal)/i.test(str)
        || /^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}.*\/.*\/.*$/i.test(str)
        || /^[=\-*_.\s#]{3,}$/.test(str);
    };

    const isFooterKeyword = (str) => {
      return /^(?:total\s*belanja|total\s*bayar|total\s*pembayaran|grand\s*total|\btotal\b|sub\s*total|subtotal|harga\s*jual|jumlah\s*harga|jumlah\s*pembelian|jumlah\s*total|non\s*tunai|tunai|cash|kembali|kembalian|anda\s*hemat|layanan\s*konsumen|kontak|telp|sms|seabank|mandiri|bca|bri|bni|gopay|ovo|dana|shopeepay|qris|ppn\b|dpp\b|dpp=|ppn=)/i.test(str);
    };

    for (let idx = 0; idx < rawLines.length; idx++) {
      const rawLine = rawLines[idx];
      let line = rawLine
        .replace(/([.,])\s+([0-9])/g, '$1$2')
        .replace(/([^\s])(rp\.?)/gi, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim();

      // Clean leading OCR noise (e.g. "- ", "1 ", "• ", ". ")
      const cleanedLine = line.replace(/^[-*•.,~1\s]+(?=(?:total|subtotal|harga\s*jual|non\s*tunai|tunai|cash|anda\s*hemat|ppn|layanan|kontak|sms|voucher|diskon))/i, '');

      // Date detection
      if (!detectedDate) {
        const dm1 = line.match(/\b([0-9]{1,2})\s+([a-zA-Z]{3,10})\s+([0-9]{4})\b/i);
        if (dm1) {
          const monthNum = this.monthNameToNumber(dm1[2]);
          detectedDate = `${dm1[3]}-${monthNum}-${String(dm1[1]).padStart(2, '0')}`;
        }
        const dm2 = line.match(/\b([a-zA-Z]{3,10})\s+([0-9]{1,2})\s+([0-9]{4})\b/i);
        if (dm2) {
          const monthNum = this.monthNameToNumber(dm2[1]);
          detectedDate = `${dm2[3]}-${monthNum}-${String(dm2[2]).padStart(2, '0')}`;
        }
        const dm3 = line.match(/\b(\d{1,2})[.\/-]\s*(\d{1,2})[.\/-]\s*(\d{2,4})\b/);
        if (dm3) {
          let yr = dm3[3];
          if (yr.length === 2) yr = '20' + yr;
          detectedDate = `${yr}-${String(dm3[2]).padStart(2, '0')}-${String(dm3[1]).padStart(2, '0')}`;
        }
      }

      // Check for voucher/discount line attached to the previous item
      // e.g. "VOUCHER : (5,600)" or "DISKON : (1,500)" or "PROMO -5000" or "(5,600)"
      const voucherMatch = cleanedLine.match(/^(?:voucher|diskon|discount|potongan|promo|hemat)\s*[:=]?\s*(?:[-−–]\s*)?(?:rp\.?\s*)?\(?([0-9.,\s]+)\)?/i);
      if (voucherMatch) {
        const disc = this.cleanAmount(voucherMatch[1]);
        if (disc > 0) {
          totalDiscount += disc;
          if (items.length > 0) {
            const lastItem = items[items.length - 1];
            lastItem.discount = (lastItem.discount || 0) + disc;
            lastItem.total_price = Math.max(0, (lastItem.qty * lastItem.unit_price) - lastItem.discount);
          }
        }
        continue;
      }

      // Grand Total Detection (e.g. "TOTAL BELANJA : 71,700" or "TOTAL : 71.700")
      const totalMatch = cleanedLine.match(/^(?:total\s*belanja|total\s*bayar|total\s*pembayaran|grand\s*total|\btotal\b)\s*[:=]?\s*(?:rp\.?\s*)?([0-9.,\s]+)/i);
      if (totalMatch) {
        const amt = this.cleanAmount(totalMatch[1]);
        if (amt > 0) {
          grandTotal = amt;
          candidates.push(amt);
        }
        finishedItems = true;
        continue;
      }

      // Subtotal / Harga Jual Detection (e.g. "HARGA JUAL : 73,784" or "SUBTOTAL : 71,700")
      const subtotalMatch = cleanedLine.match(/^(?:jumlah\s*pembelian|harga\s*jual|sub\s*total|subtotal|jumlah\s*harga)\s*[:=]?\s*(?:rp\.?\s*)?([0-9.,\s]+)/i);
      if (subtotalMatch) {
        const amt = this.cleanAmount(subtotalMatch[1]);
        if (amt > 0) {
          subtotal = amt;
          candidates.push(amt);
        }
        finishedItems = true;
        continue;
      }

      // Anda Hemat / Total Diskon
      const hematMatch = cleanedLine.match(/^(?:anda\s*hemat|total\s*diskon|total\s*hemat)\s*[:=]?\s*(?:rp\.?\s*)?([0-9.,\s]+)/i);
      if (hematMatch) {
        const disc = this.cleanAmount(hematMatch[1]);
        if (disc > 0 && totalDiscount === 0) {
          totalDiscount = disc;
        }
        finishedItems = true;
        continue;
      }

      // Payment methods in footer (e.g. "NON TUNAI : 71,700", "TUNAI : 100,000", "QRIS : 71,700")
      const paymentMatch = cleanedLine.match(/^(?:non\s*tunai|tunai|cash|kembali|kembalian|qris|debit|kredit|card|kartu|seabank|bca|mandiri|bri|bni|gopay|shopeepay|ovo|dana)\s*[:=]?\s*(?:rp\.?\s*)?([0-9.,\s]+)?/i);
      if (paymentMatch) {
        if (paymentMatch[1]) {
          const amt = this.cleanAmount(paymentMatch[1]);
          if (amt > 0) candidates.push(amt);
        }
        finishedItems = true;
        continue;
      }

      // PPN / DPP / Tax
      if (/^ppn\b|^dpp\b|^pajak|^pb1/i.test(cleanedLine)) {
        finishedItems = true;
        continue;
      }

      // If footer reached, skip any further item parsing
      if (finishedItems || isFooterKeyword(cleanedLine)) {
        finishedItems = true;
        continue;
      }

      // Skip address, header, metadata
      if (isAddressLine(cleanedLine) || isMetadataLine(cleanedLine)) {
        continue;
      }

      // Clean item line from leading bullet or number
      const itemLine = cleanedLine.replace(/^\d+[\.\)]\s*/, '').trim();

      // Pattern 1: Indomaret / Alfamart / Minimarket
      // [NAMA BARANG] [QTY] [HARGA SATUAN] [TOTAL HARGA]
      // e.g. "SO SOFT FLO BRS 700 1 19500 19,500"
      // e.g. "B.DAILY BRIGHT 100 1 40900 40,900"
      const indoMartPattern = itemLine.match(/^(.*?)\s+(\d{1,3})\s+([0-9]{1,3}(?:[.,][0-9]{3})*|[0-9]{3,7})\s+([0-9]{1,3}(?:[.,][0-9]{3})*|[0-9]{3,7})$/i);
      if (indoMartPattern) {
        const name = indoMartPattern[1].trim();
        const qty = parseInt(indoMartPattern[2]) || 1;
        const unitPrice = this.cleanAmount(indoMartPattern[3]);
        const lineTotal = this.cleanAmount(indoMartPattern[4]);

        if (qty > 0 && unitPrice > 0 && name.length >= 2 && !isAddressLine(name) && !isFooterKeyword(name)) {
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

      // Pattern 2: "Fried Kwetiau x1 Rp99.000" or "Fried Kwetiau 2x Rp99.000"
      const patternB = itemLine.match(/^(.*?)\s+(?:x|@)?\s*(\d+)(?:x)?\s+(?:rp\.?\s*)?([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]{3,7})$/i);
      if (patternB) {
        const name = patternB[1].trim();
        const qty = parseInt(patternB[2]) || 1;
        const price = this.cleanAmount(patternB[3]);

        if (price > 0 && name.length >= 2 && !isAddressLine(name) && !isFooterKeyword(name)) {
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

      // Pattern 3: Single item with trailing price "[Name] [Price]"
      const patternC = itemLine.match(/^(.*?)\s+(?:rp\.?\s*)?([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]{3,7})$/i);
      if (patternC) {
        const name = patternC[1].trim();
        const price = this.cleanAmount(patternC[2]);

        if (price > 0 && name.length >= 2 && !isAddressLine(name) && !isFooterKeyword(name) && !/,\s*$/.test(name)) {
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

    // Final total calculation
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
      detectedAccount: detectedPaymentAccount,
      candidates: uniqueCandidates.slice(0, 8),
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

