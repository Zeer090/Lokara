import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../auth/AuthProvider.jsx';
import api from '../../api/klien.js';

const fmtTgl = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleDateString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta',
  });
};

const statusBadge = {
  menunggu:   'bg-amber-100 text-amber-700',
  disetujui:  'bg-emerald-100 text-emerald-700',
  ditolak:    'bg-rose-100 text-rose-700',
  dibatalkan: 'bg-slate-100 text-slate-500',
  tergusur:   'bg-red-100 text-red-700 font-bold',
};

export default function UserDashboard() {
  const { user } = useAuth();

  // Ambil reservasi terbaru
  const { data: riwayatData } = useQuery({
    queryKey: ['reservasi_saya_dashboard'],
    queryFn: async () => {
      const res = await api.get('/reservasi/saya');
      return res.data?.data || res.data || [];
    },
  });

  // Ambil kasus terbuka (penggusuran)
  const { data: kasusData } = useQuery({
    queryKey: ['kasus_saya'],
    queryFn: async () => {
      const res = await api.get('/kasus/saya');
      return Array.isArray(res.data) ? res.data : [];
    },
  });

  const reservasiTerbaru = Array.isArray(riwayatData) ? riwayatData.slice(0, 5) : [];
  const kasusAktif = Array.isArray(kasusData)
    ? kasusData.filter(k => ['terbuka', 'diputuskan'].includes(k.status))
    : [];

  // Statistik
  const aktif    = reservasiTerbaru.filter(r => r.status === 'disetujui').length;
  const menunggu = reservasiTerbaru.filter(r => r.status === 'menunggu').length;
  const selesai  = reservasiTerbaru.filter(r => r.status === 'selesai').length;

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      {/* Banner kasus tergusur */}
      {kasusAktif.map(k => (
        <div key={k.id} className="bg-red-50 border border-red-300 rounded-xl p-4 flex justify-between items-center gap-4">
          <div>
            <p className="font-semibold text-red-700">⚠️ Reservasi Anda digusur</p>
            <p className="text-sm text-red-600 mt-0.5">
              Alasan: {String(k.alasan_penggusuran || k.alasan || 'Diberikan oleh pengelola gedung.').slice(0, 100)}
            </p>
          </div>
          <Link
            to={`/app/negosiasi/${k.id}`}
            className="flex-shrink-0 bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded-lg font-medium transition"
          >
            Negosiasi →
          </Link>
        </div>
      ))}

      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-slate-900">
          Selamat Datang{user?.nama ? `, ${user.nama}` : ''}!
        </h1>
        <p className="text-slate-500 mt-1">Kelola reservasi gedung dan ruangan Anda.</p>
      </div>

      {/* Statistik */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {[
          { label: 'Disetujui', value: aktif,    cls: 'bg-emerald-50 text-emerald-700' },
          { label: 'Menunggu',  value: menunggu, cls: 'bg-amber-50 text-amber-700' },
          { label: 'Selesai',   value: selesai,  cls: 'bg-slate-50 text-slate-700' },
        ].map(k => (
          <div key={k.label} className={`${k.cls} rounded-xl p-5 text-center`}>
            <p className="text-4xl font-bold">{k.value}</p>
            <p className="text-sm mt-1 font-medium">{k.label}</p>
          </div>
        ))}
      </div>

      {/* Riwayat Terbaru */}
      <div className="bg-white rounded-xl shadow-sm border border-slate-200/80 p-6">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-semibold text-slate-800">Reservasi Terbaru</h2>
          <Link to="/app/reservasi" className="text-sm text-blue-600 hover:underline">Lihat semua →</Link>
        </div>

        {reservasiTerbaru.length === 0 ? (
          <p className="text-sm text-slate-400 text-center py-8">Belum ada reservasi.</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-slate-50 text-slate-500 text-xs font-semibold uppercase">
                <th className="text-left p-3">Lokasi</th>
                <th className="text-left p-3">Mulai</th>
                <th className="text-left p-3">Status</th>
              </tr>
            </thead>
            <tbody>
              {reservasiTerbaru.map((r) => (
                <tr key={r.id} className="border-t hover:bg-slate-50 transition">
                  <td className="p-3 font-medium text-slate-800">
                    <Link to={`/app/reservasi/${r.id}`} className="hover:text-blue-600">
                      {r.lokasi?.nama || r.gedung || '-'}
                    </Link>
                  </td>
                  <td className="p-3 text-slate-500">{fmtTgl(r.mulai)}</td>
                  <td className="p-3">
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${statusBadge[r.status] || 'bg-slate-100 text-slate-600'}`}>
                      {r.status}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Quick Actions */}
      <div className="flex gap-3 flex-wrap">
        <Link
          to="/app/ajukan"
          className="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-medium transition shadow-sm"
        >
          + Ajukan Reservasi
        </Link>
        <Link
          to="/app/reservasi"
          className="border border-slate-300 hover:bg-slate-50 text-slate-700 px-5 py-2.5 rounded-xl font-medium transition"
        >
          Lihat Riwayat
        </Link>
      </div>
    </div>
  );
}
