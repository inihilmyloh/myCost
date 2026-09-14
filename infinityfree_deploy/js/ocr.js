// js/ocr.js - Advanced Tesseract.js OCR Scanner & Intelligent Indonesian Receipt Parser

class ReceiptScanner {
  constructor() {
    this.videoStream = null;
    this.isProcessing = false;
  }

  // Start camera stream on a video element
  async startCamera(videoElement, facingMode = 'environment') {
    try {
      if (this.videoStream) {
        this.stopCamera();
      }

      const constraints = {
        video: {
          facingMode: { ideal: facingMode },
          width: { ideal: 1920 },
          height: { ideal: 1080 }
        }
      };

      this.videoStream = await navigator.mediaDevices.getUserMedia(constraints);
      videoElement.srcObject = this.videoStream;
      await videoElement.play();
      return true;
    } catch (err) {
      console.error('Camera access error:', err);
      throw new Error('Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan di browser.');
    }
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
    const context = canvasElement.getContext('2d');
    canvasElement.width = videoElement.videoWidth || 1280;
    canvasElement.height = videoElement.videoHeight || 720;
    context.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
    return canvasElement.toDataURL('image/jpeg', 0.95);
  }

  // Pre-process image on canvas to boost OCR accuracy (Grayscale, High Contrast)
  preprocessImage(imageElement, canvas) {
    const ctx = canvas.getContext('2d');
    canvas.width = imageElement.naturalWidth || imageElement.width || 1200;
    canvas.height = imageElement.naturalHeight || imageElement.height || 1600;
    ctx.drawImage(imageElement, 0, 0, canvas.width, canvas.height);

    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imgData.data;

    // Convert to grayscale and increase contrast
    const contrast = 1.35; // Contrast boost
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
    return canvas.toDataURL('image/jpeg', 0.92);
  }

