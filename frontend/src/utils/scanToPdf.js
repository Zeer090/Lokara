/**
 * Mengonversi gambar hasil kamera/foto menjadi file PDF A4 standar (PDF 1.4, DCTDecode JPEG)
 * secara murni di browser tanpa library eksternal.
 */
export async function gambarKeScanPdf(fileOrBlob, { modeDokumen = true, namaFile = 'scan_surat_permohonan.pdf' } = {}) {
  const img = await muatGambar(fileOrBlob);

  // Resize proporsional maks 1240px (A4 150 DPI agar tajam & ukuran file kecil < 1 MB)
  const MAX_DIM = 1240;
  let w = img.naturalWidth || img.width;
  let h = img.naturalHeight || img.height;
  if (w > MAX_DIM || h > MAX_DIM) {
    const ratio = Math.min(MAX_DIM / w, MAX_DIM / h);
    w = Math.round(w * ratio);
    h = Math.round(h * ratio);
  }

  const canvas = document.createElement('canvas');
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext('2d');

  // Background putih
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, w, h);

  if (modeDokumen) {
    ctx.filter = 'contrast(1.22) brightness(1.06)';
  }
  ctx.drawImage(img, 0, 0, w, h);
  ctx.filter = 'none';

  const jpegDataUrl = canvas.toDataURL('image/jpeg', 0.85);
  const jpegBytes = base64KeUint8Array(jpegDataUrl.split(',')[1]);

  const pdfBytes = rakitPdfA4DariJpeg(jpegBytes, w, h);
  const pdfFile = new File([pdfBytes], namaFile, { type: 'application/pdf' });

  return {
    pdfFile,
    previewDataUrl: jpegDataUrl,
    sizeKb: (pdfFile.size / 1024).toFixed(1),
  };
}

function muatGambar(fileOrBlob) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(fileOrBlob);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = (err) => {
      URL.revokeObjectURL(url);
      reject(err);
    };
    img.src = url;
  });
}

function base64KeUint8Array(base64) {
  const bin = atob(base64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) {
    bytes[i] = bin.charCodeAt(i);
  }
  return bytes;
}

/**
 * Merakit dokumen PDF 1.4 valid berisi 1 halaman A4 (595.28 x 841.89 pt) dengan gambar JPEG.
 */
function rakitPdfA4DariJpeg(jpegBytes, imgW, imgH) {
  const enc = new TextEncoder();
  const pageW = 595.28;
  const pageH = 841.89;
  const margin = 20;
  const availW = pageW - margin * 2;
  const availH = pageH - margin * 2;
  const scale = Math.min(availW / imgW, availH / imgH);
  const drawW = (imgW * scale).toFixed(2);
  const drawH = (imgH * scale).toFixed(2);
  const drawX = ((pageW - Number(drawW)) / 2).toFixed(2);
  const drawY = ((pageH - Number(drawH)) / 2).toFixed(2);

  const contentStream = `q\n${drawW} 0 0 ${drawH} ${drawX} ${drawY} cm\n/Im0 Do\nQ\n`;
  const contentBytes = enc.encode(contentStream);

  const chunks = [];
  const offsets = [0]; // objek 0 dummy
  let totalLen = 0;

  const pushStr = (s) => {
    const b = enc.encode(s);
    chunks.push(b);
    totalLen += b.length;
  };
  const pushBytes = (b) => {
    chunks.push(b);
    totalLen += b.length;
  };

  pushStr('%PDF-1.4\n%\xFF\xFF\xFF\xFF\n');

  // Obj 1: Catalog
  offsets.push(totalLen);
  pushStr('1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');

  // Obj 2: Pages
  offsets.push(totalLen);
  pushStr('2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n');

  // Obj 3: Page
  offsets.push(totalLen);
  pushStr(
    `3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageW} ${pageH}] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>\nendobj\n`
  );

  // Obj 4: Image XObject (JPEG)
  offsets.push(totalLen);
  pushStr(
    `4 0 obj\n<< /Type /XObject /Subtype /Image /Width ${imgW} /Height ${imgH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${jpegBytes.length} >>\nstream\n`
  );
  pushBytes(jpegBytes);
  pushStr('\nendstream\nendobj\n');

  // Obj 5: Content Stream
  offsets.push(totalLen);
  pushStr(`5 0 obj\n<< /Length ${contentBytes.length} >>\nstream\n`);
  pushBytes(contentBytes);
  pushStr('endstream\nendobj\n');

  // XRef table
  const xrefOffset = totalLen;
  pushStr(`xref\n0 ${offsets.length}\n0000000000 65535 f \n`);
  for (let i = 1; i < offsets.length; i++) {
    pushStr(String(offsets[i]).padStart(10, '0') + ' 00000 n \n');
  }
  pushStr(
    `trailer\n<< /Size ${offsets.length} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`
  );

  const out = new Uint8Array(totalLen);
  let pos = 0;
  for (const c of chunks) {
    out.set(c, pos);
    pos += c.length;
  }
  return out;
}
