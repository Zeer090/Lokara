import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../api/klien.js';

const fetchLokasi = async () => {
  const res = await api.get('/lokasi');
  return res.data?.data || res.data || [];
};

export default function Lokasi() {
  const [filterJenis, setFilterJenis] = useState('semua'); // 'semua' | 'gedung' | 'ruangan'

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['lokasi'],
    queryFn: fetchLokasi,
  });

  const allLokasi = Array.isArray(data) ? data : [];
  const filteredLokasi = filterJenis === 'semua'
    ? allLokasi
    : allLokasi.filter((l) => l.jenis === filterJenis);

  const countGedung = allLokasi.filter((l) => l.jenis === 'gedung').length;
  const countRuangan = allLokasi.filter((l) => l.jenis === 'ruangan').length;

  return (
    <div className="min-h-screen bg-slate-50 py-10 px-4 sm:px-6 lg:px-8">
      <div className="max-w-6xl mx-auto space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-slate-900 mb-1">Katalog Gedung &amp; Ruangan</h1>
            <p className="text-sm text-slate-500">
              Pilihan lokasi resmi kampus untuk seminar, kuliah umum, rapat, praktikum, dan kegiatan organisasi.
            </p>
          </div>
          <div className="flex items-center gap-2 text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-full w-fit">
            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Database PostgreSQL Terhubung</span>
          </div>
        </div>

        {/* 🔘 PEMISAH TAB: FILTER KATEGORI GEDUNG VS RUANGAN */}
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-2 flex gap-2 w-fit flex-wrap">
          <button
            onClick={() => setFilterJenis('semua')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition ${
              filterJenis === 'semua'
                ? 'bg-slate-900 text-white shadow-sm'
                : 'text-slate-600 hover:bg-slate-100'
            }`}
          >
            Semua Lokasi ({allLokasi.length})
          </button>
          <button
            onClick={() => setFilterJenis('gedung')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ${
              filterJenis === 'gedung'
                ? 'bg-purple-600 text-white shadow-sm'
                : 'text-purple-700 hover:bg-purple-50'
            }`}
          >
            <span>🏛️</span>
            <span>Gedung Saja ({countGedung})</span>
          </button>
          <button
            onClick={() => setFilterJenis('ruangan')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ${
              filterJenis === 'ruangan'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-blue-700 hover:bg-blue-50'
            }`}
          >
            <span>🚪</span>
            <span>Ruangan Saja ({countRuangan})</span>
          </button>
        </div>

        {isLoading ? (
          <div className="py-24 text-center">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent mb-3"></div>
            <p className="text-sm text-slate-500">Memuat data lokasi dari database...</p>
          </div>
        ) : isError ? (
          <div className="p-8 bg-white border border-slate-200 rounded-2xl text-center space-y-3 shadow-sm">
            <p className="text-3xl">📡</p>
            <p className="text-slate-800 font-semibold text-sm">Sedang menghubungkan ulang ke server backend...</p>
            <button
              onClick={() => refetch()}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition"
            >
              🔄 Coba Muat Ulang
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {filteredLokasi.map((l) => {
              let fasilitasList = [];
              if (l.fasilitas && typeof l.fasilitas === 'object' && !Array.isArray(l.fasilitas)) {
                fasilitasList = Object.entries(l.fasilitas)
                  .filter(([_, v]) => Boolean(v))
                  .map(([k]) => k.replace('_', ' ').toUpperCase());
              } else if (Array.isArray(l.fasilitas)) {
                fasilitasList = l.fasilitas;
              } else if (typeof l.fasilitas === 'string') {
                fasilitasList = l.fasilitas.split(',').map((f) => f.trim());
              }

              const isGedung = l.jenis === 'gedung';

              return (
                <div
                  key={l.id}
                  className={`bg-white rounded-2xl border shadow-sm hover:shadow-md transition-all p-6 flex flex-col justify-between ${
                    isGedung ? 'border-purple-200/80' : 'border-blue-200/80'
                  }`}
                >
                  <div>
                    <div className="flex items-start justify-between mb-3">
                      <div>
                        <span className={`inline-block text-[11px] font-semibold uppercase px-2.5 py-0.5 rounded-full mb-1.5 ${
                          isGedung ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'
                        }`}>
                          {isGedung ? '🏛️ Gedung' : '🚪 Ruangan'}
                        </span>
                        <h2 className="text-lg font-bold text-slate-800 leading-snug">{l.nama}</h2>
                      </div>
                      {l.sementara && (
                        <span className="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full border border-amber-200 whitespace-nowrap">
                          Data contoh
                        </span>
                      )}
                    </div>

                    <p className="text-xs text-slate-500 mb-2">
                      Kapasitas:{' '}
                      <span className="font-semibold text-slate-800 text-sm">{l.kapasitas} orang</span>
                    </p>

                    <p className="text-[11px] text-slate-400 mb-3 italic">
                      {isGedung ? 'Wajib surat izin PDF • Disetujui Pengelola' : 'Otomatis disetujui • Tanpa surat'}
                    </p>

                    <div className="space-y-1 mb-6">
                      <p className="text-[11px] font-medium text-slate-400">Fasilitas:</p>
                      <div className="flex flex-wrap gap-1.5">
                        {fasilitasList.length > 0 ? (
                          fasilitasList.map((f, i) => (
                            <span
                              key={i}
                              className="bg-slate-100 text-slate-700 text-xs px-2.5 py-1 rounded-lg font-medium"
                            >
                              {f}
                            </span>
                          ))
                        ) : (
                          <span className="text-xs text-slate-400 italic">Standar</span>
                        )}
                      </div>
                    </div>
                  </div>

                  <Link
                    to={`/app/ajukan?jenis=${l.jenis}`}
                    className={`w-full text-center text-white text-xs font-semibold py-2.5 rounded-xl transition shadow-sm ${
                      isGedung
                        ? 'bg-purple-600 hover:bg-purple-700'
                        : 'bg-blue-600 hover:bg-blue-700'
                    }`}
                  >
                    Ajukan {isGedung ? 'Gedung' : 'Ruangan'} Ini →
                  </Link>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