  // Process image using Tesseract.js with progress callbacks
  async recognize(imageSource, onProgress = null) {
    if (this.isProcessing) return null;
    this.isProcessing = true;

    try {
      if (typeof Tesseract === 'undefined') {
        throw new Error('Library Tesseract.js belum dimuat. Periksa koneksi internet.');
      }

      // Pre-process using off-screen image element and canvas
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
            if (onProgress && m.status === 'recognizing text') {
              onProgress(Math.round((m.progress || 0) * 100));
            }
          }
        }
      );

      const parsedData = this.parseReceiptText(result.data.text);
      return {
        rawText: result.data.text,
        confidence: result.data.confidence,
        parsed: parsedData
      };
    } catch (err) {
      console.error('OCR Processing error:', err);
      throw err;
    } finally {
      this.isProcessing = false;
    }
  }

  // Intelligent Indonesian receipt parser (Indomaret, Alfamart, SPBU, Resto, Cafe, etc.)
  parseReceiptText(text) {
    if (!text) return { amount: null, date: new Date().toISOString().split('T')[0], category: 'Belanja', notes: 'Scan Nota', candidates: [] };

    const lines = text.split('\n').map((l) => l.trim()).filter((l) => l.length > 0);
    const fullText = text.toLowerCase();

    const result = {
      amount: null,
      date: null,
      category: 'Belanja',
      notes: '',
      detectedMerchant: '',
      candidates: []
    };

    // 1. Detect Merchant & Category
    for (let i = 0; i < Math.min(lines.length, 6); i++) {
      const line = lines[i];
      if (/indomaret|indomarco|alfamart|alfamidi|superindo|hypermart|transmart|yoma|famima|hero|lotte/i.test(line)) {
        result.detectedMerchant = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        result.category = 'Belanja';
        break;
      } else if (/spbu|pertamina|shell|bp akr|petrol|bensin|solar/i.test(line)) {
        result.detectedMerchant = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        result.category = 'Transportasi';
        break;
      } else if (/kopi|coffee|cafe|resto|restaurant|warung|dapur|bakso|mie|ayam|kitchen|eatery|mcdonald|kfc|starbucks|hokben|solaria|richeese/i.test(line)) {
        result.detectedMerchant = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        result.category = 'Makanan & Minuman';
        break;
      } else if (/apotek|pharmacy|kimia farma|k24|guardian|watson|klinik|rumah sakit|rs /i.test(line)) {
        result.detectedMerchant = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        result.category = 'Kesehatan';
        break;
      } else if (/cinema|xxi|cgv|cinepolis|game|timezone|karaoke/i.test(line)) {
        result.detectedMerchant = line.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        result.category = 'Hiburan';
        break;
      }
    }

    if (!result.detectedMerchant && lines.length > 0) {
      result.detectedMerchant = lines[0].replace(/[^a-zA-Z0-9\s]/g, '').trim();
    }

    // 2. Extract Numbers & Amounts
    // Regex for keywords: TOTAL, TOT, GRAND TOTAL, TOTAL BELANJA, HARGA JUAL, BAYAR, TUNAI, CASH, TAGIHAN, NETT, AMOUNT
    const keywordMatches = [];
    const allFoundNumbers = [];

    // Search specifically around total lines
    const totalLineRegex = /(?:total|tot\b|grand\s*total|sub\s*total|harga\s*jual|jumlah|tagihan|tunai|cash|bayar|netto?|amount|rp\.?|idr)/i;

    lines.forEach((line) => {
      // Find all number patterns in line e.g. 35.000, 35,000, 35000, 35 000, 35.500,00
      const matches = line.match(/(?:rp\.?|idr)?\s*([0-9]{1,3}(?:[.,\s][0-9]{3})+(?:[.,][0-9]{2})?|[0-9]{4,8})/gi);
      if (matches) {
        matches.forEach((m) => {
          const cleanNum = this.cleanAmount(m);
          if (cleanNum >= 500 && cleanNum <= 50000000) {
            allFoundNumbers.push(cleanNum);
            if (totalLineRegex.test(line)) {
              keywordMatches.push({ amount: cleanNum, line: line });
            }
          }
        });
      }
    });

    // Pick candidates
    const uniqueCandidates = [...new Set([...keywordMatches.map((k) => k.amount), ...allFoundNumbers])];
    result.candidates = uniqueCandidates.slice(0, 5);

    // Pick best amount:
    // If we matched keyword lines with TOTAL/GRAND TOTAL/HARGA JUAL, choose the largest or most relevant
    if (keywordMatches.length > 0) {
      // Prioritize lines with TOTAL over TUNAI/KEMBALIAN
      const exactTotal = keywordMatches.find((k) => /total\s*(?:belanja|harga|jual|bayar|akhir)?/i.test(k.line));
      if (exactTotal) {
        result.amount = exactTotal.amount;
      } else {
        result.amount = keywordMatches[keywordMatches.length - 1].amount;
      }
    } else if (allFoundNumbers.length > 0) {
      // Fallback: choose the most probable transaction amount (median or largest within realistic bracket)
      const filtered = allFoundNumbers.filter((n) => n >= 1000 && n <= 10000000);
      result.amount = filtered.length > 0 ? Math.max(...filtered) : allFoundNumbers[0];
    }

    // 3. Extract Date
    const dateRegexes = [
      /\b(20\d{2})[-/.](0[1-9]|1[0-2])[-/.](0[1-9]|[12]\d|3[01])\b/, // YYYY-MM-DD
      /\b(0[1-9]|[12]\d|3[01])[-/.](0[1-9]|1[0-2])[-/.](20\d{2}|\d{2})\b/, // DD-MM-YYYY or DD-MM-YY
      /\b(0[1-9]|[12]\d|3[01])\s+(jan|feb|mar|apr|mei|may|jun|jul|agu|aug|sep|okt|oct|nov|des|dec)[a-z]*\s+(20\d{2}|\d{2})\b/i
    ];

    for (const line of lines) {
      const match1 = line.match(dateRegexes[0]);
      if (match1) {
        result.date = `${match1[1]}-${match1[2]}-${match1[3]}`;
        break;
      }

      const match2 = line.match(dateRegexes[1]);
      if (match2) {
        let year = match2[3];
        if (year.length === 2) year = '20' + year;
        result.date = `${year}-${match2[2].padStart(2, '0')}-${match2[1].padStart(2, '0')}`;
        break;
      }

      const match3 = line.match(dateRegexes[2]);
      if (match3) {
        let year = match3[3];
        if (year.length === 2) year = '20' + year;
        const monthNum = this.monthNameToNumber(match3[2]);
        result.date = `${year}-${monthNum}-${match3[1].padStart(2, '0')}`;
        break;
      }
    }

    if (!result.date || isNaN(new Date(result.date).getTime())) {
      result.date = new Date().toISOString().split('T')[0];
    }

    // Default notes
    result.notes = result.detectedMerchant 
      ? `Nota: ${result.detectedMerchant.substring(0, 50)}` 
      : 'Belanja Minimarket / Nota';

    return result;
  }

  // Convert string number with commas, dots, spaces into valid integer
  cleanAmount(str) {
    if (!str) return 0;
    let clean = str.replace(/[^\d.,]/g, '').trim();

    // 50.000,00 or 50,000.00
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
    const m = monthStr.toLowerCase().substring(0, 3);
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
