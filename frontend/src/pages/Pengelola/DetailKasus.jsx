import React from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

const fmtTgl = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta',
  }) + ' WIB';
};

const fmtWaktu = (iso) => {
  if (!iso) return '-';
  return new Date(iso).toLocaleString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta',
  });
};

const statusMap = {
  terbuka:        { label: 'Belum Diputuskan',       cls: 'bg-orange-100 text-orange-700 border-orange-200' },
  diputuskan:     { label: 'Menunggu Pengajuan Ulang', cls: 'bg-blue-100 text-blue-700 border-blue-200' },
  diajukan_ulang: { label: 'Menunggu ACC',            cls: 'bg-indigo-100 text-indigo-700 border-indigo-200' },
  selesai:        { label: 'Selesai',                 cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  diserahkan:     { label: 'Ditangani Bu Eci',        cls: 'bg-slate-100 text-slate-600 border-slate-200' },
};

export default function DetailKasus() {
  const { id } = useParams();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['pengelola_kasus_detail', id],
    queryFn: async () => {
      const res = await api.get(`/pengelola/kasus/${id}`);
      return res.data?.kasus || res.data;
    },
  });

  const kasus = data;
  const statusInfo = statusMap[kasus?.status] || { label: kasus?.status, cls: 'bg-slate-100 text-slate-600 border-slate-200' };

  if (isLoading) {
    return (
      <div className="max-w-4xl mx-auto p-6 flex items-center justify-center min-h-64">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-10 w-10 border-4 border-slate-600 border-t-transparent mb-3" />
          <p className="text-sm text-slate-500">Memuat detail kasus...</p>
        </div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="max-w-4xl mx-auto p-6">
        <Link to="/pengelola/kasus" className="text-xs text-slate-400 hover:text-slate-600 mb-4 inline-block">← Kembali</Link>
        <div className="bg-rose-50 border border-rose-200 rounded-xl p-6 text-center">
          <p className="text-rose-700 font-semibold">{error?.response?.data?.galat || 'Gagal memuat data kasus.'}</p>
        </div>
      </div>
    );
  }

  const reservasi = kasus?.reservasi;
  const pesan = kasus?.pesan_negosiasi || [];
  const usulan = kasus?.usulan_agent || [];
  const logAgent = kasus?.log_agent || [];
  const reservasiPengganti = kasus?.reservasi_pengganti;

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div>
        <Link to="/pengelola/kasus" className="text-xs text-slate-400 hover:text-slate-600 mb-2 inline-block">
          ← Kembali ke Daftar Kasus
        </Link>
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Detail Kasus Penggusuran</h1>
            <p className="text-sm font-mono text-slate-500 mt-0.5">{reservasi?.kode || id}</p>
          </div>
          <span className={`text-xs font-bold border px-3 py-1.5 rounded-full w-fit ${statusInfo.cls}`}>
            {statusInfo.label}
          </span>
        </div>
      </div>

      {/* Catatan hanya baca */}
      <div className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
        📋 Halaman ini hanya untuk melihat. Usulan diajukan oleh Agen AI dan dipilih oleh pengguna.
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {/* Reservasi Asal */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
          <h2 className="font-bold text-slate-700 text-sm uppercase tracking-wide">Reservasi yang Tergusur</h2>
          <div className="space-y-1.5 text-sm">
            <div className="flex justify-between">
              <span className="text-slate-500">Kode</span>
              <span className="font-mono font-bold text-blue-600">{reservasi?.kode || '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Gedung</span>
              <span className="font-medium text-slate-800">{reservasi?.lokasi?.nama || '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Pemohon</span>
              <span className="text-slate-700">{reservasi?.pengguna?.nama || '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Organisasi</span>
              <span className="text-slate-700">{reservasi?.pengguna?.organisasi || '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Jadwal</span>
              <span className="text-slate-700 text-right text-xs">{fmtTgl(reservasi?.mulai)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Selesai</span>
              <span className="text-slate-700 text-right text-xs">{fmtTgl(reservasi?.selesai)}</span>
            </div>
            {reservasi?.keperluan && (
              <div className="pt-1">
                <span className="text-slate-500 text-xs">Keperluan:</span>
                <p className="text-slate-700 mt-0.5">{reservasi.keperluan}</p>
              </div>
            )}
          </div>
        </div>

        {/* Info Penggusuran */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
          <h2 className="font-bold text-slate-700 text-sm uppercase tracking-wide">Alasan Penggusuran</h2>
          {kasus?.acara && (
            <div className="text-sm">
              <span className="text-slate-500">Acara kampus: </span>
              <span className="font-medium text-slate-800">{kasus.acara.nama}</span>
            </div>
          )}
          <blockquote className="border-l-4 border-orange-400 bg-orange-50 pl-3 py-2 pr-2 rounded text-sm text-slate-700 italic">
            {kasus?.alasan_penggusuran || kasus?.alasan || 'Tidak ada keterangan tambahan.'}
          </blockquote>

          {/* Reservasi Pengganti */}
          {reservasiPengganti && (
            <div className="mt-3 pt-3 border-t border-slate-100">
              <p className="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Reservasi Pengganti</p>
              <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-sm space-y-1">
                <p className="font-semibold text-indigo-700">{reservasiPengganti.lokasi?.nama || '-'}</p>
                <p className="text-xs text-indigo-600">{fmtTgl(reservasiPengganti.mulai)}</p>
                <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${
                  reservasiPengganti.status === 'disetujui'
                    ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                    : 'bg-amber-100 text-amber-700 border-amber-200'
                }`}>
                  {reservasiPengganti.status}
                </span>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Usulan Agent */}
      {usulan.length > 0 && (
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
          <h2 className="font-bold text-slate-700 text-sm uppercase tracking-wide">Usulan Agen AI</h2>
          <div className="space-y-3">
            {usulan.map((u, i) => (
              <div key={u.id} className={`rounded-lg border p-4 text-sm ${
                u.status === 'dipilih'
                  ? 'bg-indigo-50 border-indigo-200'
                  : 'bg-slate-50 border-slate-200'
              }`}>
                <div className="flex items-center justify-between mb-1">
                  <span className="font-semibold text-slate-800">
                    Opsi {i + 1}: {u.lokasi?.nama || u.lokasi_nama || 'Lokasi'}
                  </span>
                  <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${
                    u.status === 'dipilih'
                      ? 'bg-indigo-100 text-indigo-700 border-indigo-200'
                      : 'bg-slate-100 text-slate-500 border-slate-200'
                  }`}>
                    {u.status === 'dipilih' ? '✓ Dipilih' : u.status}
                  </span>
                </div>
                <p className="text-xs text-slate-500">📅 {fmtTgl(u.mulai)} s.d. {fmtTgl(u.selesai)}</p>
                {u.catatan && <p className="text-xs text-slate-400 italic mt-1">{u.catatan}</p>}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Percakapan Negosiasi */}
      {pesan.length > 0 && (
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
          <h2 className="font-bold text-slate-700 text-sm uppercase tracking-wide">
            Percakapan Negosiasi ({pesan.length} pesan)
          </h2>
          <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
            {pesan.map((m) => (
              <div key={m.id} className={`flex gap-3 text-sm ${m.pengirim === 'pengguna' ? 'flex-row-reverse' : ''}`}>
                <div className={`px-3 py-2 rounded-xl max-w-xs text-sm ${
                  m.pengirim === 'pengguna'
                    ? 'bg-blue-100 text-blue-900'
                    : m.pengirim === 'agent'
                    ? 'bg-slate-100 text-slate-800'
                    : 'bg-amber-50 text-amber-800 border border-amber-200'
                }`}>
                  <p className="text-[10px] font-bold mb-0.5 opacity-60 capitalize">{m.pengirim}</p>
                  <p>{m.pesan}</p>
                  <p className="text-[10px] opacity-50 mt-0.5">{fmtWaktu(m.dibuat_pada)}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Log Agen */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
        <h2 className="font-bold text-slate-700 text-sm uppercase tracking-wide">Log Agen AI</h2>
        {logAgent.length === 0 ? (
          <p className="text-sm text-slate-400 italic">Belum ada log aktivitas agen.</p>
        ) : (
          <ul className="space-y-2.5">
            {logAgent.map((l, i) => (
              <li key={l.id || i} className="flex gap-3 text-sm">
                <span className="text-slate-400 w-40 flex-shrink-0 text-xs">{fmtWaktu(l.dibuat_pada || l.waktu)}</span>
                <span className="text-slate-700">{l.pesan || l.keterangan || JSON.stringify(l)}</span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
