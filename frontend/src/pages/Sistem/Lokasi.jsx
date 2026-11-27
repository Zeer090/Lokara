import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/klien.js';
import { formatFasilitas } from '../../utils/format.js';

export default function LokasiSistem() {
  const qc = useQueryClient();
  const [modalBuka, setModalBuka] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({
    nama: '',
    jenis: 'ruangan',
    kapasitas: 40,
    fasilitas: '',
    sementara: false,
  });

  // 1. Fetch semua lokasi
  const { data: listLokasi = [], isLoading } = useQuery({
    queryKey: ['sistem_lokasi_list'],
    queryFn: async () => {
      const res = await api.get('/sistem/lokasi');
      return res.data?.data || res.data || [];
    },
  });

  // 2. Mutasi Simpan / Edit
  const mutasiSimpan = useMutation({
    mutationFn: async (payload) => {
      if (editId) {
        const res = await api.put(`/sistem/lokasi/${editId}`, payload);
        return res.data;
      } else {
        const res = await api.post('/sistem/lokasi', payload);
        return res.data;
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sistem_lokasi_list'] });
      setModalBuka(false);
      setEditId(null);
      setForm({ nama: '', jenis: 'ruangan', kapasitas: 40, fasilitas: '', sementara: false });
    },
    onError: (err) => {
      alert(err.response?.data?.galat || err.response?.data?.message || 'Gagal menyimpan data lokasi.');
    },
  });

  // 3. Mutasi Toggle Status Aktif
  const mutasiToggleStatus = useMutation({
    mutationFn: async ({ id, aktif }) => {
      const res = await api.patch(`/sistem/lokasi/${id}/status`, { aktif });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sistem_lokasi_list'] });
    },
    onError: (err) => {
      alert(err.response?.data?.galat || 'Gagal mengubah status lokasi.');
    },
  });

  const bukaModalEdit = (lok) => {
    setEditId(lok.id);
    setForm({
      nama: lok.nama,
      jenis: lok.jenis,
      kapasitas: lok.kapasitas,
      fasilitas: formatFasilitas(lok.fasilitas),
      sementara: Boolean(lok.sementara),
    });
    setModalBuka(true);
  };

  const bukaModalTambah = () => {
    setEditId(null);
    setForm({ nama: '', jenis: 'ruangan', kapasitas: 40, fasilitas: '', sementara: false });
    setModalBuka(true);
  };

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 mb-1">Kelola Data Lokasi &amp; Fasilitas</h1>
          <p className="text-sm text-slate-500">
            Daftar gedung dan ruangan di lingkungan Politeknik Negeri Lampung.
          </p>
        </div>
        <button
          onClick={bukaModalTambah}
          className="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition w-fit"
        >
          + Tambah Lokasi Baru
        </button>
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        {isLoading ? (
          <div className="py-20 text-center text-xs text-slate-400">Memuat data lokasi...</div>
        ) : listLokasi.length === 0 ? (
          <div className="py-16 text-center text-slate-400">
            <p className="text-3xl mb-2">📍</p>
            <p className="text-sm">Belum ada data gedung atau ruangan.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                  <th className="py-3 px-4 text-left">Nama Lokasi</th>
                  <th className="py-3 px-4 text-left">Jenis</th>
                  <th className="py-3 px-4 text-left">Kapasitas</th>
                  <th className="py-3 px-4 text-left">Fasilitas</th>
                  <th className="py-3 px-4 text-left">Label Status</th>
                  <th className="py-3 px-4 text-left">Status</th>
                  <th className="py-3 px-4 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {listLokasi.map((l) => (
                  <tr key={l.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4 font-semibold text-slate-800">{l.nama}</td>
                    <td className="py-3 px-4">
                      <span
                        className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase ${l.jenis === 'gedung'
                          ? 'bg-purple-100 text-purple-800 border border-purple-200'
                          : 'bg-blue-100 text-blue-800 border border-blue-200'
                          }`}
                      >
                        {l.jenis}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-slate-700 font-medium">{l.kapasitas} orang</td>
                    <td className="py-3 px-4 text-slate-500 max-w-xs truncate">{formatFasilitas(l.fasilitas) || '-'}</td>
                    <td className="py-3 px-4">
                      {l.sementara && (
                        <span className="px-2 py-0.5 bg-amber-100 text-amber-800 border border-amber-200 rounded-md text-[10px] font-medium">
                          Data contoh
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${l.aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                          }`}
                      >
                        {l.aktif ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </td>
                    <td className="py-3 px-4 flex items-center gap-1.5">
                      <button
                        onClick={() => bukaModalEdit(l)}
                        className="text-xs px-2.5 py-1 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg transition"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => mutasiToggleStatus.mutate({ id: l.id, aktif: !l.aktif })}
                        disabled={mutasiToggleStatus.isPending}
                        className={`text-xs px-2.5 py-1 rounded-lg border transition ${l.aktif
                          ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
                          : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                          }`}
                      >
                        {l.aktif ? 'Nonaktifkan' : 'Aktifkan'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal Tambah/Edit */}
      {modalBuka && (
        <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md space-y-4">
            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
              <h3 className="text-base font-bold text-slate-800">{editId ? 'Ubah Data Lokasi' : 'Tambah Lokasi Baru'}</h3>
              <button onClick={() => setModalBuka(false)} className="text-slate-400 hover:text-slate-600 text-lg">×</button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-600 font-semibold mb-1">Nama Gedung / Ruangan *</label>
                <input
                  type="text"
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  placeholder="Misal: Gedung Serbaguna / Lab Komputer 3"
                  className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-slate-600 font-semibold mb-1">Jenis Lokasi *</label>
                <select
                  value={form.jenis}
                  onChange={(e) => setForm({ ...form, jenis: e.target.value })}
                  className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option value="ruangan">Ruangan (Persetujuan Otomatis, Tanpa Surat)</option>
                  <option value="gedung">Gedung (Persetujuan Manual Bu Eci, Wajib Surat PDF)</option>
                </select>
              </div>

              <div>
                <label className="block text-slate-600 font-semibold mb-1">Kapasitas (Orang) *</label>
                <input
                  type="number"
                  min="1"
                  value={form.kapasitas}
                  onChange={(e) => setForm({ ...form, kapasitas: Number(e.target.value) })}
                  className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-slate-600 font-semibold mb-1">Fasilitas Tersedia</label>
                <textarea
                  rows={2}
                  value={form.fasilitas}
                  onChange={(e) => setForm({ ...form, fasilitas: e.target.value })}
                  placeholder="AC, Proyektor, Sound System, Kursi Lipat (pisahkan koma)"
                  className="w-full border border-slate-300 rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>

              <label className="flex items-center gap-2 cursor-pointer pt-1">
                <input
                  type="checkbox"
                  checked={form.sementara}
                  onChange={(e) => setForm({ ...form, sementara: e.target.checked })}
                  className="rounded text-blue-600"
                />
                <span className="text-slate-600 font-medium">Tandai sebagai "Data contoh" (sementara)</span>
              </label>
            </div>

            <div className="flex gap-2 pt-2">
              <button
                onClick={() => mutasiSimpan.mutate(form)}
                disabled={mutasiSimpan.isPending || !form.nama.trim()}
                className="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white py-2.5 rounded-xl text-xs font-semibold shadow-sm transition"
              >
                {mutasiSimpan.isPending ? 'Menyimpan...' : 'Simpan Data'}
              </button>
              <button
                onClick={() => setModalBuka(false)}
                className="px-4 py-2.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition"
              >
                Batal
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
