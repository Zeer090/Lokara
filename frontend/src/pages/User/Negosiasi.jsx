import React, { useState, useEffect, useRef } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/klien.js';
import { parseTeksRingkas } from '../../utils/format.js';

function TeksChat({ teks }) {
  return (
    <>
      {parseTeksRingkas(teks).map((tok, i) => {
        if (tok.type === 'bold') return <strong key={i} className="font-semibold">{tok.value}</strong>;
        if (tok.type === 'italic') return <em key={i} className="italic">{tok.value}</em>;
        return <React.Fragment key={i}>{tok.value}</React.Fragment>;
      })}
    </>
  );
}

// ─── Helper ──────────────────────────────────────────────────────────────────
const fmtTgl = (iso) => {
  if (!iso) return '-';
  try {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return String(iso);
    return d.toLocaleString('id-ID', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
      hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta',
    }) + ' WIB';
  } catch {
    return String(iso);
  }
};

const statusKasus = {
  terbuka:       { label: 'Belum Diputuskan',      cls: 'bg-orange-100 text-orange-700 border-orange-200' },
  diputuskan:    { label: 'Menunggu Pengajuan Ulang', cls: 'bg-blue-100 text-blue-700 border-blue-200' },
  diajukan_ulang:{ label: 'Menunggu ACC Pengelola', cls: 'bg-indigo-100 text-indigo-700 border-indigo-200' },
  selesai:       { label: 'Selesai',                cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  diserahkan:    { label: 'Ditangani Bu Eci',       cls: 'bg-slate-100 text-slate-600 border-slate-200' },
};

// ─── Kartu Usulan Agent ───────────────────────────────────────────────────────
function KartuUsulan({ usulan, kasusId, onPilih }) {
  const [konfirmasi, setKonfirmasi] = useState(false);
  const [alasan, setAlasan] = useState('');
  const [loading, setLoading] = useState(false);

  const handlePilih = async () => {
    setLoading(true);
    try {
      await onPilih(usulan.id, alasan);
      setKonfirmasi(false);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="border border-indigo-200 bg-indigo-50/80 rounded-xl p-3 mt-1.5 space-y-1.5 max-w-sm">
      <div className="flex items-center justify-between gap-2">
        <span className="text-indigo-700 font-bold text-[11px] uppercase tracking-wide">💡 Opsi Pengganti</span>
        {usulan.bentrok_dicek && (
          <span className="text-[10px] bg-emerald-100 text-emerald-700 border border-emerald-200 px-1.5 py-0.5 rounded-full font-medium">
            ✓ Bebas bentrok
          </span>
        )}
      </div>
      <p className="text-xs font-bold text-slate-800">{usulan.lokasi?.nama || usulan.lokasi_nama || 'Lokasi Pengganti'}</p>
      <div className="text-[11px] text-slate-600 space-y-0.5 leading-snug">
        <p>📅 {fmtTgl(usulan.mulai)} — {fmtTgl(usulan.selesai)}</p>
        {usulan.kapasitas && <p>👥 Kap. {usulan.kapasitas} org {usulan.catatan ? `• ${usulan.catatan}` : ''}</p>}
      </div>

      {!konfirmasi ? (
        <div className="flex gap-2 pt-1">
          <button
            onClick={() => setKonfirmasi(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition"
          >
            Pilih ini
          </button>
          <button
            onClick={() => {
              // kirim pesan otomatis "minta opsi lain" ditangani di komponen parent
              onPilih(null, '__minta_lain__');
            }}
            className="border border-slate-300 text-slate-600 hover:bg-slate-100 text-xs font-semibold px-4 py-1.5 rounded-lg transition"
          >
            Minta opsi lain
          </button>
        </div>
      ) : (
        <div className="space-y-2 pt-1">
          <p className="text-xs font-medium text-slate-700">Alasan memilih usulan ini (opsional):</p>
          <textarea
            value={alasan}
            onChange={e => setAlasan(e.target.value.slice(0, 500))}
            rows={2}
            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="Contoh: Fasilitas sesuai kebutuhan acara"
          />
          <div className="flex gap-2">
            <button
              onClick={handlePilih}
              disabled={loading}
              className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition"
            >
              {loading ? 'Memilih...' : 'Konfirmasi Pilihan'}
            </button>
            <button onClick={() => setKonfirmasi(false)} className="text-xs text-slate-500 hover:underline">
              Batal
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Layar Langkah Berikutnya (setelah pilih usulan) ─────────────────────────
function LayarLangkahBerikutnya({ kasus, tautanWa, onAjukanUlang, sudahAjukanUlang }) {
  const [step, setStep] = useState(sudahAjukanUlang ? 3 : 1);
  const [loading, setLoading] = useState(false);

  const handleAjukanUlang = async () => {
    setLoading(true);
    try {
      await onAjukanUlang();
      setStep(3);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-blue-50 border border-blue-200 rounded-xl p-5 space-y-4">
      <p className="text-sm font-bold text-blue-800">📋 Langkah Selanjutnya</p>
      <p className="text-xs text-blue-600 italic">
        ⚠️ Pesan ke Bu Eci tidak terkirim otomatis. Tekan tombol di bawah untuk membuka WhatsApp.
      </p>

      {/* Step 1: WA */}
      <div className={`rounded-lg p-3 border ${step >= 1 ? 'bg-white border-blue-200' : 'bg-slate-50 border-slate-200 opacity-50'}`}>
        <p className="text-xs font-semibold text-slate-700 mb-2">Langkah 1 — Minta ACC lewat WhatsApp</p>
        {tautanWa ? (
          <a
            href={tautanWa}
            target="_blank"
            rel="noopener noreferrer"
            onClick={() => step === 1 && setStep(2)}
            className="inline-block bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition"
          >
            💬 Buka WhatsApp Bu Eci
          </a>
        ) : (
          <p className="text-xs text-slate-400 italic">Kontak admin belum tersedia</p>
        )}
      </div>

      {/* Step 2: Ajukan Ulang */}
      <div className={`rounded-lg p-3 border ${step >= 2 ? 'bg-white border-blue-200' : 'bg-slate-50 border-slate-200 opacity-50'}`}>
        <p className="text-xs font-semibold text-slate-700 mb-1">Langkah 2 — Ajukan Reservasi Pengganti</p>
        <p className="text-[11px] text-slate-500 mb-2">Memakai surat yang sama. Tidak perlu unggah surat baru.</p>
        {step === 3 || sudahAjukanUlang ? (
          <span className="text-xs text-emerald-600 font-semibold">✅ Sudah diajukan</span>
        ) : (
          <button
            onClick={handleAjukanUlang}
            disabled={loading || step < 2}
            className="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white text-xs font-semibold px-4 py-2 rounded-lg transition"
          >
            {loading ? 'Mengajukan...' : '📤 Ajukan Reservasi'}
          </button>
        )}
      </div>

      {/* Step 3: Status */}
      {step >= 3 && (
        <div className="rounded-lg p-3 border bg-emerald-50 border-emerald-200">
          <p className="text-xs font-semibold text-emerald-700">Langkah 3 — Menunggu ACC Pengelola Gedung</p>
          <p className="text-[11px] text-emerald-600 mt-1">
            Reservasi pengganti sedang menunggu persetujuan. Anda akan mendapat notifikasi setelah ada keputusan.
          </p>
        </div>
      )}
    </div>
  );
}

// ─── Komponen Utama ───────────────────────────────────────────────────────────
export default function Negosiasi() {
  const { kasusId } = useParams();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const chatEndRef = useRef(null);
  const [input, setInput] = useState('');
  const [agentTyping, setAgentTyping] = useState(false);

  // ─ Fetch detail kasus ─
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['kasus', kasusId],
    queryFn: async () => {
      const res = await api.get(`/kasus/${kasusId}`);
      return res.data;
    },
    refetchInterval: 15000, // polling tiap 15 detik
  });

  const kasus = data?.kasus;
  const tautanWa = data?.tautan_wa;
  const pesan = kasus?.pesan_negosiasi || [];
  const usulan = kasus?.usulan_agent || [];
  const statusInfo = statusKasus[kasus?.status] || statusKasus.terbuka;

  // Scroll ke bawah saat pesan baru masuk
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [pesan.length, agentTyping]);

  // Kirim pesan ke API
  const mutasiKirim = useMutation({
    mutationFn: async (teks) => {
      const res = await api.post(`/kasus/${kasusId}/pesan`, { pesan: teks });
      return res.data;
    },
    onMutate: () => setAgentTyping(true),
    onSettled: () => setAgentTyping(false),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['kasus', kasusId] }),
    onError: (err) => alert(err.response?.data?.galat || 'Gagal mengirim pesan.'),
  });

  // Pilih usulan
  const mutasiPilihUsulan = useMutation({
    mutationFn: async ({ usulanId, alasan }) => {
      if (usulanId === null && alasan === '__minta_lain__') {
        // Kirim pesan otomatis "minta opsi lain"
        const res = await api.post(`/kasus/${kasusId}/pesan`, { pesan: 'Tolong berikan opsi jadwal lain yang berbeda.' });
        return res.data;
      }
      const res = await api.post(`/kasus/${kasusId}/pilih-usulan`, { usulan_id: usulanId, alasan_pilihan: alasan });
      return res.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['kasus', kasusId] }),
    onError: (err) => alert(err.response?.data?.galat || 'Gagal memilih usulan.'),
  });

  // Ajukan ulang
  const mutasiAjukanUlang = useMutation({
    mutationFn: async () => {
      const res = await api.post(`/kasus/${kasusId}/ajukan-ulang`);
      return res.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['kasus', kasusId] }),
    onError: (err) => alert(err.response?.data?.galat || 'Gagal mengajukan ulang.'),
  });

  const handleKirim = () => {
    const teks = input.trim();
    if (!teks || mutasiKirim.isPending) return;
    setInput('');
    mutasiKirim.mutate(teks);
  };

  const handlePilihUsulan = (usulanId, alasan) => {
    mutasiPilihUsulan.mutate({ usulanId, alasan });
  };

  const kasusSelesai = ['selesai', 'diserahkan', 'diajukan_ulang'].includes(kasus?.status);
  const kasusDiserahkan = kasus?.status === 'diserahkan';

  // ─ Loading / Error ─
  if (isLoading) {
    return (
      <div className="max-w-5xl mx-auto p-6 flex items-center justify-center min-h-64">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-10 w-10 border-4 border-indigo-600 border-t-transparent mb-3" />
          <p className="text-sm text-slate-500">Memuat data kasus...</p>
        </div>
      </div>
    );
  }

  if (isError) {
    const is403 = error?.response?.status === 403;
    return (
      <div className="max-w-5xl mx-auto p-6">
        <div className="bg-rose-50 border border-rose-200 rounded-xl p-6 text-center">
          <p className="text-rose-700 font-semibold text-lg mb-2">
            {is403 ? 'Anda tidak berwenang mengakses kasus ini.' : 'Gagal memuat data kasus.'}
          </p>
          <Link to="/app" className="text-sm text-blue-600 hover:underline">← Kembali ke Dashboard</Link>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-5xl mx-auto p-4 sm:p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <Link to="/app" className="text-xs text-slate-400 hover:text-slate-600 mb-1 inline-block">← Dashboard</Link>
          <h1 className="text-2xl font-bold text-slate-900">Negosiasi Jadwal Pengganti</h1>
          <p className="text-sm text-slate-500 font-mono">{kasus?.reservasi?.kode || kasusId}</p>
        </div>
        <span className={`text-xs font-bold border px-3 py-1.5 rounded-full w-fit ${statusInfo.cls}`}>
          {statusInfo.label}
        </span>
      </div>

      {/* Layout 2 kolom */}
      <div className="flex gap-4 flex-col lg:flex-row">
        {/* ── Kolom Kiri: Informasi Kasus ── */}
        <div className="lg:w-80 space-y-3 flex-shrink-0">
          {/* Reservasi Asal */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 space-y-2">
            <p className="text-xs font-bold text-slate-500 uppercase tracking-wide">Reservasi yang Tergusur</p>
            <div className="space-y-1 text-sm text-slate-700">
              <p className="font-semibold text-base text-red-700">{kasus?.reservasi?.lokasi?.nama || '-'}</p>
              <p className="text-xs text-slate-500">📅 {fmtTgl(kasus?.reservasi?.mulai)}</p>
              <p className="text-xs text-slate-500">⏱ s.d. {fmtTgl(kasus?.reservasi?.selesai)}</p>
              {kasus?.reservasi?.keperluan && (
                <p className="text-xs text-slate-500">🎯 {kasus.reservasi.keperluan}</p>
              )}
            </div>
            <span className="inline-block text-[10px] bg-red-100 text-red-700 border border-red-200 px-2 py-0.5 rounded-full font-bold">
              TERGUSUR
            </span>
          </div>

          {/* Alasan Penggusuran */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p className="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Alasan Penggusuran</p>
            {kasus?.acara_kampus && (
              <p className="text-xs text-slate-500 mb-1">
                Acara: <span className="font-medium text-slate-700">{kasus.acara_kampus?.nama || 'Acara Kampus'}</span>
              </p>
            )}
            <blockquote className="border-l-4 border-red-400 bg-red-50 pl-3 py-2 pr-2 rounded text-sm text-slate-700 italic">
              {kasus?.alasan_penggusuran || 'Tidak ada keterangan tambahan.'}
            </blockquote>
          </div>

          {/* Tombol WA */}
          {tautanWa ? (
            <a
              href={tautanWa}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center justify-center gap-2 w-full bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition shadow-sm"
            >
              💬 Chat Bu Eci via WhatsApp
            </a>
          ) : (
            <div className="text-center text-xs text-slate-400 italic bg-slate-50 rounded-xl px-4 py-3 border">
              Kontak admin belum tersedia
            </div>
          )}
        </div>

        {/* ── Kolom Kanan: Chat ── */}
        <div className="flex-1 flex flex-col bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" style={{ minHeight: 480 }}>
          {/* Header chat */}
          <div className="px-4 py-3 border-b border-slate-100 bg-slate-50 flex items-center gap-2">
            <span className="text-sm font-semibold text-slate-700">🤖 Percakapan dengan Agen AI</span>
            <span className="text-[10px] text-slate-400 ml-auto">Usulan ini belum disetujui. Persetujuan akhir hanya dari pengelola gedung.</span>
          </div>

          {/* Area chat */}
          <div className="flex-1 overflow-y-auto p-4 flex flex-col gap-3" style={{ maxHeight: 460 }}>
            {/* Pesan pertama dari sistem */}
            <div className="bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg px-3 py-2 text-center">
              Kasus negosiasi dibuka. Agen AI akan membantu mencari jadwal pengganti.
            </div>

            {pesan.map((m) => {
              const isUser = m.pengirim === 'pengguna';
              const isAgent = m.pengirim === 'agent';
              const penutupStr = 'Usulan ini belum disetujui. Persetujuan akhir hanya dari pengelola gedung.';
              const isiBersih = isAgent && typeof m.pesan === 'string'
                ? m.pesan.replace(penutupStr, '').trim()
                : m.pesan;

              return (
                <div key={m.id} className={`flex flex-col ${isUser ? 'items-end' : 'items-start'}`}>
                  <div
                    className={`max-w-sm lg:max-w-md px-3 py-2 rounded-xl text-[13px] leading-snug whitespace-pre-line ${
                      isUser
                        ? 'bg-blue-600 text-white rounded-br-sm'
                        : 'bg-slate-100 text-slate-800 rounded-bl-sm'
                    }`}
                  >
                    <TeksChat teks={isiBersih} />
                  </div>
                  <span className="text-[10px] text-slate-400 mt-0.5 mx-1">
                    {isAgent ? '🤖 Agen • ' : ''}{new Date(m.dibuat_pada).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}
                  </span>

                  {/* Kartu usulan yang terkait pesan agent */}
                  {isAgent && usulan
                    .filter(u => u.pesan_id === m.id && ['diusulkan', 'ditawarkan'].includes(u.status))
                    .map(u => (
                      <KartuUsulan
                        key={u.id}
                        usulan={u}
                        kasusId={kasusId}
                        onPilih={handlePilihUsulan}
                      />
                    ))
                  }
                </div>
              );
            })}

            {/* Usulan yang aktif ditawarkan oleh Agen AI (fallback/global) */}
            {Array.isArray(usulan) && usulan
              .filter(u => u && (!u.pesan_id || !Array.isArray(pesan) || !pesan.some(p => p && p.id === u.pesan_id)) && ['diusulkan', 'ditawarkan'].includes(u.status))
              .map(u => (
                <div key={u.id} className="items-start flex flex-col">
                  <span className="text-[10px] text-indigo-600 font-semibold mb-0.5 ml-1">🤖 Rekomendasi Jadwal Alternatif</span>
                  <KartuUsulan usulan={u} kasusId={kasusId} onPilih={handlePilihUsulan} />
                </div>
              ))
            }

            {/* Agent sedang mengetik */}
            {agentTyping && (
              <div className="flex items-start gap-2">
                <span className="text-[10px] text-slate-400">🤖 Agen AI</span>
                <div className="bg-slate-100 text-slate-500 px-4 py-2 rounded-xl text-sm italic flex items-center gap-2">
                  <span className="animate-pulse">●</span>
                  <span className="animate-pulse" style={{ animationDelay: '0.2s' }}>●</span>
                  <span className="animate-pulse" style={{ animationDelay: '0.4s' }}>●</span>
                  <span className="ml-1">Agen sedang mencari jadwal…</span>
                </div>
              </div>
            )}

            <div ref={chatEndRef} />
          </div>

          {/* Layar Langkah Berikutnya */}
          {kasus?.status === 'diputuskan' && (
            <div className="border-t border-slate-100 p-4">
              <LayarLangkahBerikutnya
                kasus={kasus}
                tautanWa={tautanWa}
                onAjukanUlang={() => mutasiAjukanUlang.mutateAsync()}
                sudahAjukanUlang={kasus?.status === 'diajukan_ulang'}
              />
            </div>
          )}

          {/* Input chat */}
          {kasusDiserahkan ? (
            <div className="border-t border-slate-100 p-4 bg-slate-50">
              <div className="rounded-xl bg-amber-50 border border-amber-200 p-3 text-center space-y-2">
                <p className="text-sm font-semibold text-amber-800">Kasus ini telah diserahkan ke pengelola.</p>
                <p className="text-xs text-amber-600">Silakan hubungi Bu Eci langsung via WhatsApp.</p>
                {tautanWa && (
                  <a
                    href={tautanWa}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-block bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-5 py-2 rounded-lg transition"
                  >
                    💬 Chat Bu Eci
                  </a>
                )}
              </div>
            </div>
          ) : kasusSelesai && kasus?.status !== 'diputuskan' ? (
            <div className="border-t border-slate-100 p-3 bg-emerald-50 text-center">
              <p className="text-sm text-emerald-700 font-semibold">✅ Kasus ini telah {statusInfo.label.toLowerCase()}.</p>
            </div>
          ) : kasus?.status !== 'diputuskan' ? (
            <div className="border-t border-slate-100 p-2.5 space-y-2 bg-slate-50/50">
              <div className="flex flex-wrap gap-1.5">
                {[
                  'Carikan jadwal pengganti',
                  'Geser minggu depan pagi',
                  'Kenapa reservasi digusur?',
                  'Hubungi Bu Eci',
                ].map((chip) => (
                  <button
                    key={chip}
                    type="button"
                    disabled={mutasiKirim.isPending}
                    onClick={() => mutasiKirim.mutate(chip)}
                    className="text-[11px] bg-white hover:bg-indigo-50 text-slate-600 hover:text-indigo-700 border border-slate-200 px-2.5 py-1 rounded-full transition"
                  >
                    {chip}
                  </button>
                ))}
              </div>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={input}
                  onChange={e => setInput(e.target.value.slice(0, 1000))}
                  onKeyDown={e => e.key === 'Enter' && !e.shiftKey && handleKirim()}
                  placeholder="Tanya jadwal, kapasitas, atau minta opsi lain..."
                  disabled={mutasiKirim.isPending}
                  className="flex-1 bg-white border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                />
                <button
                  onClick={handleKirim}
                  disabled={!input.trim() || mutasiKirim.isPending}
                  className="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white px-4 py-1.5 rounded-lg text-sm font-semibold transition"
                >
                  {mutasiKirim.isPending ? '…' : 'Kirim'}
                </button>
              </div>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
