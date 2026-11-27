import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../api/klien.js';

export default function TombolChatAdmin({ reservasiId, kasusId, className = '' }) {
  const { data, isLoading } = useQuery({
    queryKey: ['chat_admin_tautan', { reservasiId, kasusId }],
    queryFn: async () => {
      const params = {};
      if (kasusId) params.kasus_id = kasusId;
      else if (reservasiId) params.reservasi_id = reservasiId;
      const res = await api.get('/chat-admin/tautan', { params });
      return res.data;
    },
    staleTime: 60000,
  });

  const tautan = data?.tautan;
  const tersedia = data?.tersedia !== false && Boolean(tautan);

  if (isLoading) {
    return (
      <button
        disabled
        className={`px-4 py-2 bg-slate-100 text-slate-400 rounded-xl text-xs font-semibold cursor-not-allowed border border-slate-200 ${className}`}
      >
        Memeriksa kontak WA...
      </button>
    );
  }

  if (!tersedia) {
    return (
      <span
        title="Nomor WhatsApp admin belum dikonfigurasi di sistem"
        className={`inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 text-slate-400 rounded-xl text-xs font-medium border border-slate-200 cursor-not-allowed ${className}`}
      >
        <span>💬</span> Kontak admin belum tersedia
      </span>
    );
  }

  return (
    <a
      href={tautan}
      target="_blank"
      rel="noopener noreferrer"
      className={`inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition active:scale-95 ${className}`}
    >
      <span>💬</span> Chat Bu Eci via WhatsApp
    </a>
  );
}
