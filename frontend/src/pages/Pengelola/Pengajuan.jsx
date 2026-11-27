import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/klien.js';

const statusBadge = (status) => {
  const map = {
    menunggu: 'bg-amber-100 text-amber-800 border-amber-200',
    disetujui: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    ditolak: 'bg-rose-100 text-rose-800 border-rose-200',
    tergusur: 'bg-red-100 text-red-800 border-red-300 font-bold',
    dibatalkan: 'bg-slate-100 text-slate-600 border-slate-200',
  };
  const key = status?.toLowerCase() || 'menunggu';
  return (
    <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold border capitalize ${map[key] || 'bg-slate-100 text-slate-700'}`}>
      {status || 'Menunggu'}
    </span>
  );
};

const fetchDaftarPengajuan = async (status) => {
  const res = await api.get('/pengelola/reservasi', { params: { status } });
  return res.data?.data || res.data || [];
};

export default function Pengajuan() {
  const [filterStatus, setFilterStatus] = useState('semua');

  const { data: listPengajuan = [], isLoading, isError, refetch } = useQuery({
    queryKey: ['pengelola_reservasi', filterStatus],
    queryFn: () => fetchDaftarPengajuan(filterStatus),
  });

  // Backend sudah memfilter — gunakan data langsung tanpa filter ulang di client
  const filtered = listPengajuan;

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 mb-1">Daftar Pengajuan Masuk</h1>
          <p className="text-sm text-slate-500">
            Daftar permohonan reservasi khusus gedung yang Anda kelola (Bu Eci).
          </p>
        </div>
        <button
          onClick={() => refetch()}
          className="px-3.5 py-2 text-xs font-semibold bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-700 shadow-sm transition w-fit"
        >
          🔄 Segarkan Antrean
        </button>
      </div>

      {/* Filter Tabs */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
          <label className="text-xs font-semibold text-slate-600">Filter Status:</label>
          <div className="flex gap-2 flex-wrap">
            {['semua', 'menunggu', 'disetujui', 'ditolak'].map((st) => (
              <button
                key={st}
                onClick={() => setFilterStatus(st)}
                className={`px-3 py-1 rounded-lg text-xs font-semibold capitalize transition ${
                  filterStatus === st
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                }`}
              >
                {st}
              </button>
            ))}
          </div>
        </div>

        {/* Table */}
        {isLoading ? (
          <div className="py-20 text-center">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent mb-3"></div>
            <p className="text-xs text-slate-500">Memuat data pengajuan dari server...</p>
          </div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center text-slate-400 text-sm">
            <p className="text-3xl mb-2">📥</p>
            <p>Tidak ada permohonan peminjaman dalam kategori ini.</p>
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
                  <th className="py-3 px-4 text-left">Keperluan</th>
                  <th className="py-3 px-4 text-left">Status</th>
                  <th className="py-3 px-4 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filtered.map((row) => (
                  <tr key={row.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4 font-mono font-bold text-blue-600">{row.kode || row.id.slice(0, 8)}</td>
                    <td className="py-3 px-4 font-medium text-slate-800">
                      <div>{row.pengguna?.nama || row.pemohon || 'Pemohon'}</div>
                      <div className="text-[10px] text-slate-400">{row.pengguna?.organisasi || '-'}</div>
                    </td>
                    <td className="py-3 px-4 text-slate-700 font-medium">{row.lokasi?.nama || row.gedung || '-'}</td>
                    <td className="py-3 px-4 text-slate-600 whitespace-nowrap">
                      <div>{row.mulai}</div>
                      <div className="text-[10px] text-slate-400">s/d {row.selesai}</div>
                    </td>
                    <td className="py-3 px-4 text-slate-600 max-w-xs truncate">{row.keperluan || '-'}</td>
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
    </div>
  );
}
