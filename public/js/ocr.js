// js/ocr.js - Tesseract.js OCR Scanner & Intelligent Receipt Parser

class ReceiptScanner {
  constructor() {
    this.videoStream = null;
    this.worker = null;
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
      throw new Error('Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan.');
    }
  }

  // Stop camera stream
  stopCamera() {
    if (this.videoStream) {
      this.videoStream.getTracks().forEach((track) => track.stop());
      this.videoStream = null;
    }
  }

  // Capture frame from video to canvas
  captureFrame(videoElement, canvasElement) {
    const context = canvasElement.getContext('2d');
    canvasElement.width = videoElement.videoWidth || 640;
    canvasElement.height = videoElement.videoHeight || 480;
    context.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
    return canvasElement.toDataURL('image/jpeg', 0.9);
  }

  // Process image using Tesseract.js with progress callbacks
  async recognize(imageSource, onProgress = null) {
    if (this.isProcessing) return null;
    this.isProcessing = true;

    try {
      if (typeof Tesseract === 'undefined') {
        throw new Error('Library Tesseract.js belum dimuat.');
      }

      const result = await Tesseract.recognize(
        imageSource,
        'ind+eng', // Indonesian & English
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

  // Intelligent text parser to extract Total Amount, Date, Category, and Merchant
  parseReceiptText(text) {
    const lines = text.split('\n').map((l) => l.trim()).filter((l) => l.length > 0);
    const fullText = text.toLowerCase();

    const result = {
      amount: null,
      date: null,
      category: 'Makanan & Minuman',
      notes: '',
      detectedMerchant: ''
    };

    // 1. Detect Merchant / Store Name from first few lines
    for (let i = 0; i < Math.min(lines.length, 4); i++) {
      const line = lines[i];
      if (/indomaret|alfamart|superindo|hypermart|transmart|yoma|famima|alfamidi/i.test(line)) {
        result.detectedMerchant = line;
        result.category = 'Belanja';
        break;
      } else if (/spbu|pertamina|shell|bp akr|petrol/i.test(line)) {
        result.detectedMerchant = line;
        result.category = 'Transportasi';
        break;
      } else if (/kopi|coffee|cafe|resto|restaurant|warung|dapur|bakso|mie|ayam|kitchen|eatery/i.test(line)) {
        result.detectedMerchant = line;
        result.category = 'Makanan & Minuman';
        break;
      } else if (/apotek|pharmacy|kimia farma|k24|guardian|watson|klinik|rs /i.test(line)) {
        result.detectedMerchant = line;
        result.category = 'Kesehatan';
        break;
      } else if (/cinema|xxi|cgv|cinepolis|game|timezone/i.test(line)) {
        result.detectedMerchant = line;
        result.category = 'Hiburan';
        break;
      }
    }

    if (!result.detectedMerchant && lines.length > 0) {
      result.detectedMerchant = lines[0]; // fallback top line
    }

    // 2. Extract Total Amount
    // Regex for common total labels: TOTAL, GRAND TOTAL, TOTAL BELANJA, TAGIHAN, TUNAI, CASH, BAYAR
    const totalKeywordsRegex = /(?:total\s*(?:belanja|bayar|transaksi|tagihan|akhir|gross|net)?|grand\s*total|subtotal|jumlah|tunai|cash|debit|qris|paid|amount)\s*[:=]?\s*(?:rp\.?|idr)?\s*([0-9.,]+)/i;

    let candidateAmounts = [];

    // Check line by line for total keywords
    for (const line of lines) {
      const match = line.match(totalKeywordsRegex);
      if (match && match[1]) {
        const cleaned = this.cleanAmount(match[1]);
        if (cleaned > 0) {
          candidateAmounts.push(cleaned);
        }
      }
    }

    // Fallback search for all numbers preceded by Rp or formatting
    if (candidateAmounts.length === 0) {
      const rpRegex = /(?:rp\.?|idr)\s*([0-9.,]+)/gi;
      let match;
      while ((match = rpRegex.exec(text)) !== null) {
        const cleaned = this.cleanAmount(match[1]);
        if (cleaned > 0) candidateAmounts.push(cleaned);
      }
    }

    // Select the best candidate (usually the largest number found around total or the last total match)
    if (candidateAmounts.length > 0) {
      // Filter out reasonable amount range
      const validAmounts = candidateAmounts.filter((n) => n >= 500 && n <= 100000000);
      if (validAmounts.length > 0) {
        result.amount = Math.max(...validAmounts);
      } else {
        result.amount = candidateAmounts[0];
      }
    }

    // 3. Extract Date
    // Formats: DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD, DD Month YYYY
    const dateRegexes = [
      /\b(20\d{2})[-/.](0[1-9]|1[0-2])[-/.](0[1-9]|[12]\d|3[01])\b/, // YYYY-MM-DD
      /\b(0[1-9]|[12]\d|3[01])[-/.](0[1-9]|1[0-2])[-/.](20\d{2}|\d{2})\b/, // DD-MM-YYYY or DD-MM-YY
      /\b(0[1-9]|[12]\d|3[01])\s+(jan|feb|mar|apr|mei|may|jun|jul|agu|aug|sep|okt|oct|nov|des|dec)[a-z]*\s+(20\d{2}|\d{2})\b/i // DD Mmm YYYY
    ];

    for (const line of lines) {
      // Check standard date
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

    // Default to today if date not found or invalid
    if (!result.date || isNaN(new Date(result.date).getTime())) {
      result.date = new Date().toISOString().split('T')[0];
    }

    // Compose default notes
    result.notes = result.detectedMerchant 
      ? `Nota: ${result.detectedMerchant.substring(0, 50)}` 
      : 'Hasil Scan Nota';

    return result;
  }

  // Convert string number with commas/periods into valid float
  cleanAmount(str) {
    if (!str) return 0;
    let clean = str.replace(/[^\d.,]/g, '');

    // Handle Indonesian format 50.000,00 or 50,000.00
    if (clean.includes('.') && clean.includes(',')) {
      if (clean.lastIndexOf('.') > clean.lastIndexOf(',')) {
        // 50,000.00
        clean = clean.replace(/,/g, '');
      } else {
        // 50.000,00
        clean = clean.replace(/\./g, '').replace(',', '.');
      }
    } else if (clean.includes('.')) {
      // Might be thousand separator 50.000
      const parts = clean.split('.');
      if (parts.length > 1 && parts[parts.length - 1].length === 3) {
        clean = clean.replace(/\./g, '');
      } else if (parts.length > 2) {
        clean = clean.replace(/\./g, '');
      }
    } else if (clean.includes(',')) {
      // 50,000
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
