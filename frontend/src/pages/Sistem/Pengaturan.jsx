import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/klien.js';

export default function Pengaturan() {
  const qc = useQueryClient();
  const [waAdminNomor, setWaAdminNomor] = useState('');
  const [pesanSukses, setPesanSukses] = useState('');

  // 1. Fetch pengaturan sistem
  const { data: pengaturan = {}, isLoading } = useQuery({
    queryKey: ['sistem_pengaturan'],
    queryFn: async () => {
      const res = await api.get('/sistem/pengaturan');
      return res.data || {};
    },
  });

  useEffect(() => {
    if (pengaturan?.wa_admin_nomor) {
      setWaAdminNomor(pengaturan.wa_admin_nomor);
    }
  }, [pengaturan]);

  // 2. Mutasi Simpan Pengaturan
  const mutasiSimpan = useMutation({
    mutationFn: async (payload) => {
      setPesanSukses('');
      const res = await api.put('/sistem/pengaturan', payload);
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sistem_pengaturan'] });
      setPesanSukses('Pengaturan sistem berhasil disimpan.');
    },
    onError: (err) => {
      alert(err.response?.data?.galat || 'Gagal menyimpan pengaturan.');
    },
  });

  const handleSimpan = (e) => {
    e.preventDefault();
    mutasiSimpan.mutate({
      wa_admin_nomor: waAdminNomor.trim(),
    });
  };

  return (
    <div className="max-w-3xl mx-auto p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-slate-900 mb-1">Pengaturan Sistem</h1>
        <p className="text-sm text-slate-500">
          Konfigurasi nomor narahubung darurat WhatsApp dan parameter operasional SMARTSPACE.
        </p>
      </div>

      {pesanSukses && (
        <div className="p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs text-emerald-800 flex items-center justify-between">
          <span>✅ {pesanSukses}</span>
          <button onClick={() => setPesanSukses('')} className="font-bold">×</button>
        </div>
      )}

      <form onSubmit={handleSimpan} className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        <h2 className="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3">
          Kontak WhatsApp Admin / Pengelola (Bu Eci)
        </h2>

        <div className="text-xs space-y-1">
          <label className="block text-slate-700 font-semibold">Nomor WhatsApp Resmi Admin *</label>
          <input
            type="text"
            value={waAdminNomor}
            onChange={(e) => setWaAdminNomor(e.target.value)}
            placeholder="Contoh: 6281234567890 (format internasional tanpa tanda +)"
            className="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 font-mono"
          />
          <p className="text-[11px] text-slate-400 mt-1">
            Nomor ini menjadi tujuan tombol <strong>"Chat Bu Eci via WhatsApp"</strong> di halaman negosiasi penggusuran,
            detail reservasi, dan daftar belum diputuskan pengelola.
          </p>
        </div>

        <div className="pt-2">
          <button
            type="submit"
            disabled={mutasiSimpan.isPending || isLoading}
            className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-sm transition"
          >
            {mutasiSimpan.isPending ? 'Menyimpan...' : 'Simpan Pengaturan'}
          </button>
        </div>
      </form>
    </div>
  );
}
