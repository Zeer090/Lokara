import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/klien.js';

export default function Penugasan() {
  const qc = useQueryClient();
  const [form, setForm] = useState({ pengguna_id: '', lokasi_id: '' });
  const [errorMsg, setErrorMsg] = useState('');

  // 1. Fetch daftar penugasan aktif
  const { data: listPenugasan = [], isLoading } = useQuery({
    queryKey: ['sistem_penugasan'],
    queryFn: async () => {
      const res = await api.get('/sistem/penugasan');
      return res.data?.data || res.data || [];
    },
  });

  // 2. Fetch calon pengelola (peran = pengelola_gedung)
  const { data: listPengelola = [] } = useQuery({
    queryKey: ['sistem_pengguna_pengelola'],
    queryFn: async () => {
      const res = await api.get('/sistem/pengguna', { params: { peran: 'pengelola_gedung' } });
      return res.data?.data || res.data || [];
    },
  });

  // 3. Fetch gedung (hanya jenis = gedung)
  const { data: listGedung = [] } = useQuery({
    queryKey: ['sistem_lokasi_gedung'],
    queryFn: async () => {
      const res = await api.get('/sistem/lokasi', { params: { jenis: 'gedung' } });
      const raw = res.data?.data || res.data || [];
      return raw.filter((l) => l.jenis === 'gedung');
    },
  });

  // 4. Mutasi Tambah Penugasan
  const mutasiTambah = useMutation({
    mutationFn: async (payload) => {
      setErrorMsg('');
      const res = await api.post('/sistem/penugasan', payload);
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sistem_penugasan'] });
      qc.invalidateQueries({ queryKey: ['sistem_gedung_tanpa_pengelola'] });
      setForm({ pengguna_id: '', lokasi_id: '' });
      alert('Penugasan pengelola gedung berhasil disimpan.');
    },
    onError: (err) => {
      setErrorMsg(err.response?.data?.galat || err.response?.data?.message || 'Gagal menyimpan penugasan.');
    },
  });

  // 5. Mutasi Hapus Penugasan
  const mutasiHapus = useMutation({
    mutationFn: async (id) => {
      if (!window.confirm('Batalkan penugasan pengelola atas gedung ini?')) return;
      const res = await api.delete(`/sistem/penugasan/${id}`);
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sistem_penugasan'] });
      qc.invalidateQueries({ queryKey: ['sistem_gedung_tanpa_pengelola'] });
    },
    onError: (err) => {
      alert(err.response?.data?.galat || 'Gagal menghapus penugasan.');
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!form.pengguna_id || !form.lokasi_id) {
      setErrorMsg('Pilih pengelola dan gedung terlebih dahulu.');
      return;
    }
    mutasiTambah.mutate(form);
  };

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-slate-900 mb-1">Penugasan Pengelola Gedung</h1>
        <p className="text-sm text-slate-500">
          Tautkan akun staf pengelola gedung resmi (Bu Eci &amp; tim) ke fasilitas gedung yang menjadi wewenangnya.
        </p>
      </div>

      <div className="bg-blue-50 border border-blue-200 p-4 rounded-2xl text-xs text-blue-800 flex items-start gap-2">
        <span className="text-base">ℹ️</span>
        <span>
          <strong>Ketentuan Bisnis:</strong> Ruangan (kelas, lab, ruang rapat) tidak memiliki pengelola karena
          persetujuannya otomatis. Penugasan hanya berlaku untuk <strong>Gedung</strong> yang memerlukan verifikasi manual surat permohonan.
        </span>
      </div>

      {/* Form Tambah Penugasan */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        <h2 className="font-bold text-slate-800 text-sm">Tugaskan Pengelola Baru</h2>
        {errorMsg && (
          <div className="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-medium">
            ❌ {errorMsg}
          </div>
        )}

        <form onSubmit={handleSubmit} className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs items-end">
          <div>
            <label className="block text-slate-600 font-semibold mb-1">Pilih Gedung Kampus *</label>
            <select
              value={form.lokasi_id}
              onChange={(e) => setForm({ ...form, lokasi_id: e.target.value })}
              className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">-- Pilih Gedung --</option>
              {listGedung.map((g) => (
                <option key={g.id} value={g.id}>
                  {g.nama}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-slate-600 font-semibold mb-1">Pilih Staf Pengelola Gedung *</label>
            <select
              value={form.pengguna_id}
              onChange={(e) => setForm({ ...form, pengguna_id: e.target.value })}
              className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">-- Pilih Pengelola --</option>
              {listPengelola.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.nama} ({p.email})
                </option>
              ))}
            </select>
          </div>

          <div>
            <button
              type="submit"
              disabled={mutasiTambah.isPending}
              className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white py-2 rounded-xl text-xs font-semibold shadow-sm transition"
            >
              {mutasiTambah.isPending ? 'Menyimpan...' : 'Simpan Penugasan'}
            </button>
          </div>
        </form>
      </div>

      {/* Tabel Penugasan Aktif */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        <h2 className="font-bold text-slate-800 text-sm">Daftar Penugasan Aktif</h2>

        {isLoading ? (
          <div className="py-16 text-center text-xs text-slate-400">Memuat data penugasan...</div>
        ) : listPenugasan.length === 0 ? (
          <div className="py-16 text-center text-slate-400">
            <p className="text-3xl mb-2">🔗</p>
            <p className="text-sm">Belum ada gedung yang ditugaskan ke pengelola.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                  <th className="py-3 px-4 text-left">Gedung Kampus</th>
                  <th className="py-3 px-4 text-left">Pengelola Gedung</th>
                  <th className="py-3 px-4 text-left">Email &amp; No. HP</th>
                  <th className="py-3 px-4 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {listPenugasan.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4 font-semibold text-slate-800">{item.lokasi?.nama || '-'}</td>
                    <td className="py-3 px-4 font-medium text-emerald-800">{item.pengguna?.nama || '-'}</td>
                    <td className="py-3 px-4 text-slate-600">
                      <div>{item.pengguna?.email}</div>
                      <div className="text-[10px] text-slate-400">{item.pengguna?.no_hp || '-'}</div>
                    </td>
                    <td className="py-3 px-4">
                      <button
                        onClick={() => mutasiHapus.mutate(item.id)}
                        disabled={mutasiHapus.isPending}
                        className="text-xs px-2.5 py-1 border border-rose-200 text-rose-600 hover:bg-rose-50 rounded-lg transition"
                      >
                        Hapus Penugasan
                      </button>
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
