import React, { useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';
import TombolChatAdmin from '../../components/TombolChatAdmin.jsx';

const statusColor = {
  disetujui: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  menunggu: 'bg-amber-100 text-amber-800 border-amber-200',
  ditolak: 'bg-rose-100 text-rose-800 border-rose-200',
  dibatalkan: 'bg-slate-100 text-slate-600 border-slate-200',
  tergusur: 'bg-red-100 text-red-800 border-red-300 font-bold',
};

const fetchDetailReservasi = async (id) => {
  const res = await api.get(`/reservasi/${id}`);
  return res.data?.reservasi || res.data?.data || res.data;
};

const formatDateTime = (isoStr) => {
  if (!isoStr) return '-';
  try {
    const d = new Date(isoStr);
    const dateFormatted = d.toLocaleDateString('id-ID', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
    const hours = d.getHours();
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const period = hours >= 12 ? 'PM' : 'AM';
    const h12 = hours % 12 === 0 ? 12 : hours % 12;
    const padH12 = String(h12).padStart(2, '0');
    const ket = hours >= 5 && hours < 12 ? 'Pagi' : hours >= 12 && hours < 15 ? 'Siang' : hours >= 15 && hours < 18 ? 'Sore' : 'Malam';

    return `${dateFormatted} • ${padH12}:${minutes} ${period} (${ket})`;
  } catch {
    return isoStr;
  }
};

const DetailReservasi = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [batalLoading, setBatalLoading] = useState(false);
  const [pesanBatal, setPesanBatal] = useState('');
  const [downloading, setDownloading] = useState(false);

  const { data: reservasi, isLoading, isError, refetch } = useQuery({
    queryKey: ['detail_reservasi', id],
    queryFn: () => fetchDetailReservasi(id),
  });

  const handleBatalkan = async () => {
    if (!window.confirm('Apakah Anda yakin ingin membatalkan permohonan reservasi ini?')) return;
    setBatalLoading(true);
    try {
      await api.post(`/reservasi/${id}/batal`);
      setPesanBatal('Reservasi berhasil dibatalkan.');
      refetch();
    } catch (err) {
      alert(err.response?.data?.galat || 'Gagal membatalkan reservasi.');
    } finally {
      setBatalLoading(false);
    }
  };

  const handleUnduhSurat = async () => {
    const suratId = reservasi?.surat?.id || reservasi?.surat_id || id;
    setDownloading(true);
    try {
      const res = await api.get(`/surat/${suratId}/unduh`, { responseType: 'blob' });
      const blob = new Blob([res.data], { type: 'application/pdf' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `surat_${reservasi?.kode || id}.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    } catch (err) {
      alert(err.response?.data?.galat || 'Gagal mengunduh berkas surat.');
    } finally {
      setDownloading(false);
    }
  };

  const handleUnduhMemo = async () => {
    const memoId = reservasi?.memo_id || reservasi?.memo?.id || id;
    setDownloading(true);
    try {
      const res = await api.get(`/memo/${memoId}/unduh`, { responseType: 'blob' });
      const blob = new Blob([res.data], { type: 'application/pdf' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `memo_${reservasi?.kode || id}.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    } catch (err) {
      alert(err.response?.data?.galat || 'Gagal mengunduh memo persetujuan.');
    } finally {
      setDownloading(false);
    }
  };

  if (isLoading) {
    return (
      <div className="py-24 text-center">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent mb-3"></div>
        <p className="text-xs text-slate-500">Memuat detail reservasi #{id}...</p>
      </div>
    );
  }

  if (isError || !reservasi) {
    return (
      <div className="max-w-2xl mx-auto p-8 text-center">
        <p className="text-rose-600 text-sm font-semibold mb-3">Reservasi tidak ditemukan atau telah dihapus.</p>
        <Link to="/app/reservasi" className="text-xs text-blue-600 hover:underline">← Kembali ke Riwayat</Link>
      </div>
    );
  }

  const isDisetujui = reservasi.status?.toLowerCase() === 'disetujui';
  const isMenunggu = reservasi.status?.toLowerCase() === 'menunggu';
  const isTergusur = reservasi.status?.toLowerCase() === 'tergusur' || reservasi.kasus_penggusuran;
  const isGedung = reservasi.lokasi?.jenis === 'gedung';

  return (
    <div className="max-w-2xl mx-auto p-6 space-y-6">
      <Link to="/app/reservasi" className="text-slate-500 hover:text-slate-800 text-xs font-medium">
        ← Kembali ke Riwayat Reservasi
      </Link>

      {/* Banner Kasus Penggusuran jika jadwal bentrok dengan acara kampus */}
      {isTergusur && (
        <div className="p-4 bg-red-50 border border-red-300 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
          <div>
            <p className="text-xs font-bold text-red-800 flex items-center gap-1.5">
              <span>⚠️</span> Reservasi Ini Mengalami Konflik / Tergusur
            </p>
            <p className="text-[11px] text-red-600 mt-0.5">
              Acara kampus telah dijadwalkan pada waktu yang sama. Agen AI siap membantu Anda mencari jadwal pengganti.
            </p>
          </div>
          <Link
            to={`/app/negosiasi/${reservasi.kasus_penggusuran?.id || 1}`}
            className="px-3.5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold whitespace-nowrap transition"
          >
            Buka Ruang Negosiasi AI →
          </Link>
        </div>
      )}

      {pesanBatal && (
        <div className="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs font-semibold">
          {pesanBatal}
        </div>
      )}

      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-5">
        <div className="flex items-start justify-between border-b border-slate-100 pb-4">
          <div>
            <span className={`inline-block text-[11px] font-semibold uppercase px-2 py-0.5 rounded-full mb-1 ${
              isGedung ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'
            }`}>
              {isGedung ? '🏛️ Gedung' : '🚪 Ruangan'}
            </span>
            <h1 className="text-xl font-bold text-slate-800">{reservasi.lokasi?.nama || '-'}</h1>
            <p className="text-xs text-slate-400 font-mono mt-0.5">Kode Booking: {reservasi.kode || id}</p>
          </div>
          <span className={`px-3 py-1 rounded-full text-xs font-bold border capitalize ${
            statusColor[reservasi.status?.toLowerCase()] || 'bg-slate-100 text-slate-700'
          }`}>
            {reservasi.status}
          </span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <div>
            <span className="text-slate-400 block mb-0.5">Waktu Mulai:</span>
            <p className="font-semibold text-slate-800">{formatDateTime(reservasi.mulai)}</p>
          </div>
          <div>
            <span className="text-slate-400 block mb-0.5">Waktu Selesai:</span>
            <p className="font-semibold text-slate-800">{formatDateTime(reservasi.selesai)}</p>
          </div>
          <div>
            <span className="text-slate-400 block mb-0.5">Kapasitas Lokasi:</span>
            <p className="font-semibold text-slate-800">
              {reservasi.lokasi?.kapasitas ? `${reservasi.lokasi.kapasitas} orang` : '-'}
            </p>
          </div>
          <div>
            <span className="text-slate-400 block mb-0.5">Estimasi Peserta:</span>
            <p className="font-semibold text-slate-800">
              {reservasi.jumlah_peserta ? `${reservasi.jumlah_peserta} orang` : '-'}
            </p>
          </div>
          <div className="sm:col-span-2">
            <span className="text-slate-400 block mb-0.5">Keperluan &amp; Tujuan Kegiatan:</span>
            <p className="font-medium text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-200/60 leading-relaxed">
              {reservasi.keperluan || '-'}
            </p>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="pt-4 border-t border-slate-100 flex flex-wrap items-center gap-2.5">
          {isDisetujui && (
            <button
              onClick={handleUnduhMemo}
              disabled={downloading}
              className="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5"
            >
              <span>📄</span> Unduh Memo Persetujuan (PDF)
            </button>
          )}

          {(reservasi.has_surat || reservasi.surat) && (
            <button
              onClick={handleUnduhSurat}
              disabled={downloading}
              className="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold border border-slate-200/80 transition flex items-center gap-1.5"
            >
              <span>👁️</span> Lihat Surat Permohonan Saya (PDF)
            </button>
          )}

          {/* Hubungi Pengelola Gedung (Bu Eci) */}
          <TombolChatAdmin reservasiId={reservasi.id} kasusId={reservasi.kasus_penggusuran?.id} />

          {(isMenunggu || isDisetujui) && (
            <button
              onClick={handleBatalkan}
              disabled={batalLoading}
              className="px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-xs font-semibold transition"
            >
              {batalLoading ? 'Membatalkan...' : 'Batalkan Peminjaman'}
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default DetailReservasi;
