import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/klien.js';

const roleBadge = {
  pengguna: 'bg-blue-100 text-blue-800 border-blue-200',
  pengelola_gedung: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  pengelola_sistem: 'bg-purple-100 text-purple-800 border-purple-200',
  akademik: 'bg-amber-100 text-amber-800 border-amber-200',
};

export default function Pengguna() {
  const qc = useQueryClient();
  const [search, setSearch] = useState('');
  const [filterPeran, setFilterPeran] = useState('semua');
  const [errorMessage, setErrorMessage] = useState('');

  // 1. Fetch data pengguna
  const { data: listPengguna = [], isLoading, refetch } = useQuery({
    queryKey: ['sistem_pengguna'],
    queryFn: async () => {
      const res = await api.get('/sistem/pengguna');
      return res.data?.data || res.data || [];
    },
  });

  // 2. Mutasi ubah peran
  const mutasiUbahPeran = useMutation({
    mutationFn: async ({ id, peran }) => {
      setErrorMessage('');
      const res = await api.post(`/sistem/pengguna/${id}/peran`, { peran });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sistem_pengguna'] });
    },
    onError: (err) => {
      setErrorMessage(err.response?.data?.galat || err.response?.data?.message || 'Gagal mengubah peran pengguna.');
    },
  });

  // 3. Mutasi ubah status aktif
  const mutasiStatusAktif = useMutation({
    mutationFn: async ({ id, aktif }) => {
      setErrorMessage('');
      const res = await api.patch(`/sistem/pengguna/${id}/aktif`, { aktif });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sistem_pengguna'] });
    },
    onError: (err) => {
      setErrorMessage(err.response?.data?.galat || 'Gagal mengubah status aktif pengguna.');
    },
  });

  const filtered = (Array.isArray(listPengguna) ? listPengguna : []).filter((u) => {
    const matchSearch =
      (u.nama || '').toLowerCase().includes(search.toLowerCase()) ||
      (u.email || '').toLowerCase().includes(search.toLowerCase()) ||
      (u.organisasi || '').toLowerCase().includes(search.toLowerCase());
    const matchRole = filterPeran === 'semua' || u.peran === filterPeran;
    return matchSearch && matchRole;
  });

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 mb-1">Kelola Pengguna &amp; Hak Akses</h1>
          <p className="text-sm text-slate-500">
            Daftar akun terdaftar pada sistem SMARTSPACE POLINELA beserta penetapan perannya.
          </p>
        </div>
        <button
          onClick={() => refetch()}
          className="px-3.5 py-2 text-xs font-semibold bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-700 shadow-sm transition w-fit"
        >
          🔄 Segarkan
        </button>
      </div>

      <div className="p-3.5 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-800 flex items-start gap-2">
        <span className="text-base">⚠️</span>
        <span>
          <strong>Kebijakan Integritas:</strong> Kenaikan peran pengguna (misal menjadi Pengelola Gedung) akan ditolak
          oleh server jika akun tersebut sedang memiliki permohonan reservasi yang masih aktif atau kasus penggusuran
          terbuka.
        </span>
      </div>

      {errorMessage && (
        <div className="p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 flex items-center justify-between">
          <span>❌ {errorMessage}</span>
          <button onClick={() => setErrorMessage('')} className="font-bold ml-2">×</button>
        </div>
      )}

      {/* Filter and Table */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pb-3 border-b border-slate-100">
          <input
            type="text"
            placeholder="Cari nama, email, atau organisasi..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full sm:max-w-xs text-xs border border-slate-300 rounded-xl px-3.5 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500"
          />

          <div className="flex items-center gap-2 self-start sm:self-auto">
            <label className="text-xs text-slate-500 font-semibold">Filter Peran:</label>
            <select
              value={filterPeran}
              onChange={(e) => setFilterPeran(e.target.value)}
              className="text-xs border border-slate-300 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="semua">Semua Peran</option>
              <option value="pengguna">Pengguna (Mahasiswa/Umum)</option>
              <option value="pengelola_gedung">Pengelola Gedung (Bu Eci)</option>
              <option value="pengelola_sistem">Pengelola Sistem (Admin)</option>
              <option value="akademik">Bagian Akademik (BAAK)</option>
            </select>
          </div>
        </div>

        {isLoading ? (
          <div className="py-20 text-center text-xs text-slate-400">Memuat data pengguna...</div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center text-slate-400">
            <p className="text-3xl mb-2">👤</p>
            <p className="text-sm">Tidak ada pengguna yang sesuai kriteria pencarian.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                  <th className="py-3 px-4 text-left">Nama Lengkap</th>
                  <th className="py-3 px-4 text-left">Email &amp; Kontak</th>
                  <th className="py-3 px-4 text-left">Organisasi / Lembaga</th>
                  <th className="py-3 px-4 text-left">Peran Sistem</th>
                  <th className="py-3 px-4 text-left">Status</th>
                  <th className="py-3 px-4 text-left">Aksi Perubahan</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filtered.map((u) => (
                  <tr key={u.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4 font-semibold text-slate-800">{u.nama}</td>
                    <td className="py-3 px-4 text-slate-600">
                      <div>{u.email}</div>
                      <div className="text-[10px] text-slate-400">{u.no_hp || '-'}</div>
                    </td>
                    <td className="py-3 px-4 text-slate-600">{u.organisasi || '-'}</td>
                    <td className="py-3 px-4">
                      <span
                        className={`px-2.5 py-1 rounded-full text-[10px] font-bold border capitalize ${
                          roleBadge[u.peran] || 'bg-slate-100 text-slate-700'
                        }`}
                      >
                        {u.peran ? u.peran.replace('_', ' ') : 'pengguna'}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                          u.aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                        }`}
                      >
                        {u.aktif ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </td>
                    <td className="py-3 px-4 flex items-center gap-2">
                      <select
                        value={u.peran}
                        onChange={(e) => mutasiUbahPeran.mutate({ id: u.id, peran: e.target.value })}
                        disabled={mutasiUbahPeran.isPending}
                        className="text-[11px] border border-slate-300 rounded-lg px-2 py-1 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                      >
                        <option value="pengguna">Pengguna</option>
                        <option value="pengelola_gedung">Pengelola Gedung</option>
                        <option value="pengelola_sistem">Pengelola Sistem</option>
                        <option value="akademik">Akademik</option>
                      </select>

                      <button
                        onClick={() => mutasiStatusAktif.mutate({ id: u.id, aktif: !u.aktif })}
                        disabled={mutasiStatusAktif.isPending}
                        className={`text-[10px] font-semibold px-2 py-1 rounded-lg border transition ${
                          u.aktif
                            ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
                            : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                        }`}
                      >
                        {u.aktif ? 'Nonaktifkan' : 'Aktifkan'}
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
