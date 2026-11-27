import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';
import { formatFasilitas } from '../../utils/format.js';

export default function LaporanSistem() {
  const { data: ringkasan = {}, isLoading: loadingRingkasan } = useQuery({
    queryKey: ['sistem_laporan_ringkasan'],
    queryFn: async () => {
      const res = await api.get('/laporan/ringkasan');
      return res.data || {};
    },
  });

  const { data: listLokasi = [], isLoading: loadingLokasi } = useQuery({
    queryKey: ['sistem_laporan_lokasi'],
    queryFn: async () => {
      const res = await api.get('/sistem/lokasi');
      return res.data?.data || res.data || [];
    },
  });

  const totalGedung = ringkasan.total_gedung ?? listLokasi.filter((l) => l.jenis === 'gedung').length;
  const totalRuangan = ringkasan.total_ruangan ?? listLokasi.filter((l) => l.jenis === 'ruangan').length;
  const totalReservasi = ringkasan.total_reservasi ?? ringkasan.total_reservasi_aktif ?? 0;
  const statusCounts = ringkasan.status_reservasi || ringkasan.status || {};

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-slate-900 mb-1">Laporan Sistem &amp; Utilisasi Fasilitas</h1>
        <p className="text-sm text-slate-500">
          Rekapitulasi penggunaan gedung dan ruangan di lingkungan kampus POLINELA (tanpa detail privat pemohon).
        </p>
      </div>

      <div className="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 text-xs text-slate-600 flex items-center gap-2">
        <span className="text-base">🔒</span>
        <span>
          <strong>Kepatuhan Privasi:</strong> Laporan sistem diagregasi dalam bentuk angka statistik ketersediaan dan utilisasi
          fasilitas guna perencanaan alokasi anggaran tanpa mengungkap identitas spesifik pemohon.
        </span>
      </div>

      {loadingRingkasan || loadingLokasi ? (
        <div className="py-20 text-center text-xs text-slate-400">Memuat data laporan sistem...</div>
      ) : (
        <>
          {/* Ringkasan Angka */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 text-center">
              <p className="text-3xl font-extrabold text-slate-800 mb-1">{totalGedung}</p>
              <p className="text-xs font-semibold text-slate-500">Total Gedung Kampus</p>
            </div>
            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 text-center">
              <p className="text-3xl font-extrabold text-blue-600 mb-1">{totalRuangan}</p>
              <p className="text-xs font-semibold text-slate-500">Total Ruangan / Lab</p>
            </div>
            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 text-center">
              <p className="text-3xl font-extrabold text-indigo-600 mb-1">{totalReservasi}</p>
              <p className="text-xs font-semibold text-slate-500">Total Permohonan Masuk</p>
            </div>
            <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 text-center">
              <p className="text-3xl font-extrabold text-emerald-600 mb-1">
                {statusCounts.disetujui ?? ringkasan.total_disetujui ?? 0}
              </p>
              <p className="text-xs font-semibold text-slate-500">Permohonan Disetujui</p>
            </div>
          </div>

          {/* Tabel Kapasitas Lokasi Kampus */}
          <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
            <h2 className="text-sm font-bold text-slate-800 pb-2 border-b border-slate-100">
              Daftar Fasilitas &amp; Daya Tampung Kampus
            </h2>
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                    <th className="py-3 px-4 text-left">Nama Fasilitas</th>
                    <th className="py-3 px-4 text-left">Jenis</th>
                    <th className="py-3 px-4 text-left">Daya Tampung</th>
                    <th className="py-3 px-4 text-left">Kelengkapan Fasilitas</th>
                    <th className="py-3 px-4 text-left">Status Operasional</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {listLokasi.map((l) => (
                    <tr key={l.id} className="hover:bg-slate-50/70 transition">
                      <td className="py-3 px-4 font-semibold text-slate-800">{l.nama}</td>
                      <td className="py-3 px-4">
                        <span
                          className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                            l.jenis === 'gedung' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'
                          }`}
                        >
                          {l.jenis}
                        </span>
                      </td>
                      <td className="py-3 px-4 font-medium text-slate-700">{l.kapasitas} orang</td>
                      <td className="py-3 px-4 text-slate-500">{formatFasilitas(l.fasilitas) || '-'}</td>
                      <td className="py-3 px-4">
                        <span
                          className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                            l.aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                          }`}
                        >
                          {l.aktif ? 'Aktif' : 'Nonaktif'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
