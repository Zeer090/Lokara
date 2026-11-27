import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/klien.js';

const fmtWaktu = (iso) => {
  if (!iso) return '-';
  const d = new Date(iso);
  const now = new Date();
  const diffMs = now - d;
  const diffMnt = Math.floor(diffMs / 60000);
  if (diffMnt < 1) return 'Baru saja';
  if (diffMnt < 60) return `${diffMnt} menit lalu`;
  const diffJam = Math.floor(diffMnt / 60);
  if (diffJam < 24) return `${diffJam} jam lalu`;
  const diffHari = Math.floor(diffJam / 24);
  if (diffHari < 7) return `${diffHari} hari lalu`;
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

export default function Notifikasi() {
  const navigate = useNavigate();
  const qc = useQueryClient();

  const { data: notifs = [], isLoading } = useQuery({
    queryKey: ['notifikasi_semua'],
    queryFn: async () => {
      const res = await api.get('/notifikasi');
      return Array.isArray(res.data?.data) ? res.data.data
        : Array.isArray(res.data) ? res.data : [];
    },
  });

  const mutasiBaca = useMutation({
    mutationFn: async (id) => {
      await api.post(`/notifikasi/${id}/baca`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifikasi_semua'] }),
  });

  const mutasiSemua = useMutation({
    mutationFn: async () => {
      await api.post('/notifikasi/baca-semua');
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifikasi_semua'] }),
  });

  const handleKlik = (n) => {
    if (!n.dibaca_pada) mutasiBaca.mutate(n.id);
    if (n.tautan) navigate(n.tautan);
  };

  const adaBelumDibaca = notifs.some(n => !n.dibaca_pada);

  return (
    <div className="max-w-2xl mx-auto p-6 space-y-4">
      <div className="flex justify-between items-center">
        <h1 className="text-3xl font-bold text-slate-900">Notifikasi</h1>
        {adaBelumDibaca && (
          <button
            onClick={() => mutasiSemua.mutate()}
            disabled={mutasiSemua.isPending}
            className="text-sm text-blue-600 hover:underline disabled:opacity-50"
          >
            Tandai Semua Dibaca
          </button>
        )}
      </div>

      {isLoading ? (
        <div className="text-center py-16 text-slate-400 text-sm">Memuat notifikasi…</div>
      ) : notifs.length === 0 ? (
        <div className="text-center py-16 text-slate-400">
          <p className="text-3xl mb-2">🔔</p>
          <p className="text-sm">Belum ada notifikasi.</p>
        </div>
      ) : (
        <div className="bg-white rounded-xl shadow-sm border border-slate-200 divide-y divide-slate-100">
          {notifs.map(n => (
            <div
              key={n.id}
              onClick={() => handleKlik(n)}
              className={`flex items-start gap-3 p-4 cursor-pointer hover:bg-slate-50 transition ${!n.dibaca_pada ? 'bg-blue-50/60' : ''}`}
            >
              <div className={`w-2.5 h-2.5 mt-1.5 rounded-full flex-shrink-0 ${!n.dibaca_pada ? 'bg-blue-500' : 'bg-slate-200'}`} />
              <div className="flex-1 min-w-0">
                <p className={`text-sm ${!n.dibaca_pada ? 'font-semibold text-slate-800' : 'text-slate-700'}`}>
                  {n.judul && <span className="block font-bold">{n.judul}</span>}
                  {n.isi}
                </p>
                <p className="text-xs text-slate-400 mt-0.5">{fmtWaktu(n.dibuat_pada || n.created_at)}</p>
              </div>
              {!n.dibaca_pada && (
                <span className="text-[10px] bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-semibold flex-shrink-0">
                  Baru
                </span>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
