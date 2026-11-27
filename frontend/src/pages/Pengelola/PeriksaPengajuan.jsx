import React, { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

const fetchDetailPengajuan = async (id) => {
  const res = await api.get(`/pengelola/reservasi/${id}`);
  return res.data?.pengajuan || res.data?.data || res.data;
};

const PeriksaPengajuan = () => {
  const { id } = useParams();
  const navigate = useNavigate();

  const [aksi, setAksi] = useState(null); // 'setuju' | 'tolak'
  const [alasan, setAlasan] = useState('');
  const [loadingAksi, setLoadingAksi] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [suksesMsg, setSuksesMsg] = useState('');

  // PDF Preview State
  const [pdfUrl, setPdfUrl] = useState(null);
  const [loadingSurat, setLoadingSurat] = useState(false);

  const { data: reservasi, isLoading, isError } = useQuery({
    queryKey: ['pengelola_detail_reservasi', id],
    queryFn: () => fetchDetailPengajuan(id),
  });

  // Cleanup object URL on unmount
  useEffect(() => {
    return () => {
      if (pdfUrl) {
        URL.revokeObjectURL(pdfUrl);
      }
    };
  }, [pdfUrl]);

  const pemohon = reservasi?.pemohon || reservasi?.pengguna || {};
  const gedung = reservasi?.gedung || reservasi?.lokasi || {};
  const surat = reservasi?.surat || null;
  const suratId = surat?.id || reservasi?.surat_id;

  const handleBukaSurat = async () => {
    if (!suratId) {
      setErrorMsg('Dokumen surat tidak ditemukan pada pengajuan ini.');
      return;
    }

    setLoadingSurat(true);
    setErrorMsg('');

    try {
      const res = await api.get(`/surat/${suratId}/unduh`, {
        responseType: 'blob',
      });
      const blob = new Blob([res.data], { type: 'application/pdf' });
      const url = URL.createObjectURL(blob);
      setPdfUrl(url);
    } catch (err) {
      const msg =
        err.response?.data?.galat ||
        err.response?.data?.pesan ||
        err.message ||
        'Gagal memuat berkas surat dari server.';
      setErrorMsg(msg);
    } finally {
      setLoadingSurat(false);
    }
  };

  const handleKirimKeputusan = async () => {
    if (aksi === 'tolak' && !alasan.trim()) {
      setErrorMsg('Alasan penolakan wajib diisi untuk memberi penjelasan kepada pemohon.');
      return;
    }

    setLoadingAksi(true);
    setErrorMsg('');

    try {
      if (aksi === 'setuju') {
        const res = await api.post(`/pengelola/reservasi/${id}/setuju`);
        setSuksesMsg(res.data?.pesan || 'Permohonan peminjaman berhasil disetujui. Memo persetujuan resmi telah diterbitkan.');
      } else if (aksi === 'tolak') {
        const res = await api.post(`/pengelola/reservasi/${id}/tolak`, { alasan });
        setSuksesMsg(res.data?.pesan || 'Permohonan peminjaman telah ditolak beserta alasan resmi.');
      }
    } catch (err) {
      const msg = err.response?.data?.galat || err.response?.data?.pesan || err.message || 'Gagal memproses keputusan.';
      setErrorMsg(msg);
    } finally {
      setLoadingAksi(false);
    }
  };

  if (isLoading) {
    return (
      <div className="py-24 text-center">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent mb-3"></div>
        <p className="text-xs text-slate-500">Memuat berkas permohonan #{id}...</p>
      </div>
    );
  }

  if (isError || !reservasi) {
    return (
      <div className="max-w-2xl mx-auto p-8 text-center">
        <p className="text-rose-600 text-sm font-semibold mb-3">
          Permohonan tidak ditemukan atau Anda tidak memiliki akses ke gedung ini.
        </p>
        <Link to="/pengelola/pengajuan" className="text-xs text-blue-600 hover:underline">
          ← Kembali ke Antrean
        </Link>
      </div>
    );
  }

  if (suksesMsg) {
    return (
      <div className="max-w-lg mx-auto mt-16 p-8 bg-white rounded-2xl shadow-sm border border-slate-200 text-center">
        <div className="text-5xl mb-4">{aksi === 'setuju' ? '✅' : '❌'}</div>
        <h2 className="text-2xl font-bold text-slate-800 mb-2">Keputusan Berhasil Disimpan</h2>
        <p className="text-sm text-slate-600 mb-6">{suksesMsg}</p>
        <Link
          to="/pengelola/pengajuan"
          className="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm"
        >
          ← Kembali ke Daftar Antrean
        </Link>
      </div>
    );
  }

  const isMenunggu = reservasi.status?.toLowerCase() === 'menunggu';

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <Link to="/pengelola/pengajuan" className="text-slate-500 hover:text-slate-800 text-xs font-medium">
        ← Kembali ke Daftar Pengajuan
      </Link>

      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Pemeriksaan Berkas Permohonan</h1>
          <p className="text-xs text-slate-500 font-mono mt-0.5">Kode: {reservasi.kode || id}</p>
        </div>
        <span
          className={`px-3 py-1 rounded-full text-xs font-bold border capitalize w-fit ${
            reservasi.status === 'disetujui'
              ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
              : reservasi.status === 'ditolak'
              ? 'bg-rose-100 text-rose-800 border-rose-200'
              : 'bg-amber-100 text-amber-800 border-amber-200'
          }`}
        >
          Status: {reservasi.status}
        </span>
      </div>

      {errorMsg && (
        <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-medium">
          ⚠️ {errorMsg}
        </div>
      )}

      {/* Rincian Permohonan */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
        <h2 className="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-2">
          Rincian Permohonan Peminjaman
        </h2>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <div>
            <span className="text-slate-400 block mb-0.5">Nama Pemohon:</span>
            <p className="font-semibold text-slate-800 text-sm">{pemohon.nama || '-'}</p>
            <p className="text-slate-500">{pemohon.email} | {pemohon.no_hp || '-'}</p>
          </div>
          <div>
            <span className="text-slate-400 block mb-0.5">Organisasi / Lembaga:</span>
            <p className="font-semibold text-slate-800 text-sm">{pemohon.organisasi || '-'}</p>
          </div>
          <div>
            <span className="text-slate-400 block mb-0.5">Lokasi yang Diajukan:</span>
            <p className="font-semibold text-blue-700 text-sm">🏛️ {gedung.nama || '-'}</p>
            <p className="text-slate-500">Kapasitas: {gedung.kapasitas} orang</p>
          </div>
          <div>
            <span className="text-slate-400 block mb-0.5">Estimasi Peserta:</span>
            <p className="font-semibold text-slate-800 text-sm">
              {reservasi.jumlah_peserta ? `${reservasi.jumlah_peserta} orang` : 'Tidak disebutkan'}
            </p>
          </div>
          <div className="sm:col-span-2">
            <span className="text-slate-400 block mb-0.5">Waktu &amp; Tanggal Acara:</span>
            <p className="font-semibold text-slate-800 bg-slate-50 p-2.5 rounded-lg border border-slate-200/60 font-mono text-xs">
              📅 {reservasi.mulai} <span className="text-slate-400 font-normal">sampai</span> {reservasi.selesai}
            </p>
          </div>
          <div className="sm:col-span-2">
            <span className="text-slate-400 block mb-0.5">Tujuan &amp; Keperluan Acara:</span>
            <p className="text-slate-700 leading-relaxed bg-slate-50 p-3 rounded-lg border border-slate-200/60">
              {reservasi.keperluan}
            </p>
          </div>
        </div>
      </div>

      {/* Dokumen Surat PDF Card */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl font-bold shadow-sm">
            📄
          </div>
          <div>
            <h3 className="text-xs sm:text-sm font-bold text-slate-800">
              {surat?.nama_asli ? `Berkas Surat: ${surat.nama_asli}` : 'Berkas Surat Permohonan Resmi (PDF)'}
            </h3>
            <p className="text-[11px] text-slate-400 mt-0.5">
              {surat?.ukuran_byte ? `${(surat.ukuran_byte / 1024).toFixed(1)} KB • ` : ''}Diunggah oleh pemohon saat pengajuan
            </p>
          </div>
        </div>

        {suratId ? (
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handleBukaSurat}
              disabled={loadingSurat}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-2"
            >
              {loadingSurat ? (
                <>
                  <span className="animate-spin">⏳</span>
                  <span>Mengambil Dokumen...</span>
                </>
              ) : (
                <>
                  <span>👁️</span>
                  <span>{pdfUrl ? 'Muat Ulang Surat' : 'Buka & Periksa Surat'}</span>
                </>
              )}
            </button>
          </div>
        ) : (
          <span className="text-xs text-slate-400 italic">Tanpa lampiran surat</span>
        )}
      </div>

      {/* Pratinjau Surat PDF Interaktif (Inline Viewer) */}
      {pdfUrl && (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 space-y-3">
          <div className="flex items-center justify-between border-b border-slate-100 pb-3">
            <div className="flex items-center gap-2">
              <span className="text-lg">📄</span>
              <h3 className="text-xs sm:text-sm font-bold text-slate-800">
                Pratinjau Surat: {surat?.nama_asli || 'surat_permohonan.pdf'}
              </h3>
            </div>
            <div className="flex items-center gap-2">
              <a
                href={pdfUrl}
                target="_blank"
                rel="noreferrer"
                className="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold transition"
              >
                Buka di Tab Baru ↗
              </a>
              <button
                type="button"
                onClick={() => setPdfUrl(null)}
                className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-semibold transition"
              >
                Tutup Pratinjau ✕
              </button>
            </div>
          </div>

          <div className="rounded-xl overflow-hidden border border-slate-200 bg-slate-900">
            <iframe
              src={pdfUrl}
              className="w-full h-[650px] border-none"
              title="Pratinjau Surat Permohonan Resmi"
            />
          </div>
        </div>
      )}

      {/* Tombol Tindakan Keputusan Pengelola (Hanya jika status 'menunggu') */}
      {isMenunggu ? (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
          <h2 className="text-xs font-bold text-slate-800 uppercase tracking-wide">
            Tindakan Keputusan Pengelola Gedung
          </h2>

          <div className="flex gap-3">
            <button
              onClick={() => { setAksi('setuju'); setErrorMsg(''); }}
              className={`flex-1 py-3 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 ${
                aksi === 'setuju'
                  ? 'bg-emerald-600 text-white shadow'
                  : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200'
              }`}
            >
              <span>✅</span> Setujui Permohonan (Terbitkan Memo)
            </button>
            <button
              onClick={() => { setAksi('tolak'); setErrorMsg(''); }}
              className={`flex-1 py-3 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 ${
                aksi === 'tolak'
                  ? 'bg-rose-600 text-white shadow'
                  : 'bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200'
              }`}
            >
              <span>❌</span> Tolak Permohonan
            </button>
          </div>

          {/* Kotak Alasan Penolakan */}
          {aksi === 'tolak' && (
            <div className="p-4 bg-rose-50/70 border border-rose-200 rounded-xl space-y-2">
              <label className="block text-xs font-bold text-rose-900">
                Alasan Penolakan Resmi (Wajib ditulis apa adanya) <span className="text-rose-500">*</span>
              </label>
              <textarea
                value={alasan}
                onChange={(e) => setAlasan(e.target.value)}
                rows={3}
                placeholder="Contoh: Gedung sedang dipersiapkan untuk kegiatan Dies Natalis Kampus..."
                className="w-full border border-rose-300 rounded-lg p-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-400 bg-white"
                required
              />
              <p className="text-[11px] text-rose-700">
                Alasan ini akan ditampilkan persis kepada pemohon tanpa diubah.
              </p>
            </div>
          )}

          {aksi && (
            <button
              onClick={handleKirimKeputusan}
              disabled={loadingAksi || (aksi === 'tolak' && !alasan.trim())}
              className={`w-full py-3 rounded-xl text-xs font-bold text-white transition shadow-sm ${
                aksi === 'setuju' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'
              } disabled:opacity-50`}
            >
              {loadingAksi ? 'Memproses Keputusan...' : `Konfirmasi Keputusan: ${aksi.toUpperCase()}`}
            </button>
          )}
        </div>
      ) : (
        <div className="p-4 bg-slate-100 rounded-xl text-xs text-slate-500 text-center">
          Permohonan ini telah berstatus <strong>{reservasi.status}</strong> dan tidak dapat diubah lagi dari antrean ini.
        </div>
      )}
    </div>
  );
};

export default PeriksaPengajuan;
