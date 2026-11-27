import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

export default function SistemDashboard() {
  const { data: ringkasan = {}, isLoading: loadingRingkasan } = useQuery({
    queryKey: ['sistem_ringkasan'],
    queryFn: async () => {
      const res = await api.get('/laporan/ringkasan');
      return res.data || {};
    },
  });

  const { data: gedungTanpa = [], isLoading: loadingTanpa } = useQuery({
    queryKey: ['sistem_gedung_tanpa_pengelola'],
    queryFn: async () => {
      const res = await api.get('/sistem/gedung-tanpa-pengelola');
      return res.data?.data || res.data || [];
    },
  });

  const jumlahTanpaPengelola = Array.isArray(gedungTanpa) ? gedungTanpa.length : 0;

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-slate-900 mb-1">Dashboard Pengelola Sistem</h1>
        <p className="text-sm text-slate-500">
          Pemantauan integritas infrastruktur, peran pengguna, dan tata kelola gedung kampus POLINELA.
        </p>
      </div>

      {/* Peringatan Sistem */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {jumlahTanpaPengelola > 0 ? (
          <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between gap-3 shadow-sm">
            <div className="flex items-center gap-3">
              <span className="text-3xl">🏛️</span>
              <div>
                <p className="font-bold text-amber-900 text-sm">{jumlahTanpaPengelola} Gedung Tanpa Pengelola</p>
                <p className="text-xs text-amber-700">Perlu penugasan agar permohonan gedung dapat diverifikasi.</p>
              </div>
            </div>
            <Link
              to="/sistem/gedung-tanpa-pengelola"
              className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold whitespace-nowrap transition"
            >
              Lihat Detail →
            </Link>
          </div>
        ) : (
          <div className="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center gap-3 text-emerald-800 text-xs">
            <span className="text-2xl">✅</span>
            <span className="font-semibold">Semua gedung telah memiliki penugasan pengelola resmi.</span>
          </div>
        )}

        <div className="bg-indigo-50 border border-indigo-200 rounded-2xl p-4 flex items-center justify-between gap-3 shadow-sm">
          <div className="flex items-center gap-3">
            <span className="text-3xl">👥</span>
            <div>
              <p className="font-bold text-indigo-900 text-sm">Kelola Akun &amp; Hak Akses</p>
              <p className="text-xs text-indigo-700">Audit peran mahasiswa, pengelola, dan staf akademik.</p>
            </div>
          </div>
          <Link
            to="/sistem/pengguna"
            className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold whitespace-nowrap transition"
          >
            Kelola →
          </Link>
        </div>
      </div>

      {/* Statistik Keseluruhan */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {[
          { label: 'Total Gedung Kampus', value: ringkasan.total_gedung ?? '-' },
          { label: 'Total Ruangan Kelas/Lab', value: ringkasan.total_ruangan ?? '-' },
          { label: 'Total Reservasi Aktif', value: ringkasan.total_reservasi_aktif ?? ringkasan.total_reservasi ?? '-' },
          { label: 'Reservasi Disetujui', value: ringkasan.total_disetujui ?? '-' },
        ].map((item, idx) => (
          <div key={idx} className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 text-center">
            <p className="text-3xl font-extrabold text-slate-800 mb-1">{loadingRingkasan ? '...' : item.value}</p>
            <p className="text-xs font-medium text-slate-500">{item.label}</p>
          </div>
        ))}
      </div>

      {/* Pintasan Menu Utama */}
      <div>
        <h2 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Menu Konfigurasi &amp; Audit</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { label: 'Data Pengguna', desc: 'Peran & Status', to: '/sistem/pengguna', icon: '👤' },
            { label: 'Lokasi & Fasilitas', desc: 'Gedung & Ruangan', to: '/sistem/lokasi', icon: '📍' },
            { label: 'Penugasan Pengelola', desc: 'SK Penugasan Gedung', to: '/sistem/penugasan', icon: '🔗' },
            { label: 'Pengaturan Sistem', desc: 'WhatsApp & Kebijakan', to: '/sistem/pengaturan', icon: '⚙️' },
            { label: 'Audit Log Sistem', desc: 'Aktivitas Agent & Akses', to: '/sistem/log', icon: '📋' },
            { label: 'Gedung Tanpa Pengelola', desc: 'Antrean Darurat', to: '/sistem/gedung-tanpa-pengelola', icon: '🏛️' },
          ].map((s) => (
            <Link
              key={s.to}
              to={s.to}
              className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 hover:shadow-md hover:border-blue-300 transition flex flex-col items-center text-center group"
            >
              <div className="text-2xl mb-1.5 group-hover:scale-110 transition-transform">{s.icon}</div>
              <p className="text-xs font-bold text-slate-800">{s.label}</p>
              <p className="text-[10px] text-slate-400 mt-0.5">{s.desc}</p>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
