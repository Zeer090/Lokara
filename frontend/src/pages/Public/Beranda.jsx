import React, { useState, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';
import { formatFasilitas } from '../../utils/format.js';

// Format helper
const formatTglLengkap = (dateStr) => {
  if (!dateStr || typeof dateStr !== 'string' || !dateStr.includes('-')) return '';
  try {
    const [y, m, d] = dateStr.split('-').map(Number);
    const dateObj = new Date(y, m - 1, d);
    if (isNaN(dateObj.getTime())) return String(dateStr);
    return dateObj.toLocaleDateString('id-ID', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  } catch {
    return String(dateStr);
  }
};

const getHariName = (dateStr) => {
  if (!dateStr || typeof dateStr !== 'string' || !dateStr.includes('-')) return '';
  try {
    const [y, m, d] = dateStr.split('-').map(Number);
    const dateObj = new Date(y, m - 1, d);
    if (isNaN(dateObj.getTime())) return '';
    return dateObj.toLocaleDateString('id-ID', { weekday: 'short' });
  } catch {
    return '';
  }
};

const getTglNomor = (dateStr) => {
  if (!dateStr || typeof dateStr !== 'string' || !dateStr.includes('-')) return '';
  const parts = dateStr.split('-');
  return parts[2] || '';
};

export default function Beranda() {
  const navigate = useNavigate();

  // Tanggal default: Hari ini dalam format YYYY-MM-DD
  const todayStr = useMemo(() => {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }, []);

  const [searchKeyword, setSearchKeyword] = useState('');
  const [selectedDate, setSelectedDate] = useState(todayStr);
  const [filterJenis, setFilterJenis] = useState('semua'); // 'semua' | 'gedung' | 'ruangan'
  const [filterStatus, setFilterStatus] = useState('semua'); // 'semua' | 'kosong' | 'terisi'

  // Hitung bulan untuk query kalender (YYYY-MM)
  const currentMonth = useMemo(() => {
    return selectedDate.slice(0, 7);
  }, [selectedDate]);

  // 1. Fetch data semua lokasi resmi kampus
  const { data: daftarLokasi = [], isLoading: loadingLokasi } = useQuery({
    queryKey: ['publik_beranda_lokasi'],
    queryFn: async () => {
      const res = await api.get('/lokasi');
      const data = res.data?.data || res.data || [];
      return Array.isArray(data) ? data : [];
    },
  });

  // 2. Fetch jadwal/slot terisi untuk bulan terpilih
  const { data: events = [], isLoading: loadingEvents } = useQuery({
    queryKey: ['publik_beranda_events', currentMonth],
    queryFn: async () => {
      const res = await api.get('/jadwal/kalender', { params: { bulan: currentMonth } });
      const raw = res.data?.events || res.data?.data || res.data || [];
      return Array.isArray(raw) ? raw : [];
    },
  });

  // Generate 7 hari cepat di sekitar selectedDate
  const dateStrip = useMemo(() => {
    const list = [];
    const [y, m, d] = selectedDate.split('-').map(Number);
    const base = new Date(y, m - 1, d);

    // Ambil 3 hari sebelum dan 3 hari sesudah
    for (let offset = -2; offset <= 4; offset++) {
      const nextDate = new Date(base);
      nextDate.setDate(base.getDate() + offset);
      const dy = nextDate.getFullYear();
      const dm = String(nextDate.getMonth() + 1).padStart(2, '0');
      const dd = String(nextDate.getDate()).padStart(2, '0');
      list.push(`${dy}-${dm}-${dd}`);
    }
    return list;
  }, [selectedDate]);

  // Evaluasi ketersediaan setiap lokasi pada tanggal selectedDate
  const statusPerLokasi = useMemo(() => {
    const map = {};

    daftarLokasi.forEach((lok) => {
      // Temukan semua event bentrok untuk lokasi ini pada selectedDate
      const bentrokList = events.filter((ev) => {
        if (ev.lokasi_id !== lok.id) return false;
        if (!ev.start || !ev.end) return false;

        const startTgl = ev.start.slice(0, 10);
        const endTgl = ev.end.slice(0, 10);

        return startTgl <= selectedDate && endTgl >= selectedDate;
      });

      if (bentrokList.length > 0) {
        // Ambil jam terisi
        const jamSlots = bentrokList.map((ev) => {
          try {
            const s = new Date(ev.start).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const e = new Date(ev.end).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            return `${s} - ${e} WIB`;
          } catch {
            return 'Terpakai';
          }
        });

        map[lok.id] = {
          isKosong: false,
          label: 'Terisi Sebagian / Ada Jadwal',
          jamSlots,
          badgeColor: 'bg-rose-100 text-rose-800 border-rose-200',
        };
      } else {
        map[lok.id] = {
          isKosong: true,
          label: 'Kosong / Tersedia Seharian',
          jamSlots: [],
          badgeColor: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        };
      }
    });

    return map;
  }, [daftarLokasi, events, selectedDate]);

  // Filter daftar lokasi berdasarkan keyword, jenis, dan status ketersediaan
  const filteredLokasi = useMemo(() => {
    return daftarLokasi.filter((lok) => {
      // Filter Search Keyword
      const q = searchKeyword.toLowerCase().trim();
      const matchKeyword =
        !q ||
        lok.nama?.toLowerCase().includes(q) ||
        formatFasilitas(lok.fasilitas).toLowerCase().includes(q) ||
        String(lok.kapasitas).includes(q);

      // Filter Jenis
      const matchJenis = filterJenis === 'semua' || lok.jenis === filterJenis;

      // Filter Status
      const statusInfo = statusPerLokasi[lok.id];
      const matchStatus =
        filterStatus === 'semua' ||
        (filterStatus === 'kosong' && statusInfo?.isKosong) ||
        (filterStatus === 'terisi' && !statusInfo?.isKosong);

      return matchKeyword && matchJenis && matchStatus;
    });
  }, [daftarLokasi, searchKeyword, filterJenis, filterStatus, statusPerLokasi]);

  // Statistik Counter
  const totalKosong = useMemo(() => {
    return daftarLokasi.filter((l) => statusPerLokasi[l.id]?.isKosong).length;
  }, [daftarLokasi, statusPerLokasi]);

  const totalTerisi = daftarLokasi.length - totalKosong;

  const setTanggalCepat = (hariOffset) => {
    const now = new Date();
    now.setDate(now.getDate() + hariOffset);
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    setSelectedDate(`${y}-${m}-${d}`);
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-800">
      {/* 🚀 Hero Section */}
      <section className="relative overflow-hidden bg-gradient-to-b from-blue-700 via-blue-600 to-indigo-700 text-white py-20 px-6">
        <div className="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
        <div className="relative max-w-5xl mx-auto text-center space-y-4">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-xs font-semibold text-blue-100">
            <span>✨</span> Sistem Peminjaman Gedung &amp; Ruangan Cerdas POLINELA
          </div>
          <h1 className="text-4xl sm:text-5xl md:text-6xl font-black tracking-tight text-white leading-tight">
            Cari &amp; Pinjam Ruangan Kampus <br className="hidden sm:inline" />
            <span className="text-blue-200">Kapan Saja, Transparan &amp; Otomatis</span>
          </h1>
          <p className="text-sm sm:text-base text-blue-100 max-w-2xl mx-auto leading-relaxed">
            Persetujuan instan otomatis untuk ruangan kelas/lab, verifikasi digital resmi Bu Eci untuk gedung, serta
            pendampingan Agen AI saat terjadi penggusuran acara kampus.
          </p>
          <div className="pt-2 flex justify-center gap-3 flex-wrap">
            <Link
              to="/app/ajukan"
              className="bg-white text-blue-700 font-bold px-6 py-3 rounded-2xl shadow-lg hover:bg-blue-50 transition active:scale-95 text-xs sm:text-sm"
            >
              + Ajukan Peminjaman
            </Link>
            <Link
              to="/jadwal"
              className="bg-blue-800/60 hover:bg-blue-800/90 text-white font-semibold px-6 py-3 rounded-2xl border border-white/20 transition text-xs sm:text-sm"
            >
              📅 Buka Kalender Bulanan
            </Link>
          </div>
        </div>
      </section>

      {/* 🗓️ INTERACTIVE CALENDAR & AVAILABILITY SEARCH BAR SECTION */}
      <section id="cek-jadwal" className="py-12 px-4 sm:px-6 max-w-6xl mx-auto -mt-8 relative z-20">
        <div className="bg-white rounded-3xl shadow-xl border border-slate-200/80 p-6 sm:p-8 space-y-6">
          {/* Header Section */}
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div>
              <div className="flex items-center gap-2">
                <span className="text-2xl">🔍</span>
                <h2 className="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                  Cek Ketersediaan Gedung &amp; Ruangan
                </h2>
              </div>
              <p className="text-xs sm:text-sm text-slate-500 mt-1">
                Pilih tanggal dan cari gedung atau ruangan yang <strong>kosong</strong> untuk acara atau kuliah pengganti Anda.
              </p>
            </div>

            {/* Quick Status Stats */}
            <div className="flex items-center gap-2.5 flex-wrap">
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold shadow-xs">
                <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                {totalKosong} Kosong / Siap Pakai
              </span>
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold shadow-xs">
                <span className="w-2 h-2 rounded-full bg-rose-500"></span>
                {totalTerisi} Ada Jadwal
              </span>
            </div>
          </div>

          {/* Search & Filter Bar Controls */}
          <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
            {/* 1. Keyword Search */}
            <div className="md:col-span-5 relative">
              <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                Cari Gedung / Ruangan
              </label>
              <div className="relative">
                <input
                  type="text"
                  value={searchKeyword}
                  onChange={(e) => setSearchKeyword(e.target.value)}
                  placeholder="Ketik nama lokasi (misal: Serbaguna, Lab, Ruang 101)..."
                  className="w-full text-xs border border-slate-300 rounded-xl pl-9 pr-3.5 py-2.5 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                />
                <span className="absolute left-3 top-2.5 text-slate-400 text-sm">🔎</span>
                {searchKeyword && (
                  <button
                    onClick={() => setSearchKeyword('')}
                    className="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600 font-bold"
                  >
                    ×
                  </button>
                )}
              </div>
            </div>

            {/* 2. Date Picker */}
            <div className="md:col-span-4">
              <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                Pilih Tanggal Pengecekan
              </label>
              <input
                type="date"
                value={selectedDate}
                onChange={(e) => setSelectedDate(e.target.value)}
                className="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2.5 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold text-slate-800"
              />
            </div>

            {/* 3. Filter Jenis */}
            <div className="md:col-span-3">
              <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                Jenis Fasilitas
              </label>
              <select
                value={filterJenis}
                onChange={(e) => setFilterJenis(e.target.value)}
                className="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium text-slate-800"
              >
                <option value="semua">Semua (Gedung &amp; Ruangan)</option>
                <option value="gedung">🏛️ Khusus Gedung</option>
                <option value="ruangan">🚪 Khusus Ruangan</option>
              </select>
            </div>
          </div>

          {/* Interactive Date Strip (Quick 7-Day Navigation) */}
          <div className="space-y-2 pt-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                <span>📅</span> Navigasi Cepat Tanggal:
                <span className="text-blue-600 ml-1 font-semibold">{formatTglLengkap(selectedDate)}</span>
              </span>
              <div className="flex gap-1.5">
                <button
                  onClick={() => setTanggalCepat(0)}
                  className={`text-[11px] px-2.5 py-1 rounded-lg font-semibold transition ${
                    selectedDate === todayStr ? 'bg-blue-600 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-600'
                  }`}
                >
                  Hari Ini
                </button>
                <button
                  onClick={() => setTanggalCepat(1)}
                  className="text-[11px] px-2.5 py-1 rounded-lg font-semibold bg-slate-100 hover:bg-slate-200 text-slate-600 transition"
                >
                  Besok
                </button>
                <button
                  onClick={() => setTanggalCepat(2)}
                  className="text-[11px] px-2.5 py-1 rounded-lg font-semibold bg-slate-100 hover:bg-slate-200 text-slate-600 transition"
                >
                  Lusa
                </button>
              </div>
            </div>

            {/* 7-Day Scrollable Chips */}
            <div className="flex gap-2 overflow-x-auto pb-1 pt-1 no-scrollbar">
              {dateStrip.map((dStr) => {
                const isActive = dStr === selectedDate;
                const isToday = dStr === todayStr;

                return (
                  <button
                    key={dStr}
                    onClick={() => setSelectedDate(dStr)}
                    className={`flex-1 min-w-[70px] sm:min-w-[85px] py-2 px-2 rounded-2xl border text-center transition-all ${
                      isActive
                        ? 'bg-blue-600 border-blue-600 text-white shadow-md scale-102 font-bold'
                        : 'bg-white border-slate-200/80 hover:bg-slate-50 text-slate-700'
                    }`}
                  >
                    <div className="text-[10px] uppercase tracking-wide opacity-80">{getHariName(dStr)}</div>
                    <div className="text-base font-extrabold my-0.5">{getTglNomor(dStr)}</div>
                    {isToday && (
                      <span className={`text-[9px] px-1.5 py-0.2 rounded-full font-bold ${isActive ? 'bg-white text-blue-600' : 'bg-blue-100 text-blue-700'}`}>
                        Kini
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Filter Status Pills (Semua / Hanya Kosong / Terisi) */}
          <div className="flex items-center gap-2 border-t border-slate-100 pt-4 flex-wrap">
            <span className="text-xs font-semibold text-slate-500">Tampilkan:</span>
            <button
              onClick={() => setFilterStatus('semua')}
              className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition ${
                filterStatus === 'semua'
                  ? 'bg-slate-800 text-white shadow-xs'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              Semua ({daftarLokasi.length})
            </button>
            <button
              onClick={() => setFilterStatus('kosong')}
              className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition ${
                filterStatus === 'kosong'
                  ? 'bg-emerald-600 text-white shadow-xs'
                  : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'
              }`}
            >
              <span>🟢</span> Hanya yang Kosong ({totalKosong})
            </button>
            <button
              onClick={() => setFilterStatus('terisi')}
              className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition ${
                filterStatus === 'terisi'
                  ? 'bg-rose-600 text-white shadow-xs'
                  : 'bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100'
              }`}
            >
              <span>🔴</span> Ada Jadwal ({totalTerisi})
            </button>
          </div>

          {/* Results Grid */}
          <div className="pt-2">
            {loadingLokasi || loadingEvents ? (
              <div className="py-20 text-center">
                <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent mb-3"></div>
                <p className="text-xs text-slate-500">Memeriksa ketersediaan jadwal gedung &amp; ruangan...</p>
              </div>
            ) : filteredLokasi.length === 0 ? (
              <div className="py-16 text-center text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                <p className="text-3xl mb-2">🔍</p>
                <p className="text-sm font-semibold text-slate-700">Tidak ada fasilitas yang sesuai kriteria</p>
                <p className="text-xs text-slate-400 mt-1">Coba ganti tanggal atau hapus kata kunci pencarian Anda.</p>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {filteredLokasi.map((lok) => {
                  const status = statusPerLokasi[lok.id] || { isKosong: true, jamSlots: [] };
                  const isGedung = lok.jenis === 'gedung';

                  return (
                    <div
                      key={lok.id}
                      className={`rounded-2xl border p-5 flex flex-col justify-between transition-all hover:shadow-md ${
                        status.isKosong
                          ? 'bg-white border-slate-200/90 hover:border-emerald-300'
                          : 'bg-rose-50/30 border-rose-200/80'
                      }`}
                    >
                      {/* Card Header */}
                      <div>
                        <div className="flex items-start justify-between gap-2 mb-2">
                          <span
                            className={`px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider ${
                              isGedung
                                ? 'bg-purple-100 text-purple-800 border border-purple-200'
                                : 'bg-blue-100 text-blue-800 border border-blue-200'
                            }`}
                          >
                            {isGedung ? '🏛️ Gedung' : '🚪 Ruangan'}
                          </span>

                          {/* Availability Badge */}
                          <span
                            className={`px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border ${
                              status.isKosong
                                ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                : 'bg-rose-100 text-rose-800 border-rose-200'
                            }`}
                          >
                            {status.isKosong ? '🟢 KOSONG' : '🔴 TERISI'}
                          </span>
                        </div>

                        <h3 className="font-extrabold text-slate-900 text-base leading-snug">{lok.nama}</h3>
                        <p className="text-xs text-slate-500 mt-0.5">
                          Kapasitas: <span className="font-semibold text-slate-700">{lok.kapasitas} orang</span>
                        </p>

                        {formatFasilitas(lok.fasilitas) && (
                          <p className="text-[11px] text-slate-400 mt-2 line-clamp-2 leading-relaxed">
                            {formatFasilitas(lok.fasilitas)}
                          </p>
                        )}
                      </div>

                      {/* Schedule details & Action */}
                      <div className="mt-4 pt-3 border-t border-slate-100 space-y-3">
                        {status.isKosong ? (
                          <div className="p-2 rounded-xl bg-emerald-50/80 border border-emerald-100 text-[11px] text-emerald-800 flex items-center gap-1.5">
                            <span>✅</span>
                            <span>Tersedia seharian penuh pada {formatTglLengkap(selectedDate)}.</span>
                          </div>
                        ) : (
                          <div className="p-2 rounded-xl bg-rose-50 border border-rose-200 text-[11px] text-rose-800 space-y-1">
                            <div className="font-bold flex items-center gap-1">
                              <span>⚠️</span> Waktu Terpakai:
                            </div>
                            <ul className="list-disc list-inside space-y-0.5 text-[10px]">
                              {status.jamSlots.map((slot, idx) => (
                                <li key={idx} className="font-semibold">{slot}</li>
                              ))}
                            </ul>
                          </div>
                        )}

                        <div className="flex items-center justify-between gap-2">
                          <span className="text-[10px] text-slate-400">
                            {isGedung ? 'Verifikasi Bu Eci' : 'Persetujuan Instan'}
                          </span>
                          <Link
                            to={`/app/ajukan?lokasi_id=${lok.id}`}
                            className={`px-3.5 py-1.5 rounded-xl text-xs font-semibold transition shadow-xs text-center ${
                              status.isKosong
                                ? 'bg-blue-600 hover:bg-blue-700 text-white'
                                : 'bg-slate-200 hover:bg-slate-300 text-slate-700'
                            }`}
                          >
                            {status.isKosong ? '+ Pinjam Sekarang' : 'Cek Jam Lain'}
                          </Link>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Footer of Section: Link to Full Calendar */}
          <div className="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500">
            <p>
              💡 Ingin melihat rincian jadwal satu bulan penuh dalam tampilan grid?
            </p>
            <Link
              to="/jadwal"
              className="inline-flex items-center gap-1.5 font-bold text-blue-600 hover:text-blue-700 hover:underline"
            >
              <span>Buka Kalender Publik Lengkap</span>
              <span>→</span>
            </Link>
          </div>
        </div>
      </section>

      {/* 🏛️ Lokasi Section (Daftar Fasilitas Utama) */}
      <section className="py-16 px-6 bg-slate-100/60 border-t border-slate-200/60">
        <div className="max-w-6xl mx-auto space-y-8">
          <div className="text-center max-w-2xl mx-auto">
            <h2 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
              Fasilitas Kampus POLINELA
            </h2>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Gedung pertemuan besar, auditorium, serta laboratorium komputer siap menunjang kegiatan akademik dan kemahasiswaan.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {daftarLokasi.slice(0, 6).map((lok) => (
              <div
                key={lok.id}
                className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition"
              >
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-2xl">{lok.jenis === 'gedung' ? '🏛️' : '🚪'}</span>
                    <span className="text-[10px] font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded-full capitalize">
                      {lok.jenis}
                    </span>
                  </div>
                  <h3 className="text-lg font-bold text-slate-900 mb-1">{lok.nama}</h3>
                  <p className="text-xs text-slate-500 mb-3">
                    Kapasitas:{' '}
                    <span className="font-semibold text-slate-700">{lok.kapasitas} orang</span>
                  </p>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    {formatFasilitas(lok.fasilitas) || 'Fasilitas standar pendingin ruangan, pencahayaan, dan kelistrikan.'}
                  </p>
                </div>

                <div className="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                  <span className="text-[11px] text-slate-400">Status Operasional Aktif</span>
                  <Link
                    to={`/app/ajukan?lokasi_id=${lok.id}`}
                    className="text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline"
                  >
                    Pilih Lokasi →
                  </Link>
                </div>
              </div>
            ))}
          </div>

          <div className="text-center pt-2">
            <Link
              to="/lokasi"
              className="inline-flex items-center gap-2 text-xs font-bold text-blue-600 hover:underline px-4 py-2 bg-white rounded-xl border border-slate-200 shadow-xs"
            >
              Lihat Seluruh {daftarLokasi.length} Fasilitas Kampus →
            </Link>
          </div>
        </div>
      </section>

      {/* 🚀 CTA Section */}
      <section className="py-20 px-6 text-center bg-white border-t border-slate-200/80">
        <div className="max-w-2xl mx-auto space-y-4">
          <h2 className="text-3xl font-extrabold text-slate-900 tracking-tight">Siap Meminjam Ruangan?</h2>
          <p className="text-sm text-slate-500 leading-relaxed">
            Ajukan permohonan peminjaman ruangan atau gedung kampus secara resmi dalam hitungan menit tanpa birokrasi manual yang rumit.
          </p>
          <div className="pt-2 flex justify-center gap-3">
            <Link
              to="/daftar"
              className="bg-blue-600 hover:bg-blue-700 text-white font-bold px-8 py-3.5 rounded-2xl shadow-md transition active:scale-95 text-xs sm:text-sm"
            >
              Daftar Akun Mahasiswa
            </Link>
            <Link
              to="/masuk"
              className="border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold px-6 py-3.5 rounded-2xl transition text-xs sm:text-sm"
            >
              Masuk ke Sistem
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
