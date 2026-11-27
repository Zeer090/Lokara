import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

export default function GedungTanpaPengelola() {
  const { data: listGedung = [], isLoading, refetch } = useQuery({
    queryKey: ['sistem_gedung_tanpa_pengelola'],
    queryFn: async () => {
      const res = await api.get('/sistem/gedung-tanpa-pengelola');
      return res.data?.data || res.data || [];
    },
  });

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 mb-1">Gedung Tanpa Pengelola</h1>
          <p className="text-sm text-slate-500">
            Daftar fasilitas gedung yang belum memiliki staf pengelola yang bertugas.
          </p>
        </div>
        <Link
          to="/sistem/penugasan"
          className="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition w-fit"
        >
          + Buka Penugasan Gedung
        </Link>
      </div>

      <div className="bg-amber-50 border border-amber-200 p-4 rounded-2xl text-xs text-amber-900 leading-relaxed">
        <strong>Ketentuan Privasi &amp; Keamanan:</strong> Demi menjaga kerahasiaan pemohon, panel pengelola sistem
        hanya menampilkan angka rekapitulasi antrean permohonan yang tertahan tanpa mengungkap nama pemohon, tujuan
        kegiatan, maupun dokumen surat permohonan.
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        {isLoading ? (
          <div className="py-16 text-center text-xs text-slate-400">Memuat data gedung...</div>
        ) : listGedung.length === 0 ? (
          <div className="py-16 text-center text-slate-500">
            <p className="text-3xl mb-2">🎉</p>
            <p className="text-sm font-semibold text-slate-800">Semua Gedung Sudah Memiliki Pengelola</p>
            <p className="text-xs text-slate-400 mt-1">Tidak ada gedung yang terbengkalai tanpa penugasan resmi.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                  <th className="py-3 px-4 text-left">Nama Gedung</th>
                  <th className="py-3 px-4 text-left">Kapasitas</th>
                  <th className="py-3 px-4 text-left">Label Status</th>
                  <th className="py-3 px-4 text-left">Antrean Menunggu</th>
                  <th className="py-3 px-4 text-left">Tindakan</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {listGedung.map((g) => {
                  const antrean = g.pengajuan_menunggu_count ?? g.jumlah_menunggu ?? 0;
                  return (
                    <tr key={g.id} className="hover:bg-slate-50/70 transition">
                      <td className="py-3 px-4 font-semibold text-slate-800">{g.nama}</td>
                      <td className="py-3 px-4 text-slate-600">{g.kapasitas} orang</td>
                      <td className="py-3 px-4">
                        {g.sementara ? (
                          <span className="px-2 py-0.5 bg-amber-100 text-amber-800 border border-amber-200 rounded-md text-[10px] font-medium">
                            Data contoh
                          </span>
                        ) : (
                          <span className="text-slate-400 text-[10px]">-</span>
                        )}
                      </td>
                      <td className="py-3 px-4">
                        {antrean > 0 ? (
                          <span className="px-2.5 py-1 bg-rose-100 text-rose-700 font-bold rounded-full text-[10px]">
                            {antrean} permohonan tertahan
                          </span>
                        ) : (
                          <span className="text-slate-400">0 permohonan</span>
                        )}
                      </td>
                      <td className="py-3 px-4">
                        <Link
                          to="/sistem/penugasan"
                          className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-semibold transition"
                        >
                          Tugaskan →
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
}
