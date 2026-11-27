import React, { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

const DAYS = [
  { key: 'semua', label: 'Semua Hari' },
  { key: 'senin', label: 'Senin' },
  { key: 'selasa', label: 'Selasa' },
  { key: 'rabu', label: 'Rabu' },
  { key: 'kamis', label: 'Kamis' },
  { key: 'jumat', label: 'Jumat' },
  { key: 'sabtu', label: 'Sabtu' },
];

export default function JadwalKuliah() {
  const [selectedPeriodId, setSelectedPeriodId] = useState('');
  const [selectedDay, setSelectedDay] = useState('semua');
  const [selectedRoomId, setSelectedRoomId] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [viewMode, setViewMode] = useState('grid'); // 'grid' | 'table'

  // 1. Fetch periods
  const { data: periods = [] } = useQuery({
    queryKey: ['akademik_periods'],
    queryFn: async () => {
      const res = await api.get('/akademik/periods');
      const list = res.data || [];
      if (list.length > 0 && !selectedPeriodId) {
        setSelectedPeriodId(list[0].id);
      }
      return list;
    },
  });

  const activePeriod = periods.find((p) => p.id === selectedPeriodId) || periods[0];

  // 2. Fetch active schedules for selected period
  const { data: schedules = [], isLoading: loadingSchedules, refetch } = useQuery({
    queryKey: ['akademik_schedules', activePeriod?.id],
    enabled: !!activePeriod?.id,
    queryFn: async () => {
      const res = await api.get(`/akademik/schedule/${activePeriod.id}`);
      return res.data || [];
    },
  });

  // 3. Fetch rooms
  const { data: rooms = [] } = useQuery({
    queryKey: ['lokasi_rooms'],
    queryFn: async () => {
      const res = await api.get('/lokasi');
      return res.data?.data || res.data || [];
    },
  });

  const roomMap = useMemo(() => {
    const map = {};
    rooms.forEach((r) => {
      map[r.id] = r.nama;
    });
    return map;
  }, [rooms]);

  // Filtered schedules
  const filteredSchedules = useMemo(() => {
    return schedules.filter((s) => {
      if (selectedDay !== 'semua' && s.hari?.toLowerCase() !== selectedDay) {
        return false;
      }
      if (selectedRoomId && s.lokasi_id !== selectedRoomId) {
        return false;
      }
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const mk = (s.nama_mata_kuliah || '').toLowerCase();
        const kode = (s.kode_mk || '').toLowerCase();
        const dosen = (s.nama_dosen || '').toLowerCase();
        const prodi = (s.program_studi || '').toLowerCase();
        if (!mk.includes(q) && !kode.includes(q) && !dosen.includes(q) && !prodi.includes(q)) {
          return false;
        }
      }
      return true;
    });
  }, [schedules, selectedDay, selectedRoomId, searchQuery]);

  // Group by day for grid view
  const schedulesByDay = useMemo(() => {
    const grouped = {
      senin: [],
      selasa: [],
      rabu: [],
      kamis: [],
      jumat: [],
      sabtu: [],
    };
    filteredSchedules.forEach((s) => {
      const d = (s.hari || '').toLowerCase();
      if (grouped[d]) {
        grouped[d].push(s);
      }
    });
    // Sort each day by jam_mulai
    Object.keys(grouped).forEach((d) => {
      grouped[d].sort((a, b) => (a.jam_mulai || '').localeCompare(b.jam_mulai || ''));
    });
    return grouped;
  }, [filteredSchedules]);

  const formatTime = (timeStr) => {
    if (!timeStr) return '';
    return timeStr.slice(0, 5); // '08:00:00' -> '08:00'
  };

  return (
    <div className="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto space-y-6">
        {/* Header Breadcrumb & Actions */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-xs text-slate-500 mb-1">
              <Link to="/akademik" className="hover:text-teal-600">Portal Akademik</Link>
              <span>/</span>
              <span className="text-slate-800 font-semibold">Jadwal Perkuliahan Aktif</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
              Jadwal Perkuliahan Ruangan & Gedung
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Daftar sesi perkuliahan aktif yang mengunci alokasi ruangan di SMARTSPACE AI.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Link
              to="/akademik/upload"
              className="px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5"
            >
              <span>📤 Upload Datasheet Baru</span>
            </Link>
            <Link
              to="/jadwal"
              className="px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5"
            >
              <span>🗓️ Kalender Publik</span>
            </Link>
          </div>
        </div>

        {/* Filter Toolbar */}
        <div className="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
            {/* Periode Selector */}
            <div>
              <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                Periode Semester
              </label>
              <select
                value={selectedPeriodId}
                onChange={(e) => setSelectedPeriodId(e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-teal-500"
              >
                {periods.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.tahun_ajaran} ({p.semester})
                  </option>
                ))}
              </select>
            </div>

            {/* Room Selector */}
            <div>
              <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                Pilih Ruangan
              </label>
              <select
                value={selectedRoomId}
                onChange={(e) => setSelectedRoomId(e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-teal-500"
              >
                <option value="">Semua Ruangan</option>
                {rooms.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.nama} ({r.jenis})
                  </option>
                ))}
              </select>
            </div>

            {/* Search Box */}
            <div className="sm:col-span-2">
              <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                Cari Mata Kuliah / Dosen / Kode
              </label>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Ketik nama mata kuliah, kode MK, atau dosen..."
                className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500"
              />
            </div>
          </div>

          {/* Day Pills & View Mode */}
          <div className="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-100">
            <div className="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
              {DAYS.map((d) => (
                <button
                  key={d.key}
                  type="button"
                  onClick={() => setSelectedDay(d.key)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                    selectedDay === d.key
                      ? 'bg-teal-600 text-white shadow-sm'
                      : 'bg-slate-100 hover:bg-slate-200 text-slate-600'
                  }`}
                >
                  {d.label}
                </button>
              ))}
            </div>

            <div className="flex items-center gap-2">
              <span className="text-xs text-slate-500 font-medium">
                Ditemukan <strong>{filteredSchedules.length}</strong> sesi
              </span>
              <div className="bg-slate-100 p-0.5 rounded-lg flex items-center">
                <button
                  type="button"
                  onClick={() => setViewMode('grid')}
                  className={`px-2.5 py-1 rounded text-xs font-bold transition ${
                    viewMode === 'grid' ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  Grid
                </button>
                <button
                  type="button"
                  onClick={() => setViewMode('table')}
                  className={`px-2.5 py-1 rounded text-xs font-bold transition ${
                    viewMode === 'table' ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  Tabel
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Content View */}
        {loadingSchedules ? (
          <div className="py-20 text-center text-slate-400 text-sm">
            Memuat jadwal perkuliahan...
          </div>
        ) : filteredSchedules.length === 0 ? (
          <div className="bg-white rounded-2xl border border-slate-200/80 p-12 text-center shadow-sm">
            <div className="text-4xl mb-3">🗓️</div>
            <h3 className="text-base font-bold text-slate-800">Tidak ada jadwal perkuliahan ditemukan</h3>
            <p className="text-xs text-slate-500 mt-1 max-w-md mx-auto">
              Tidak ada sesi perkuliahan yang cocok dengan filter. Unggah berkas CSV jadwal perkuliahan untuk semester ini.
            </p>
            <Link
              to="/akademik/upload"
              className="mt-4 inline-block px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-sm transition"
            >
              Upload Datasheet Jadwal Sekarang
            </Link>
          </div>
        ) : viewMode === 'grid' ? (
          /* Grid View by Days */
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'].map((day) => {
              const dayList = schedulesByDay[day] || [];
              if (selectedDay !== 'semua' && selectedDay !== day) return null;
              return (
                <div key={day} className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
                  <div className="bg-slate-100/80 px-4 py-3 border-b border-slate-200/60 flex items-center justify-between">
                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                      <span>📅</span>
                      <span>{day.toUpperCase()}</span>
                    </h3>
                    <span className="text-[11px] font-semibold bg-white border border-slate-200 px-2 py-0.5 rounded-full text-slate-600">
                      {dayList.length} Sesi
                    </span>
                  </div>

                  <div className="p-4 space-y-3 flex-1">
                    {dayList.length === 0 ? (
                      <p className="text-xs text-slate-400 italic text-center py-6">
                        Tidak ada perkuliahan pada hari ini.
                      </p>
                    ) : (
                      dayList.map((item) => (
                        <div
                          key={item.id}
                          className="bg-slate-50 hover:bg-teal-50/50 border border-slate-200/70 hover:border-teal-300 rounded-xl p-3.5 transition group"
                        >
                          <div className="flex items-start justify-between gap-2 mb-1.5">
                            <span className="text-xs font-extrabold text-teal-800">
                              ⏰ {formatTime(item.jam_mulai)} - {formatTime(item.jam_selesai)}
                            </span>
                            <span className="text-[10px] font-bold bg-teal-100 text-teal-800 px-2 py-0.5 rounded-full">
                              Kelas {item.kelas || 'A'}
                            </span>
                          </div>

                          <h4 className="text-xs sm:text-sm font-bold text-slate-800 leading-snug">
                            {item.nama_mata_kuliah}
                          </h4>
                          <p className="text-[11px] text-slate-500 font-mono mt-0.5">
                            Kode: {item.kode_mk || '-'} • {item.program_studi || 'Informatika'}
                          </p>

                          <div className="mt-2 pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px]">
                            <span className="text-slate-600 truncate max-w-[150px]">
                              👨‍🏫 {item.nama_dosen || 'Dosen Pengampu'}
                            </span>
                            <span className="font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">
                              🚪 {roomMap[item.lokasi_id] || 'Ruangan'}
                            </span>
                          </div>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          /* Table View */
          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 text-slate-600 uppercase tracking-wider font-semibold border-b border-slate-200/70">
                  <tr>
                    <th className="py-3 px-4">Hari & Jam</th>
                    <th className="py-3 px-4">Mata Kuliah</th>
                    <th className="py-3 px-4">Kelas / Prodi</th>
                    <th className="py-3 px-4">Dosen</th>
                    <th className="py-3 px-4">Ruangan</th>
                    <th className="py-3 px-4 text-center">Status Alokasi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {filteredSchedules.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50/80 transition">
                      <td className="py-3 px-4 font-semibold text-slate-800 capitalize">
                        <div>{item.hari}</div>
                        <div className="text-[11px] text-teal-700 font-mono">
                          {formatTime(item.jam_mulai)} - {formatTime(item.jam_selesai)}
                        </div>
                      </td>
                      <td className="py-3 px-4 font-bold text-slate-800">
                        <div>{item.nama_mata_kuliah}</div>
                        <div className="text-[10px] text-slate-400 font-mono font-normal">
                          {item.kode_mk}
                        </div>
                      </td>
                      <td className="py-3 px-4 text-slate-600">
                        <div>Kelas {item.kelas || 'A'}</div>
                        <div className="text-[10px] text-slate-400">{item.program_studi}</div>
                      </td>
                      <td className="py-3 px-4 text-slate-700">
                        {item.nama_dosen}
                      </td>
                      <td className="py-3 px-4">
                        <span className="font-semibold text-teal-800 bg-teal-50 px-2 py-1 rounded-md">
                          🚪 {roomMap[item.lokasi_id] || item.lokasi_id}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-center">
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                          🔒 Terkunci Kuliah
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
