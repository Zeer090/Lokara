import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

const formatWaktu = (iso) => {
  if (!iso) return '-';
  try {
    return new Date(iso).toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      timeZone: 'Asia/Jakarta',
    }) + ' WIB';
  } catch {
    return iso;
  }
};

export default function Log() {
  const [tabAktif, setTabAktif] = useState('agent'); // 'agent' | 'dokumen'

  // 1. Fetch Log Agent
  const { data: logsAgent = [], isLoading: loadingAgent, refetch: refetchAgent } = useQuery({
    queryKey: ['sistem_log_agent'],
    queryFn: async () => {
      const res = await api.get('/sistem/log/agent');
      return res.data?.data || res.data || [];
    },
    enabled: tabAktif === 'agent',
  });

  // 2. Fetch Log Dokumen
  const { data: logsDokumen = [], isLoading: loadingDokumen, refetch: refetchDokumen } = useQuery({
    queryKey: ['sistem_log_dokumen'],
    queryFn: async () => {
      const res = await api.get('/sistem/log/akses-dokumen');
      return res.data?.data || res.data || [];
    },
    enabled: tabAktif === 'dokumen',
  });

  const isLoading = tabAktif === 'agent' ? loadingAgent : loadingDokumen;
  const currentLogs = tabAktif === 'agent' ? logsAgent : logsDokumen;

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 mb-1">Log &amp; Audit Sistem</h1>
          <p className="text-sm text-slate-500">
            Jejak aktivitas pengambilan keputusan Agen AI dan pencatatan audit akses dokumen PDF (hanya baca).
          </p>
        </div>
        <button
          onClick={() => (tabAktif === 'agent' ? refetchAgent() : refetchDokumen())}
          className="px-3.5 py-2 text-xs font-semibold bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-700 shadow-sm transition w-fit"
        >
          🔄 Segarkan Log
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-slate-200 pb-2">
        <button
          onClick={() => setTabAktif('agent')}
          className={`px-4 py-2 text-xs font-semibold rounded-xl transition ${
            tabAktif === 'agent'
              ? 'bg-blue-600 text-white shadow-sm'
              : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
          }`}
        >
          🤖 Log Aktivitas Agen AI
        </button>
        <button
          onClick={() => setTabAktif('dokumen')}
          className={`px-4 py-2 text-xs font-semibold rounded-xl transition ${
            tabAktif === 'dokumen'
              ? 'bg-blue-600 text-white shadow-sm'
              : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
          }`}
        >
          📄 Audit Akses Dokumen (Surat &amp; Memo)
        </button>
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        {isLoading ? (
          <div className="py-20 text-center text-xs text-slate-400">Memuat log sistem...</div>
        ) : currentLogs.length === 0 ? (
          <div className="py-16 text-center text-slate-400">
            <p className="text-3xl mb-2">📜</p>
            <p className="text-sm">Belum ada catatan log pada kategori ini.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                  <th className="py-3 px-4 text-left">Waktu Kejadian</th>
                  <th className="py-3 px-4 text-left">Kategori / Aksi</th>
                  <th className="py-3 px-4 text-left">Uraian Aktivitas</th>
                  <th className="py-3 px-4 text-left">Aktor / Entitas</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {currentLogs.map((l) => (
                  <tr key={l.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap font-mono text-[11px]">
                      {formatWaktu(l.dibuat_pada || l.created_at || l.waktu)}
                    </td>
                    <td className="py-3 px-4">
                      <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        {l.aksi || l.tipe || (tabAktif === 'agent' ? 'AI Action' : 'Document Access')}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-slate-700 max-w-md">
                      {l.pesan || l.keterangan || (typeof l.konteks === 'object' ? JSON.stringify(l.konteks) : l.konteks) || '-'}
                    </td>
                    <td className="py-3 px-4 text-slate-600 font-medium">
                      {l.pengguna?.nama || l.user || l.pengguna_id || 'Sistem'}
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
