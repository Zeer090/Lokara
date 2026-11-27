import React, { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import api from '../../api/klien.js';

const fetchLokasiList = async () => {
  const res = await api.get('/lokasi');
  return res.data?.data || res.data || [];
};

// Helper Format AM & PM
const formatAmPm = (timeStr) => {
  if (!timeStr) return null;
  const [hStr, mStr] = timeStr.split(':');
  const h = parseInt(hStr, 10);
  if (isNaN(h)) return null;
  const isPm = h >= 12;
  const period = isPm ? 'PM' : 'AM';
  const h12 = h % 12 === 0 ? 12 : h % 12;
  const padH12 = String(h12).padStart(2, '0');

  let keterangan = '';
  let ikon = '☀️';
  let badgeColor = 'bg-amber-50 text-amber-800 border-amber-200';

  if (h >= 0 && h < 5) {
    keterangan = 'Dini Hari';
    ikon = '🌌';
    badgeColor = 'bg-slate-100 text-slate-700 border-slate-300';
  } else if (h >= 5 && h < 12) {
    keterangan = 'Pagi';
    ikon = '☀️';
    badgeColor = 'bg-amber-50 text-amber-800 border-amber-200';
  } else if (h >= 12 && h < 15) {
    keterangan = 'Siang';
    ikon = '🌤️';
    badgeColor = 'bg-blue-50 text-blue-800 border-blue-200';
  } else if (h >= 15 && h < 18) {
    keterangan = 'Sore';
    ikon = '🌇';
    badgeColor = 'bg-orange-50 text-orange-800 border-orange-200';
  } else {
    keterangan = 'Malam';
    ikon = '🌙';
    badgeColor = 'bg-indigo-50 text-indigo-800 border-indigo-200';
  }

  return {
    time12: `${padH12}:${mStr || '00'} ${period}`,
    period,
    keterangan,
    ikon,
    badgeColor,
    h24: h,
    m: mStr || '00',
  };
};

const toggleAmPm = (timeStr) => {
  if (!timeStr) return '';
  const [hStr, mStr] = timeStr.split(':');
  let h = parseInt(hStr, 10);
  if (isNaN(h)) return timeStr;
  if (h < 12) {
    h = h + 12; // AM -> PM (misal 08:00 -> 20:00)
  } else {
    h = h - 12; // PM -> AM (misal 20:00 -> 08:00)
  }
  return `${String(h).padStart(2, '0')}:${mStr || '00'}`;
};

const Ajukan = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialJenis = searchParams.get('jenis') === 'ruangan' ? 'ruangan' : 'gedung';
  
  // State pemisahan: 'gedung' atau 'ruangan'
  const [tipePeminjaman, setTipePeminjaman] = useState(initialJenis);
  const [lokasiId, setLokasiId] = useState('');
  const [tanggal, setTanggal] = useState('');
  const [jamMulai, setJamMulai] = useState('');
  const [jamSelesai, setJamSelesai] = useState('');
  const [peserta, setPeserta] = useState('');
  const [tujuan, setTujuan] = useState('');
  const [fileSurat, setFileSurat] = useState(null);
  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [hasilSukses, setHasilSukses] = useState(null);

  const { data: daftarLokasi = [], isLoading: loadingLokasi } = useQuery({
    queryKey: ['lokasi'],
    queryFn: fetchLokasiList,
  });

  // Filter lokasi terpisah secara tegas
  const lokasiTersedia = daftarLokasi.filter((l) => l.jenis === tipePeminjaman);
  const selectedLokasi = daftarLokasi.find((l) => l.id === lokasiId);

  // Keterangan waktu AM / PM
  const infoMulai = formatAmPm(jamMulai);
  const infoSelesai = formatAmPm(jamSelesai);

  // Saat berpindah tab tipe, reset pilihan lokasi
  const handlePilihTipe = (tipe) => {
    setTipePeminjaman(tipe);
    setLokasiId('');
    setErrorMsg('');
    setSearchParams({ jenis: tipe });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrorMsg('');

    if (!lokasiId || !tanggal || !jamMulai || !jamSelesai || !tujuan) {
      setErrorMsg('Mohon lengkapi semua kolom yang wajib diisi.');
      return;
    }

    if (tipePeminjaman === 'gedung' && !fileSurat) {
      setErrorMsg('Peminjaman gedung wajib mengunggah surat permohonan resmi dalam format PDF.');
      return;
    }

    setLoading(true);

    try {
      const formData = new FormData();
      formData.append('lokasi_id', lokasiId);
      formData.append('mulai', `${tanggal} ${jamMulai}:00`);
      formData.append('selesai', `${tanggal} ${jamSelesai}:00`);
      formData.append('keperluan', tujuan);
      if (peserta) {
        formData.append('jumlah_peserta', peserta);
      }
      if (fileSurat && tipePeminjaman === 'gedung') {
        formData.append('surat', fileSurat);
      }

      const res = await api.post('/reservasi', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setHasilSukses(res.data);
    } catch (err) {
      const msg =
        err.response?.data?.galat ||
        err.response?.data?.pesan ||
        err.response?.data?.message ||
        'Gagal mengirim reservasi. Pastikan jadwal yang dipilih belum terisi.';
      setErrorMsg(msg);
    } finally {
      setLoading(false);
    }
  };

  if (hasilSukses) {
    const isApproved = hasilSukses.reservasi?.status === 'disetujui';
    return (
      <div className="max-w-lg mx-auto mt-14 p-8 bg-white rounded-2xl shadow-sm border border-slate-200 text-center">
        <div className="text-5xl mb-4">{isApproved ? '⚡' : '🏛️'}</div>
        <h2 className="text-2xl font-bold text-slate-800 mb-2">
          {isApproved ? 'Ruangan Berhasil Dipesan!' : 'Pengajuan Gedung Terkirim!'}
        </h2>
        <p className="text-sm text-slate-600 mb-6 leading-relaxed">
          {hasilSukses.pesan}
        </p>
        <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-left mb-6 space-y-1.5">
          <p><strong>Kode Booking:</strong> <span className="font-mono text-blue-600 font-bold">{hasilSukses.reservasi?.kode || '-'}</span></p>
          <p><strong>Kategori:</strong> <span className="capitalize font-semibold">{tipePeminjaman}</span></p>
          <p><strong>Lokasi:</strong> {hasilSukses.reservasi?.lokasi?.nama || selectedLokasi?.nama}</p>
          <p><strong>Jadwal:</strong> {hasilSukses.reservasi?.mulai} s/d {hasilSukses.reservasi?.selesai}</p>
          <p><strong>Status:</strong> <span className={`font-semibold capitalize ${isApproved ? 'text-emerald-600' : 'text-amber-600'}`}>{hasilSukses.reservasi?.status}</span></p>
        </div>
        <div className="flex gap-3 justify-center">
          <Link
            to="/app/reservasi"
            className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm"
          >
            Lihat Riwayat Saya
          </Link>
          <button
            onClick={() => { setHasilSukses(null); setLokasiId(''); setTujuan(''); setFileSurat(null); }}
            className="px-4 py-2.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-medium"
          >
            Ajukan Lagi
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-slate-900 mb-1">Pengajuan Peminjaman</h1>
        <p className="text-sm text-slate-500">
          Pilih kategori peminjaman di bawah ini sesuai kebutuhan acara Anda.
        </p>
      </div>

      {/* 🔘 PEMISAH FORM: TAB PEMILIHAN KATEGORI */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 p-1.5 bg-slate-200/70 rounded-2xl">
        <button
          type="button"
          onClick={() => handlePilihTipe('gedung')}
          className={`py-3.5 px-4 rounded-xl text-left transition-all flex items-center gap-3.5 ${
            tipePeminjaman === 'gedung'
              ? 'bg-white text-slate-900 shadow-sm border border-slate-200'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/60'
          }`}
        >
          <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-xl ${
            tipePeminjaman === 'gedung' ? 'bg-purple-100 text-purple-700' : 'bg-slate-200 text-slate-500'
          }`}>
            🏛️
          </div>
          <div>
            <h3 className="font-bold text-sm">Peminjaman Gedung</h3>
            <p className="text-[11px] text-slate-500">Kapasitas besar, wajib surat PDF, disetujui Bu Eci</p>
          </div>
        </button>

        <button
          type="button"
          onClick={() => handlePilihTipe('ruangan')}
          className={`py-3.5 px-4 rounded-xl text-left transition-all flex items-center gap-3.5 ${
            tipePeminjaman === 'ruangan'
              ? 'bg-white text-slate-900 shadow-sm border border-slate-200'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/60'
          }`}
        >
          <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-xl ${
            tipePeminjaman === 'ruangan' ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-500'
          }`}>
            ⚡
          </div>
          <div>
            <h3 className="font-bold text-sm">Peminjaman Ruangan</h3>
            <p className="text-[11px] text-slate-500">Kelas / Lab / Seminar, tanpa surat, instan disetujui</p>
          </div>
        </button>
      </div>

      {errorMsg && (
        <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-medium space-y-1">
          <div className="flex items-center gap-2">
            <span>⚠️</span>
            <span className="font-bold">{errorMsg}</span>
          </div>
          {errorMsg.toLowerCase().includes('akademik') && (
            <p className="text-[11px] text-rose-700 pl-6">
              💡 Ruangan ini telah dialokasikan untuk jadwal perkuliahan semester aktif oleh Bagian Akademik (BAAK). Silakan periksa jam yang kosong di menu Kalender atau pilih ruangan lain.
            </p>
          )}
        </div>
      )}

      {/* FORM SPESIFIK SESUAI KATEGORI YANG DIPILIH */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 sm:p-8 space-y-6">
        <div className={`p-4 rounded-xl border text-xs leading-relaxed ${
          tipePeminjaman === 'gedung'
            ? 'bg-purple-50/70 border-purple-200 text-purple-900'
            : 'bg-emerald-50/70 border-emerald-200 text-emerald-900'
        }`}>
          {tipePeminjaman === 'gedung' ? (
            <div className="flex items-start gap-2.5">
              <span className="text-base">🏛️</span>
              <div>
                <p className="font-bold mb-0.5">Alur Peminjaman Gedung (Manual):</p>
                <p>Pengajuan permohonan gedung akan langsung masuk ke meja Pengelola Gedung (Bu Eci) untuk diperiksa kelayakan surat dan jadwalnya.</p>
              </div>
            </div>
          ) : (
            <div className="flex items-start gap-2.5">
              <span className="text-base">⚡</span>
              <div>
                <p className="font-bold mb-0.5">Alur Peminjaman Ruangan (Otomatis):</p>
                <p>Tidak memerlukan surat permohonan. Begitu Anda mengirim form dan jam yang dipilih kosong, sistem langsung menerbitkan status <strong>Disetujui</strong>.</p>
              </div>
            </div>
          )}
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Dropdown Lokasi yang sudah difilter */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Pilih {tipePeminjaman === 'gedung' ? 'Gedung' : 'Ruangan'} <span className="text-rose-500">*</span>
            </label>
            <select
              value={lokasiId}
              onChange={(e) => setLokasiId(e.target.value)}
              className="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="">-- Pilih {tipePeminjaman === 'gedung' ? 'Gedung' : 'Ruangan'} --</option>
              {loadingLokasi ? (
                <option disabled>Memuat daftar {tipePeminjaman}...</option>
              ) : (
                lokasiTersedia.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.nama} — Kapasitas {l.kapasitas} orang
                  </option>
                ))
              )}
            </select>
          </div>

          {/* Tanggal */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Tanggal Kegiatan <span className="text-rose-500">*</span>
            </label>
            <input
              type="date"
              value={tanggal}
              onChange={(e) => setTanggal(e.target.value)}
              min={new Date().toISOString().split('T')[0]}
              className="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
          </div>

          {/* Jam Mulai & Selesai dengan Keterangan AM & PM Jelas */}
          <div className="space-y-3 p-4 bg-slate-50/80 rounded-2xl border border-slate-200/70">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
              <span className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                <span>⏰</span> Waktu Kegiatan (Keterangan AM & PM)
              </span>
              <span className="text-[11px] text-slate-500 font-medium">
                AM = 00:00–11:59 (Pagi) • PM = 12:00–23:59 (Siang/Malam)
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Jam Mulai */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Jam Mulai <span className="text-rose-500">*</span>
                </label>
                <input
                  type="time"
                  value={jamMulai}
                  onChange={(e) => setJamMulai(e.target.value)}
                  className="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
                {infoMulai ? (
                  <div className="mt-2 flex items-center justify-between gap-1 text-[11px]">
                    <span className={`px-2.5 py-1 rounded-lg border font-semibold flex items-center gap-1 ${infoMulai.badgeColor}`}>
                      <span>{infoMulai.ikon}</span>
                      <span><strong>{infoMulai.time12}</strong> ({infoMulai.keterangan})</span>
                    </span>
                    <button
                      type="button"
                      onClick={() => setJamMulai(toggleAmPm(jamMulai))}
                      className="px-2 py-1 text-[10px] font-semibold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded border border-blue-200 transition"
                      title="Klik untuk mengubah antara AM (Pagi) dan PM (Malam)"
                    >
                      Ubah ke {infoMulai.period === 'AM' ? 'PM (Malam)' : 'AM (Pagi)'}
                    </button>
                  </div>
                ) : (
                  <p className="text-[11px] text-slate-400 mt-1">Pilih jam mulai</p>
                )}
              </div>

              {/* Jam Selesai */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Jam Selesai <span className="text-rose-500">*</span>
                </label>
                <input
                  type="time"
                  value={jamSelesai}
                  onChange={(e) => setJamSelesai(e.target.value)}
                  className="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
                {infoSelesai ? (
                  <div className="mt-2 flex items-center justify-between gap-1 text-[11px]">
                    <span className={`px-2.5 py-1 rounded-lg border font-semibold flex items-center gap-1 ${infoSelesai.badgeColor}`}>
                      <span>{infoSelesai.ikon}</span>
                      <span><strong>{infoSelesai.time12}</strong> ({infoSelesai.keterangan})</span>
                    </span>
                    <button
                      type="button"
                      onClick={() => setJamSelesai(toggleAmPm(jamSelesai))}
                      className="px-2 py-1 text-[10px] font-semibold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded border border-blue-200 transition"
                      title="Klik untuk mengubah antara AM (Pagi) dan PM (Malam)"
                    >
                      Ubah ke {infoSelesai.period === 'AM' ? 'PM (Malam)' : 'AM (Pagi)'}
                    </button>
                  </div>
                ) : (
                  <p className="text-[11px] text-slate-400 mt-1">Pilih jam selesai</p>
                )}
              </div>
            </div>

            {/* Quick Preset Buttons */}
            <div className="pt-2 border-t border-slate-200/60">
              <span className="text-[11px] text-slate-500 block mb-1.5 font-medium">
                Pilihan Jam Cepat (Format 12-Jam Resmi):
              </span>
              <div className="flex flex-wrap gap-1.5 text-xs">
                {[
                  { label: '08:00 AM (Pagi)', mulai: '08:00', selesai: '12:00' },
                  { label: '09:00 AM (Pagi)', mulai: '09:00', selesai: '15:00' },
                  { label: '01:00 PM (Siang)', mulai: '13:00', selesai: '17:00' },
                  { label: '07:00 PM (Malam)', mulai: '19:00', selesai: '21:30' },
                ].map((p, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => { setJamMulai(p.mulai); setJamSelesai(p.selesai); }}
                    className="px-2.5 py-1 bg-white hover:bg-blue-50 hover:text-blue-700 border border-slate-200 rounded-lg text-[11px] font-medium transition text-slate-600"
                  >
                    {p.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Live Confirmation Bar */}
            {infoMulai && infoSelesai && (
              <div className="mt-2 p-2.5 bg-blue-50/80 border border-blue-200 rounded-xl text-xs text-blue-900 flex items-center gap-2">
                <span className="text-base">📌</span>
                <span>
                  Konfirmasi Waktu Peminjaman: <strong>{infoMulai.time12} ({infoMulai.keterangan})</strong> sampai <strong>{infoSelesai.time12} ({infoSelesai.keterangan})</strong>
                </span>
              </div>
            )}
          </div>

          {/* Estimasi Peserta */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Estimasi Jumlah Peserta <span className="text-slate-400 font-normal">(opsional)</span>
            </label>
            <input
              type="number"
              min="1"
              value={peserta}
              onChange={(e) => setPeserta(e.target.value)}
              placeholder="Contoh: 30"
              className="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* Keperluan / Tujuan */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Tujuan &amp; Keperluan Acara <span className="text-rose-500">*</span>
            </label>
            <textarea
              value={tujuan}
              onChange={(e) => setTujuan(e.target.value)}
              rows={3}
              placeholder={`Jelaskan kegiatan yang akan diadakan di ${tipePeminjaman} ini...`}
              className="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
          </div>

          {/* Lampiran Surat PDF (HANYA MUNCUL DI TAB GEDUNG) */}
          {tipePeminjaman === 'gedung' ? (
            <div className="p-4 bg-purple-50/50 border border-purple-200 rounded-xl space-y-2">
              <label className="block text-xs font-semibold text-purple-950">
                Berkas Surat Permohonan Resmi (Format PDF, Maks. 5 MB) <span className="text-rose-500">*</span>
              </label>
              <input
                type="file"
                accept=".pdf"
                onChange={(e) => setFileSurat(e.target.files[0] || null)}
                className="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-purple-100 file:text-purple-700 hover:file:bg-purple-200"
                required
              />
              <p className="text-[11px] text-purple-700">Surat akan diperiksa oleh Bu Eci sebelum persetujuan diterbitkan.</p>
            </div>
          ) : (
            <div className="p-3 bg-slate-50 rounded-xl border border-slate-200/60 text-[11px] text-slate-500 flex items-center gap-2">
              <span>ℹ️</span>
              <span>Peminjaman ruangan tidak mewajibkan surat izin dinas. Form langsung siap diproses.</span>
            </div>
          )}

          <button
            type="submit"
            disabled={loading || !lokasiId}
            className={`w-full py-3 rounded-xl transition shadow-sm text-sm font-semibold text-white ${
              tipePeminjaman === 'gedung'
                ? 'bg-purple-600 hover:bg-purple-700 disabled:bg-purple-300'
                : 'bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300'
            }`}
          >
            {loading
              ? 'Memproses...'
              : tipePeminjaman === 'gedung'
              ? 'Kirim Permohonan Gedung ke Pengelola'
              : 'Pesan Ruangan Sekarang (Otomatis Disetujui)'}
          </button>
        </form>
      </div>
    </div>
  );
};

export default Ajukan;
