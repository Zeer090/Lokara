import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';
import { formatFasilitas } from '../../utils/format.js';

const statusBadge = (status) => {
  const map = {
    menunggu: 'bg-amber-100 text-amber-800 border-amber-200',
    disetujui: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    ditolak: 'bg-rose-100 text-rose-800 border-rose-200',
    tergusur: 'bg-red-100 text-red-800 border-red-300 font-bold',
  };
  return (
    <span
      className={`px-2.5 py-1 rounded-full text-[10px] font-bold border capitalize ${
        map[status?.toLowerCase()] || 'bg-slate-100 text-slate-700'
      }`}
    >
      {status || 'Menunggu'}
    </span>
  );
};

export default function PengelolaDashboard() {
  // 1. Gedung yang dikelola Bu Eci
  const { data: listLokasi = [] } = useQuery({
    queryKey: ['pengelola_dashboard_lokasi'],
    queryFn: async () => {
      const res = await api.get('/pengelola/lokasi');
      const data = res.data?.gedung_dikelola || res.data?.lokasi_dikelola || res.data?.data || res.data || [];
      return Array.isArray(data) ? data : [];
    },
  });

  // 2. Antrean pengajuan menunggu
  const { data: listMenunggu = [] } = useQuery({
    queryKey: ['pengelola_dashboard_menunggu'],
    queryFn: async () => {
      const res = await api.get('/pengelola/reservasi', { params: { status: 'menunggu' } });
      return res.data?.data || res.data || [];
    },
  });

  // 3. Kasus penggusuran terbuka
  const { data: listKasus = [] } = useQuery({
    queryKey: ['pengelola_dashboard_kasus'],
    queryFn: async () => {
      const res = await api.get('/pengelola/kasus', { params: { status: 'terbuka' } });
      return res.data?.data || res.data || [];
    },
  });

  // 4. Pengajuan disetujui
  const { data: listDisetujui = [] } = useQuery({
    queryKey: ['pengelola_dashboard_disetujui'],
    queryFn: async () => {
      const res = await api.get('/pengelola/reservasi', { params: { status: 'disetujui' } });
      return res.data?.data || res.data || [];
    },
  });

  const statCards = [
    { label: 'Menunggu Persetujuan', value: listMenunggu.length, color: 'bg-amber-50 text-amber-900 border-amber-200', icon: '⏳' },
    { label: 'Kasus Negosiasi Terbuka', value: listKasus.length, color: 'bg-rose-50 text-rose-900 border-rose-200', icon: '⚖️' },
    { label: 'Peminjaman Disetujui', value: listDisetujui.length, color: 'bg-emerald-50 text-emerald-900 border-emerald-200', icon: '✅' },
    { label: 'Total Gedung Dikelola', value: listLokasi.length, color: 'bg-blue-50 text-blue-900 border-blue-200', icon: '🏛️' },
  ];

  const recentList = [...listMenunggu, ...listDisetujui].slice(0, 5);

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 mb-1">Dashboard Pengelola Gedung</h1>
          <p className="text-sm text-slate-500">
            Selamat datang, <strong>Bu Eci</strong>. Anda bertindak sebagai penanggung jawab tunggal seluruh gedung kampus POLINELA.
          </p>
        </div>
        <Link
          to="/pengelola/pengajuan"
          className="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition w-fit"
        >
          Lihat Semua Pengajuan ({listMenunggu.length}) →
        </Link>
      </div>

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map((card) => (
          <div key={card.label} className={`rounded-2xl p-5 border shadow-sm flex items-center gap-4 ${card.color}`}>
            <span className="text-3xl">{card.icon}</span>
            <div>
              <div className="text-3xl font-extrabold">{card.value}</div>
              <div className="text-xs font-semibold mt-1 opacity-90">{card.label}</div>
            </div>
          </div>
        ))}
      </div>

      {/* Antrean Pengajuan Masuk */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-slate-100">
          <h2 className="text-sm font-bold text-slate-800">Permohonan Terbaru Perlu Verifikasi</h2>
          <Link to="/pengelola/pengajuan" className="text-xs text-blue-600 hover:underline font-semibold">
            Buka Antrean Lengkap →
          </Link>
        </div>

        {recentList.length === 0 ? (
          <div className="py-12 text-center text-slate-400 text-xs">
            <p className="text-2xl mb-1.5">📭</p>
            <p>Belum ada permohonan reservasi yang masuk.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                  <th className="py-3 px-4 text-left">Kode</th>
                  <th className="py-3 px-4 text-left">Pemohon</th>
                  <th className="py-3 px-4 text-left">Gedung</th>
                  <th className="py-3 px-4 text-left">Jadwal Acara</th>
                  <th className="py-3 px-4 text-left">Status</th>
                  <th className="py-3 px-4 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {recentList.map((row) => (
                  <tr key={row.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4 font-mono font-bold text-blue-600">{row.kode || row.id.slice(0, 8)}</td>
                    <td className="py-3 px-4 font-medium text-slate-800">
                      <div>{row.pemohon || row.pengguna?.nama || '-'}</div>
                      <div className="text-[10px] text-slate-400">{row.organisasi || row.pengguna?.organisasi || '-'}</div>
                    </td>
                    <td className="py-3 px-4 text-slate-700 font-semibold">{row.gedung || row.lokasi?.nama || '-'}</td>
                    <td className="py-3 px-4 text-slate-600 whitespace-nowrap">
                      {row.mulai ? new Date(row.mulai).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'}
                    </td>
                    <td className="py-3 px-4">{statusBadge(row.status)}</td>
                    <td className="py-3 px-4">
                      <Link
                        to={`/pengelola/pengajuan/${row.id}`}
                        className="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition inline-block"
                      >
                        Periksa Berkas
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Gedung yang Dikelola Bu Eci */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h2 className="text-sm font-bold text-slate-800">Seluruh Gedung Dikelola (Penanggung Jawab: Bu Eci)</h2>
            <p className="text-xs text-slate-400 mt-0.5">Semua permohonan peminjaman gedung ini masuk ke meja persetujuan Anda.</p>
          </div>
          <Link to="/pengelola/atur-jadwal" className="text-xs text-blue-600 hover:underline font-semibold">
            Atur Jadwal &amp; Acara Kampus →
          </Link>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          {listLokasi.map((g) => (
            <div
              key={g.id}
              className="border border-slate-200 rounded-2xl p-4 flex flex-col justify-between hover:border-blue-300 hover:shadow-sm transition bg-slate-50/50"
            >
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <span className="text-xl">🏛️</span>
                  <span className="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                    Gedung Resmi
                  </span>
                </div>
                <h3 className="font-bold text-slate-800 text-sm">{g.nama}</h3>
                <p className="text-xs text-slate-500 mt-0.5">Kapasitas: <span className="font-semibold text-slate-700">{g.kapasitas} orang</span></p>
                {formatFasilitas(g.fasilitas) && (
                  <p className="text-[11px] text-slate-400 mt-1 line-clamp-2">{formatFasilitas(g.fasilitas)}</p>
                )}
              </div>
              <div className="mt-3 pt-2.5 border-t border-slate-200/60 flex items-center justify-between">
                <span className="text-[10px] text-emerald-700 font-semibold">✓ Penugasan Aktif</span>
                <Link to="/pengelola/atur-jadwal" className="text-xs font-semibold text-blue-600 hover:underline">
                  Jadwal →
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
