import React, { useState, useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import api from '../../api/klien.js';

const fetchLokasiList = async () => {
  const res = await api.get('/lokasi');
  return res.data?.data || res.data || [];
};

const SESI_JADWAL = {
  sesi1: {
    id: 'sesi1',
    ikon: '☀️',
    nama: 'Sesi 1 (Pagi – Sore)',
    rentang: '08:00 – 15:00 WIB',
    minMulai: '08:00',
    maxSelesai: '15:00',
    opsiMulai: ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00'],
    opsiSelesai: ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'],
  },
  sesi2: {
    id: 'sesi2',
    ikon: '🌙',
    nama: 'Sesi 2 (Sore – Malam)',
    rentang: '15:00 – 20:00 WIB',
    minMulai: '15:00',
    maxSelesai: '20:00',
    opsiMulai: ['15:00', '16:00', '17:00', '18:00', '19:00'],
    opsiSelesai: ['16:00', '17:00', '18:00', '19:00', '20:00'],
  },
};

const getTodayStr = () => {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

const getHariShort = (dateStr) => {
  try {
    const [y, m, d] = dateStr.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('id-ID', { weekday: 'short' });
  } catch {
    return '';
  }
};

const Ajukan = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const initialJenis = searchParams.get('jenis') === 'ruangan' ? 'ruangan' : 'gedung';
  const initialLokasiId = searchParams.get('lokasi_id') || '';

  const todayStr = useMemo(() => getTodayStr(), []);

  const [tipePeminjaman, setTipePeminjaman] = useState(initialJenis);
  const [lokasiId, setLokasiId] = useState(initialLokasiId);
  const [tanggal, setTanggal] = useState(todayStr);
  const [sesiAktif, setSesiAktif] = useState('sesi1');
  const [jamMulai, setJamMulai] = useState('08:00');
  const [jamSelesai, setJamSelesai] = useState('15:00');
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

  // Sinkronkan tipe & lokasi jika user datang dari klik tombol di Beranda
  useEffect(() => {
    if (initialLokasiId && daftarLokasi.length > 0) {
      const found = daftarLokasi.find((l) => l.id === initialLokasiId);
      if (found) {
        setTipePeminjaman(found.jenis);
        setLokasiId(found.id);
      }
    }
  }, [initialLokasiId, daftarLokasi]);

  // Ambil jadwal terisi bulan terpilih untuk lokasi yang dipilih (sama dengan Kalender)
  const currentMonth = tanggal ? tanggal.slice(0, 7) : todayStr.slice(0, 7);
  const { data: eventsLokasi = [], isLoading: loadingJadwal } = useQuery({
    queryKey: ['ajukan_kalender_lokasi', currentMonth, lokasiId],
    queryFn: async () => {
      if (!lokasiId) return [];
      const res = await api.get('/jadwal/kalender', {
        params: { bulan: currentMonth, lokasi_id: lokasiId },
      });
      return res.data?.events || [];
    },
    enabled: Boolean(lokasiId),
  });

  const jadwalHariIni = useMemo(() => {
    if (!tanggal || !eventsLokasi.length) return [];
    return eventsLokasi.filter((ev) => {
      if (!ev.start || !ev.end) return false;
      const s = ev.start.slice(0, 10);
      const e = ev.end.slice(0, 10);
      return s <= tanggal && e >= tanggal;
    });
  }, [eventsLokasi, tanggal]);

  // 7-hari date strip (mulai dari hari ini atau sekitar tanggal dipilih)
  const dateStrip = useMemo(() => {
    const baseStr = tanggal >= todayStr ? tanggal : todayStr;
    const [y, m, d] = baseStr.split('-').map(Number);
    const base = new Date(y, m - 1, d);
    const list = [];
    for (let offset = 0; offset < 7; offset++) {
      const next = new Date(base);
      next.setDate(base.getDate() + offset);
      const dy = next.getFullYear();
      const dm = String(next.getMonth() + 1).padStart(2, '0');
      const dd = String(next.getDate()).padStart(2, '0');
      list.push(`${dy}-${dm}-${dd}`);
    }
    return list;
  }, [tanggal, todayStr]);

  const lokasiTersedia = daftarLokasi.filter((l) => l.jenis === tipePeminjaman);
  const selectedLokasi = daftarLokasi.find((l) => l.id === lokasiId);
  const sesiObj = SESI_JADWAL[sesiAktif];

  const handlePilihTipe = (tipe) => {
    setTipePeminjaman(tipe);
    setLokasiId('');
    setErrorMsg('');
    if (tipe === 'gedung') {
      const s = SESI_JADWAL[sesiAktif];
      setJamMulai(s.minMulai);
      setJamSelesai(s.maxSelesai);
    }
    setSearchParams({ jenis: tipe });
  };

  const handlePilihSesi = (sesiKey) => {
    const s = SESI_JADWAL[sesiKey];
    setSesiAktif(sesiKey);
    setJamMulai(s.minMulai);
    setJamSelesai(s.maxSelesai);
  };

  const handleUbahJamMulai = (val) => {
    setJamMulai(val);
    if (jamSelesai <= val) {
      const nextValid = sesiObj.opsiSelesai.find((o) => o > val) || sesiObj.maxSelesai;
      setJamSelesai(nextValid);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrorMsg('');

    if (!lokasiId || !tanggal || !jamMulai || !jamSelesai || !tujuan) {
      setErrorMsg('Mohon lengkapi semua kolom yang wajib diisi.');
      return;
    }

    if (tipePeminjaman === 'gedung') {
      if (jamMulai < sesiObj.minMulai || jamSelesai > sesiObj.maxSelesai || jamSelesai <= jamMulai) {
        setErrorMsg(`Waktu peminjaman gedung untuk ${sesiObj.nama} harus berada di antara ${sesiObj.minMulai} – ${sesiObj.maxSelesai} WIB.`);
        return;
      }
    } else if (jamSelesai <= jamMulai) {
      setErrorMsg('Jam selesai harus lebih akhir daripada jam mulai.');
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
          Pilih kategori, tanggal dari kalender, dan sesi waktu kegiatan Anda.
        </p>
      </div>

      {/* Tab Kategori */}
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
            <p className="text-[11px] text-slate-500">Kapasitas besar, wajib surat PDF, ACC Bu Eci</p>
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
            <p className="text-[11px] text-slate-500">Kelas / Lab / Seminar, tanpa surat, instan ACC</p>
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
              💡 Ruangan ini terpakai jadwal kuliah aktif. Silakan cek slot yang kosong di kalender atau pilih ruangan lain.
            </p>
          )}
        </div>
      )}

      {/* Form Card */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 sm:p-8 space-y-6">
        <form onSubmit={handleSubmit} className="space-y-5">
          {/* 1. Pilih Lokasi */}
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

          {/* 2. Tanggal Kegiatan (Style Kalender + Date Strip) */}
          <div className="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-3">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <label className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                <span>📅</span> Tanggal Kegiatan <span className="text-rose-500">*</span>
              </label>
              <input
                type="date"
                value={tanggal}
                onChange={(e) => setTanggal(e.target.value)}
                min={todayStr}
                className="bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>

            {/* 7-Day Date Strip */}
            <div className="grid grid-cols-7 gap-1.5">
              {dateStrip.map((dStr) => {
                const aktif = dStr === tanggal;
                return (
                  <button
                    key={dStr}
                    type="button"
                    onClick={() => setTanggal(dStr)}
                    className={`py-2 px-1 rounded-xl text-center transition border ${
                      aktif
                        ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                        : 'bg-white text-slate-700 border-slate-200 hover:border-blue-300 hover:bg-blue-50/40'
                    }`}
                  >
                    <span className={`block text-[10px] font-medium ${aktif ? 'text-blue-100' : 'text-slate-400'}`}>
                      {getHariShort(dStr)}
                    </span>
                    <span className="block text-sm font-extrabold mt-0.5">{dStr.slice(8, 10)}</span>
                  </button>
                );
              })}
            </div>

            {/* Live Status Ketersediaan dari Kalender */}
            {lokasiId && (
              <div className={`p-3 rounded-xl border text-xs ${
                loadingJadwal
                  ? 'bg-white border-slate-200 text-slate-500'
                  : jadwalHariIni.length === 0
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                    : 'bg-amber-50 border-amber-200 text-amber-900'
              }`}>
                {loadingJadwal ? (
                  <span>⏳ Memeriksa jadwal terisi di kalender...</span>
                ) : jadwalHariIni.length === 0 ? (
                  <span className="font-semibold">🟢 Kosong seharian — belum ada jadwal terisi pada tanggal ini.</span>
                ) : (
                  <div className="space-y-1">
                    <p className="font-bold">🔴 Jadwal Terisi pada Tanggal Ini (hindari jam berikut):</p>
                    <div className="flex flex-wrap gap-1.5">
                      {jadwalHariIni.map((ev, idx) => {
                        const s = new Date(ev.start).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        const e = new Date(ev.end).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        return (
                          <span key={idx} className="px-2 py-0.5 bg-white border border-amber-300 rounded-md font-semibold text-[11px]">
                            {s}–{e} WIB ({ev.sumber === 'jadwal_kuliah' ? 'Kuliah' : 'Terpakai'})
                          </span>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* 3. Waktu Kegiatan: Wajib Sesi (Gedung) vs Bebas (Ruangan) */}
          {tipePeminjaman === 'gedung' ? (
            <div className="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-3">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                <span className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                  <span>⏰</span> Pilih Sesi Peminjaman Gedung <span className="text-rose-500">*</span>
                </span>
                <span className="text-[11px] text-slate-500 font-medium">
                  Batas Wajib: 08:00–15:00 (Sesi 1) • 15:00–20:00 (Sesi 2)
                </span>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {Object.values(SESI_JADWAL).map((s) => {
                  const selected = sesiAktif === s.id;
                  return (
                    <button
                      key={s.id}
                      type="button"
                      onClick={() => handlePilihSesi(s.id)}
                      className={`p-3.5 rounded-xl border text-left transition flex items-center gap-3 ${
                        selected
                          ? 'bg-purple-600 text-white border-purple-600 shadow-sm'
                          : 'bg-white text-slate-700 border-slate-200 hover:border-purple-300'
                      }`}
                    >
                      <span className="text-2xl">{s.ikon}</span>
                      <div>
                        <p className="text-xs font-bold">{s.nama}</p>
                        <p className={`text-[11px] font-semibold mt-0.5 ${selected ? 'text-purple-100' : 'text-slate-500'}`}>
                          {s.rentang}
                        </p>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>
          ) : (
            <div className="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-3">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                <span className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                  <span>⏰</span> Waktu Peminjaman Ruangan (Bebas) <span className="text-rose-500">*</span>
                </span>
                <span className="text-[11px] text-slate-500 font-medium">
                  Atur jam mulai &amp; selesai sesuai kebutuhan
                </span>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-600 mb-1">
                    Jam Mulai <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="time"
                    value={jamMulai}
                    onChange={(e) => setJamMulai(e.target.value)}
                    className="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-600 mb-1">
                    Jam Selesai <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="time"
                    value={jamSelesai}
                    onChange={(e) => setJamSelesai(e.target.value)}
                    className="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                  />
                </div>
              </div>
            </div>
          )}

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
