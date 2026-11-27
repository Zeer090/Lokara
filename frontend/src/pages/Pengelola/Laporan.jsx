import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

export default function LaporanPengelola() {
  const { data, isLoading } = useQuery({
    queryKey: ['pengelola_laporan_ringkasan'],
    queryFn: async () => {
      const res = await api.get('/pengelola/laporan');
      return res.data || {};
    },
  });

  const ringkasan = data?.ringkasan || {};
  const total = ringkasan.total_reservasi || 0;
  const disetujui = ringkasan.disetujui || 0;
  const tergusur = ringkasan.tergusur || 0;
  const ditolak = ringkasan.ditolak || 0;
  const acaraKampus = ringkasan.acara_kampus || 0;

  const rasioPersetujuan = total > 0 ? Math.round((disetujui / total) * 100) : 0;

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-slate-900 mb-1">Laporan Operasional Gedung</h1>
        <p className="text-sm text-slate-500">
          Statistik reservasi gedung, persetujuan peminjaman, dan riwayat dampak acara kampus (Bu Eci).
        </p>
      </div>

      {isLoading ? (
        <div className="py-20 text-center text-xs text-slate-400">Memuat data laporan gedung...</div>
      ) : (
        <>
          {/* Kartu Ringkasan */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm text-center">
              <p className="text-4xl font-extrabold text-blue-600 mb-1">{total}</p>
              <p className="text-xs font-semibold text-slate-600">Total Permohonan Gedung</p>
            </div>
            <div className="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm text-center">
              <p className="text-4xl font-extrabold text-emerald-600 mb-1">{rasioPersetujuan}%</p>
              <p className="text-xs font-semibold text-slate-600">Tingkat Persetujuan Permohonan</p>
            </div>
            <div className="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm text-center">
              <p className="text-4xl font-extrabold text-indigo-600 mb-1">{acaraKampus}</p>
              <p className="text-xs font-semibold text-slate-600">Acara Resmi Kampus Terdaftar</p>
            </div>
          </div>

          {/* Rincian Status */}
          <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
            <h2 className="text-sm font-bold text-slate-800 pb-2 border-b border-slate-100">
              Rincian Disposisi Permohonan Gedung
            </h2>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div className="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-center">
                <span className="text-2xl font-bold text-emerald-800">{disetujui}</span>
                <p className="text-[11px] font-semibold text-emerald-700 mt-1">Disetujui Resmi</p>
              </div>
              <div className="p-4 rounded-xl bg-rose-50 border border-rose-200 text-center">
                <span className="text-2xl font-bold text-rose-800">{ditolak}</span>
                <p className="text-[11px] font-semibold text-rose-700 mt-1">Ditolak</p>
              </div>
              <div className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-center">
                <span className="text-2xl font-bold text-amber-800">{tergusur}</span>
                <p className="text-[11px] font-semibold text-amber-700 mt-1">Tergusur Acara</p>
              </div>
              <div className="p-4 rounded-xl bg-indigo-50 border border-indigo-200 text-center">
                <span className="text-2xl font-bold text-indigo-800">{acaraKampus}</span>
                <p className="text-[11px] font-semibold text-indigo-700 mt-1">Kegiatan Prioritas</p>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
