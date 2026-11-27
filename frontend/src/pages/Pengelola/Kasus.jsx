import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

const fmtTgl = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleDateString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta',
  });
};

const statusMap = {
  terbuka:        { label: 'Belum Diputuskan',       cls: 'bg-orange-100 text-orange-700 border-orange-200' },
  diputuskan:     { label: 'Menunggu Pengajuan Ulang', cls: 'bg-blue-100 text-blue-700 border-blue-200' },
  diajukan_ulang: { label: 'Menunggu ACC',            cls: 'bg-indigo-100 text-indigo-700 border-indigo-200' },
  selesai:        { label: 'Selesai',                 cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  diserahkan:     { label: 'Ditangani Bu Eci',        cls: 'bg-slate-100 text-slate-600 border-slate-200' },
};

const StatusBadge = ({ status }) => {
  const s = statusMap[status] || { label: status, cls: 'bg-slate-100 text-slate-600 border-slate-200' };
  return (
    <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold border capitalize ${s.cls}`}>
      {s.label}
    </span>
  );
};

export default function Kasus() {
  const [filterStatus, setFilterStatus] = useState('semua');

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['pengelola_kasus', filterStatus],
    queryFn: async () => {
      const params = filterStatus !== 'semua' ? { status: filterStatus } : {};
      const res = await api.get('/pengelola/kasus', { params });
      return res.data?.data || res.data || [];
    },
  });

  const daftar = Array.isArray(data) ? data : [];

  const filterPills = ['semua', 'terbuka', 'diputuskan', 'diajukan_ulang', 'selesai', 'diserahkan'];

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 mb-1">Daftar Kasus Penggusuran</h1>
          <p className="text-sm text-slate-500">Kasus yang timbul dari reservasi gedung yang Anda kelola.</p>
        </div>
        <button
          onClick={() => refetch()}
          className="px-3.5 py-2 text-xs font-semibold bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-700 shadow-sm transition w-fit"
        >
          🔄 Segarkan
        </button>
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        {/* Filter Pills */}
        <div className="flex items-center gap-3 border-b border-slate-100 pb-4 flex-wrap">
          <label className="text-xs font-semibold text-slate-600">Filter Status:</label>
          <div className="flex gap-2 flex-wrap">
            {filterPills.map(s => (
              <button
                key={s}
                onClick={() => setFilterStatus(s)}
                className={`px-3 py-1 rounded-lg text-xs font-semibold capitalize transition ${
                  filterStatus === s
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                }`}
              >
                {s === 'semua' ? 'Semua' : (statusMap[s]?.label || s)}
              </button>
            ))}
          </div>
        </div>

        {/* Konten */}
        {isLoading ? (
          <div className="py-20 text-center">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent mb-3" />
            <p className="text-xs text-slate-500">Memuat data kasus...</p>
          </div>
        ) : isError ? (
          <div className="py-16 text-center text-rose-500 text-sm">
            <p className="text-3xl mb-2">⚠️</p>
            <p>Gagal memuat data. <button onClick={() => refetch()} className="underline">Coba lagi</button></p>
          </div>
        ) : daftar.length === 0 ? (
          <div className="py-16 text-center text-slate-400 text-sm">
            <p className="text-3xl mb-2">📂</p>
            <p>Tidak ada kasus dalam kategori ini.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                  <th className="py-3 px-4 text-left">Kode Reservasi</th>
                  <th className="py-3 px-4 text-left">Pemohon</th>
                  <th className="py-3 px-4 text-left">Gedung</th>
                  <th className="py-3 px-4 text-left">Acara Penyebab</th>
                  <th className="py-3 px-4 text-left">Jadwal Tergusur</th>
                  <th className="py-3 px-4 text-left">Status</th>
                  <th className="py-3 px-4 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {daftar.map((k) => (
                  <tr key={k.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4 font-mono font-bold text-blue-600">
                      {k.kode_reservasi || k.id.slice(0, 8)}
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-medium text-slate-800">{k.pemohon || '-'}</div>
                      <div className="text-[10px] text-slate-400">{k.organisasi || ''}</div>
                    </td>
                    <td className="py-3 px-4 text-slate-700 font-medium">{k.gedung || '-'}</td>
                    <td className="py-3 px-4 text-slate-500">{k.acara_nama || <span className="italic text-slate-300">—</span>}</td>
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">{fmtTgl(k.mulai_tergusur)}</td>
                    <td className="py-3 px-4"><StatusBadge status={k.status} /></td>
                    <td className="py-3 px-4">
                      <Link
                        to={`/pengelola/kasus/${k.id}`}
                        className="bg-slate-700 hover:bg-slate-800 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition inline-block"
                      >
                        Lihat Detail
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
