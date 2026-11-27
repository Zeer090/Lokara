import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/klien.js';

const STATUS_BADGE = {
  baru:     { label: 'Baru',     cls: 'bg-rose-100 text-rose-700 border-rose-200' },
  diproses: { label: 'Diproses', cls: 'bg-amber-100 text-amber-800 border-amber-200' },
  selesai:  { label: 'Selesai',  cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  ditolak:  { label: 'Ditolak',  cls: 'bg-slate-100 text-slate-600 border-slate-200' },
};

export default function LaporanFasilitasPage({ mode = 'sistem' }) {
  const qc = useQueryClient();
  const endpointBase = mode === 'pengelola' ? '/pengelola/laporan-fasilitas' : '/sistem/laporan-fasilitas';

  const [filterStatus, setFilterStatus] = useState('');
  const [selectedLaporan, setSelectedLaporan] = useState(null);
  const [statusBaru, setStatusBaru] = useState('diproses');
  const [catatan, setCatatan] = useState('');
  const [previewFoto, setPreviewFoto] = useState(null);

  const { data, isLoading } = useQuery({
    queryKey: ['manajemen_laporan_fasilitas', mode, filterStatus],
    queryFn: async () => {
      const res = await api.get(endpointBase, {
        params: filterStatus ? { status: filterStatus } : {},
      });
      return res.data?.data || res.data || [];
    },
  });

  const laporanList = Array.isArray(data) ? data : [];

  const mutasiUpdate = useMutation({
    mutationFn: async ({ id, status, catatan_penanganan }) => {
      const res = await api.patch(`${endpointBase}/${id}`, { status, catatan_penanganan });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['manajemen_laporan_fasilitas'] });
      qc.invalidateQueries({ queryKey: ['publik_beranda_lokasi'] });
      setSelectedLaporan(null);
    },
    onError: (err) => {
      alert(err.response?.data?.galat || 'Gagal memperbarui status laporan.');
    },
  });

  const bukaModal = (lap) => {
    setSelectedLaporan(lap);
    setStatusBaru(lap.status === 'baru' ? 'diproses' : lap.status);
    setCatatan(lap.catatan_penanganan || '');
  };

  const countByStatus = (st) => laporanList.filter((l) => l.status === st).length;

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
            Laporan Kerusakan Fasilitas
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
            {mode === 'pengelola'
              ? 'Daftar laporan kerusakan dari mahasiswa untuk gedung-gedung yang Anda kelola.'
              : 'Seluruh laporan kerusakan ruangan & gedung kampus beserta hasil analisis AI.'}
          </p>
        </div>

        <select
          value={filterStatus}
          onChange={(e) => setFilterStatus(e.target.value)}
          className="border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-semibold bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Semua Status</option>
          <option value="baru">Baru</option>
          <option value="diproses">Diproses</option>
          <option value="selesai">Selesai</option>
          <option value="ditolak">Ditolak</option>
        </select>
      </div>

      {/* Counter Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {[
          { key: 'baru', label: 'Laporan Baru', cls: 'bg-rose-50 border-rose-200 text-rose-700' },
          { key: 'diproses', label: 'Sedang Diproses', cls: 'bg-amber-50 border-amber-200 text-amber-800' },
          { key: 'selesai', label: 'Selesai Diperbaiki', cls: 'bg-emerald-50 border-emerald-200 text-emerald-700' },
          { key: 'ditolak', label: 'Ditolak / Tidak Valid', cls: 'bg-slate-100 border-slate-200 text-slate-600' },
        ].map((c) => (
          <div key={c.key} className={`rounded-2xl border p-4 shadow-xs ${c.cls}`}>
            <p className="text-xs font-semibold opacity-80">{c.label}</p>
            <p className="text-2xl font-extrabold mt-1">{countByStatus(c.key)}</p>
          </div>
        ))}
      </div>

      {/* List */}
      {isLoading ? (
        <div className="py-16 text-center text-sm text-slate-500">Memuat laporan fasilitas...</div>
      ) : laporanList.length === 0 ? (
        <div className="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
          <p className="text-3xl mb-2">🛠️</p>
          <p className="text-sm font-semibold text-slate-700">Belum ada laporan fasilitas</p>
          <p className="text-xs mt-1">Laporan mahasiswa dari chatbot Asisten Informasi akan muncul di sini.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {laporanList.map((lap) => {
            const st = STATUS_BADGE[lap.status] || STATUS_BADGE.baru;
            const aiItems = lap.hasil_analisis_ai?.item_rusak || [];

            return (
              <div
                key={lap.id}
                className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 flex flex-col md:flex-row gap-4 justify-between"
              >
                <div className="flex gap-4 flex-1">
                  {/* Thumbnail Foto Kamera */}
                  {lap.foto_url ? (
                    <button
                      type="button"
                      onClick={() => setPreviewFoto(lap.foto_url)}
                      className="w-24 h-24 rounded-xl overflow-hidden border border-slate-200 flex-shrink-0 hover:opacity-90 transition"
                      title="Klik untuk memperbesar foto"
                    >
                      <img src={lap.foto_url} alt="Bukti kerusakan" className="w-full h-full object-cover" />
                    </button>
                  ) : (
                    <div className="w-24 h-24 rounded-xl bg-slate-100 border border-slate-200 flex-shrink-0 flex flex-col items-center justify-center text-slate-400 text-[10px]">
                      <span className="text-xl mb-0.5">📷</span>
                      <span>Tanpa foto</span>
                    </div>
                  )}

                  {/* Detail Laporan */}
                  <div className="space-y-1.5 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border ${st.cls}`}>
                        {st.label}
                      </span>
                      <span className="text-xs font-bold text-slate-800">
                        📍 {lap.lokasi?.nama || 'Lokasi belum spesifik'}
                      </span>
                      <span className="text-[11px] text-slate-400">
                        • {lap.pelapor ? `👤 ${lap.pelapor.nama}` : '👤 Anonim / Tamu'}
                      </span>
                    </div>

                    <h3 className="text-sm font-bold text-slate-900">{lap.ringkasan}</h3>
                    <p className="text-xs text-slate-600 leading-relaxed">{lap.isi}</p>

                    {aiItems.length > 0 && (
                      <div className="flex flex-wrap items-center gap-1.5 pt-1">
                        <span className="text-[10px] font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">
                          🤖 AI Terintegrasi ke Lokasi:
                        </span>
                        {aiItems.map((it, idx) => (
                          <span
                            key={idx}
                            className="text-[10px] font-medium bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded-full"
                          >
                            {String(it.nama || '').toUpperCase()}: {it.status}
                          </span>
                        ))}
                      </div>
                    )}

                    {lap.catatan_penanganan && (
                      <p className="text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-1 mt-1">
                        💬 <strong>Catatan Penanganan:</strong> {lap.catatan_penanganan}
                      </p>
                    )}
                  </div>
                </div>

                {/* Action */}
                <div className="flex md:flex-col items-end justify-between gap-2 flex-shrink-0">
                  <span className="text-[10px] text-slate-400">
                    {new Date(lap.dibuat_pada).toLocaleString('id-ID', {
                      day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                    })}
                  </span>
                  <button
                    type="button"
                    onClick={() => bukaModal(lap)}
                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs transition"
                  >
                    Tindak Lanjuti
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Modal Update Status */}
      {selectedLaporan && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md p-6 space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="text-sm font-bold text-slate-900">Tindak Lanjuti Laporan</h3>
              <button onClick={() => setSelectedLaporan(null)} className="text-slate-400 hover:text-slate-700 font-bold">
                ×
              </button>
            </div>

            <div className="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200/70">
              <p className="font-bold text-slate-800">{selectedLaporan.lokasi?.nama || 'Lokasi Umum'}</p>
              <p className="mt-0.5">{selectedLaporan.isi}</p>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1">Status Penanganan</label>
              <select
                value={statusBaru}
                onChange={(e) => setStatusBaru(e.target.value)}
                className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="baru">Baru</option>
                <option value="diproses">Sedang Diproses</option>
                <option value="selesai">Selesai (Otomatis Pulihkan Deskripsi Fasilitas Lokasi)</option>
                <option value="ditolak">Ditolak</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1">Catatan Penanganan</label>
              <textarea
                rows={3}
                value={catatan}
                onChange={(e) => setCatatan(e.target.value)}
                placeholder="Mis. Teknisi sudah memperbaiki AC dan kursi telah diganti..."
                className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div className="flex gap-2 pt-2">
              <button
                type="button"
                disabled={mutasiUpdate.isPending}
                onClick={() =>
                  mutasiUpdate.mutate({
                    id: selectedLaporan.id,
                    status: statusBaru,
                    catatan_penanganan: catatan,
                  })
                }
                className="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white py-2 rounded-xl text-xs font-bold transition"
              >
                {mutasiUpdate.isPending ? 'Menyimpan...' : 'Simpan Perubahan'}
              </button>
              <button
                type="button"
                onClick={() => setSelectedLaporan(null)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold"
              >
                Batal
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Preview Foto */}
      {previewFoto && (
        <div
          onClick={() => setPreviewFoto(null)}
          className="fixed inset-0 bg-slate-900/80 flex items-center justify-center p-4 z-50 cursor-pointer"
        >
          <img
            src={previewFoto}
            alt="Foto bukti kerusakan"
            className="max-w-full max-h-[85vh] rounded-2xl shadow-2xl border border-white/20"
          />
        </div>
      )}
    </div>
  );
}
