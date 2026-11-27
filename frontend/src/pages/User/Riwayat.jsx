import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/klien.js';

const statusColor = {
  disetujui: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  menunggu: 'bg-amber-100 text-amber-800 border-amber-200',
  ditolak: 'bg-rose-100 text-rose-800 border-rose-200',
  dibatalkan: 'bg-slate-100 text-slate-600 border-slate-200',
  tergusur: 'bg-red-100 text-red-700 border-red-300 font-bold',
};

const fetchRiwayatSaya = async () => {
  const res = await api.get('/reservasi/saya');
  return res.data?.data || res.data || [];
};

const Riwayat = () => {
  const [filter, setFilter] = useState('semua');
  const statuses = ['semua', 'menunggu', 'disetujui', 'ditolak', 'dibatalkan'];

  const { data: listReservasi = [], isLoading, isError } = useQuery({
    queryKey: ['reservasi_saya'],
    queryFn: fetchRiwayatSaya,
  });

  const filtered = filter === 'semua'
    ? listReservasi
    : listReservasi.filter((r) => r.status?.toLowerCase() === filter.toLowerCase());

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 mb-1">Riwayat Reservasi Saya</h1>
          <p className="text-sm text-slate-500">
            Daftar permohonan peminjaman ruangan dan gedung yang telah Anda ajukan.
          </p>
        </div>
        <Link
          to="/app/ajukan"
          className="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-sm transition w-fit"
        >
          + Ajukan Baru
        </Link>
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-5">
        {/* Filter Pills */}
        <div className="flex gap-2 flex-wrap border-b border-slate-100 pb-4">
          {statuses.map((s) => (
            <button
              key={s}
              onClick={() => setFilter(s)}
              className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold capitalize transition ${
                filter === s
                  ? 'bg-blue-600 text-white shadow-sm'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              {s}
            </button>
          ))}
        </div>

        {/* Content */}
        {isLoading ? (
          <div className="py-16 text-center">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent mb-3"></div>
            <p className="text-xs text-slate-500">Memuat riwayat reservasi...</p>
          </div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center text-slate-400 text-sm">
            <p className="text-3xl mb-2">📋</p>
            <p>Belum ada riwayat reservasi untuk kategori ini.</p>
            <Link to="/app/ajukan" className="mt-3 inline-block text-xs font-semibold text-blue-600 hover:underline">
              Mulai ajukan peminjaman sekarang →
            </Link>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                  <th className="text-left p-3.5">Kode</th>
                  <th className="text-left p-3.5">Lokasi</th>
                  <th className="text-left p-3.5">Jadwal Acara</th>
                  <th className="text-left p-3.5">Keperluan</th>
                  <th className="text-left p-3.5">Status</th>
                  <th className="text-left p-3.5">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filtered.map((r) => {
                  const statusKey = r.status?.toLowerCase() || 'menunggu';
                  return (
                    <tr key={r.id} className="hover:bg-slate-50/70 transition">
                      <td className="p-3.5 font-mono font-bold text-blue-600">{r.kode || r.id.slice(0, 8)}</td>
                      <td className="p-3.5 font-medium text-slate-800">{r.lokasi?.nama || '-'}</td>
                      <td className="p-3.5 text-slate-600 whitespace-nowrap">
                        <div>{r.mulai}</div>
                        <div className="text-[10px] text-slate-400">s/d {r.selesai}</div>
                      </td>
                      <td className="p-3.5 text-slate-600 max-w-xs truncate">{r.keperluan || '-'}</td>
                      <td className="p-3.5">
                        <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold border capitalize ${
                          statusColor[statusKey] || 'bg-slate-100 text-slate-700'
                        }`}>
                          {r.status}
                        </span>
                      </td>
                      <td className="p-3.5">
                        <Link
                          to={`/app/reservasi/${r.id}`}
                          className="px-2.5 py-1 rounded bg-slate-100 hover:bg-blue-50 hover:text-blue-600 text-slate-700 text-xs font-medium transition"
                        >
                          Detail
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

export default Riwayat;
