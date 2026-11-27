import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

export default function UploadJadwal() {
  const [file, setFile] = useState(null);
  const [scanning, setScanning] = useState(false);
  const [activating, setActivating] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [scanResult, setScanResult] = useState(null);
  const [successInfo, setSuccessInfo] = useState(null);

  // Ambil daftar ruangan untuk contoh template & referensi opsional
  const { data: rooms = [] } = useQuery({
    queryKey: ['lokasi_rooms'],
    queryFn: async () => {
      const res = await api.get('/lokasi');
      const all = res.data?.data || res.data || [];
      return all.filter((l) => l.jenis === 'ruangan' && l.aktif);
    },
  });

  const handleDownloadContoh = () => {
    const r1 = rooms[0]?.nama || 'Lab Komputer 1';
    const r2 = rooms[1]?.nama || r1;

    const csvContent = [
      'mata_kuliah;dosen;prodi;tanggal;jam;ruangan',
      `Algoritma dan Pemrograman;Dr. Ir. Hendra M.T.;Teknik Informatika;2026-11-30;08:00-10:00;${r1}`,
      `Basis Data Lanjut;Siti Nurhaliza M.Kom.;Sistem Informasi;2026-12-01;10:30-12:30;${r1}`,
      `Pemrograman Web;Budi Santoso M.Kom.;Teknik Informatika;2026-12-02;13:00-15:00;${r2}`,
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', 'contoh_jadwal_kuliah_simpel.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleScan = async (e) => {
    e.preventDefault();
    if (!file) {
      setErrorMessage('Pilih berkas Excel (.xlsx) atau CSV jadwal kuliah terlebih dahulu.');
      return;
    }

    setErrorMessage('');
    setSuccessInfo(null);
    setScanResult(null);
    setScanning(true);

    try {
      const formData = new FormData();
      formData.append('file', file);

      const res = await api.post('/akademik/scan', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setScanResult(res.data);
    } catch (err) {
      setErrorMessage(err.response?.data?.galat || 'Gagal memindai berkas jadwal kuliah.');
    } finally {
      setScanning(false);
    }
  };

  const handleAktivasi = async () => {
    if (!scanResult?.baris?.length) return;
    const siapItems = scanResult.baris.filter((b) => b.status_scan === 'siap');
    if (siapItems.length === 0) {
      setErrorMessage('Tidak ada baris valid yang siap diintegrasikan.');
      return;
    }

    setErrorMessage('');
    setActivating(true);

    try {
      const res = await api.post('/akademik/aktivasi-scan', { items: siapItems });
      setSuccessInfo(res.data);
      setScanResult(null);
      setFile(null);
    } catch (err) {
      setErrorMessage(err.response?.data?.galat || 'Gagal mengaktifkan jadwal hasil scan.');
    } finally {
      setActivating(false);
    }
  };

  const siapCount = scanResult?.baris?.filter((b) => b.status_scan === 'siap').length || 0;

  return (
    <div className="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
      <div className="max-w-5xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div className="flex items-center gap-2 text-xs text-slate-500 mb-1">
              <Link to="/akademik" className="hover:text-teal-600">Portal Akademik</Link>
              <span>/</span>
              <span className="text-slate-800 font-semibold">Input Jadwal Kuliah (AI Scanner)</span>
            </div>
            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
              Input Jadwal Kuliah
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
              Cukup unggah file Excel (.xlsx) atau CSV. AI Agent &amp; Sistem otomatis memindai dosen, prodi, tanggal/hari, jam, dan ruangan lalu mengintegrasikannya ke Kalender Ruangan.
            </p>
          </div>
          <button
            type="button"
            onClick={handleDownloadContoh}
            className="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200/80 rounded-xl text-xs font-semibold transition self-start sm:self-auto"
          >
            📥 Unduh Contoh Format
          </button>
        </div>

        {/* Success Alert */}
        {successInfo && (
          <div className="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 text-emerald-900 shadow-sm">
            <div className="flex items-start gap-3">
              <span className="text-2xl">🎉</span>
              <div className="flex-1">
                <h3 className="text-sm font-bold">
                  {successInfo.jumlah_tersimpan} Jadwal Kuliah Berhasil Diaktifkan &amp; Terintegrasi!
                </h3>
                <p className="text-xs text-emerald-800 mt-1">
                  Jadwal kuliah sudah otomatis masuk ke Kalender Ruangan dan memblokir pengajuan peminjaman yang bentrok.
                </p>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                  <Link
                    to="/akademik/jadwal"
                    className="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition"
                  >
                    Lihat Jadwal Kuliah Aktif →
                  </Link>
                  <Link
                    to="/jadwal"
                    className="px-3.5 py-1.5 bg-white border border-emerald-300 text-emerald-700 rounded-lg text-xs font-semibold hover:bg-emerald-50 transition"
                  >
                    Buka Kalender Ruangan
                  </Link>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Error Alert */}
        {errorMessage && (
          <div className="bg-rose-50 border border-rose-200 rounded-2xl p-4 text-rose-900 text-xs sm:text-sm font-medium flex items-center gap-2">
            <span>⚠️</span>
            <span>{errorMessage}</span>
          </div>
        )}

        {/* Single Upload Input Card */}
        <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
          <form onSubmit={handleScan} className="space-y-4">
            <div className="border-2 border-dashed border-slate-300 hover:border-teal-500 rounded-2xl p-6 text-center transition bg-slate-50/50">
              <input
                type="file"
                id="jadwalFileInput"
                accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                onChange={(e) => {
                  setFile(e.target.files?.[0] || null);
                  setScanResult(null);
                  setErrorMessage('');
                }}
                className="hidden"
              />
              <label
                htmlFor="jadwalFileInput"
                className="cursor-pointer flex flex-col items-center justify-center space-y-2"
              >
                <div className="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-2xl shadow-sm">
                  🤖
                </div>
                {file ? (
                  <div>
                    <p className="text-sm font-bold text-slate-800">{file.name}</p>
                    <p className="text-xs text-slate-400">
                      {(file.size / 1024).toFixed(1)} KB • Klik untuk ganti berkas
                    </p>
                  </div>
                ) : (
                  <div>
                    <p className="text-sm font-semibold text-slate-700">
                      Pilih File Excel (.xlsx) atau CSV Jadwal Kuliah
                    </p>
                    <p className="text-xs text-slate-400 mt-0.5">
                      Tidak perlu isi periode, prodi, atau UUID ruangan — AI akan memindai otomatis dari isi file.
                    </p>
                  </div>
                )}
              </label>
            </div>

            <div className="flex items-center justify-end">
              <button
                type="submit"
                disabled={scanning || !file}
                className="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-xs sm:text-sm font-bold rounded-xl shadow transition flex items-center gap-2"
              >
                {scanning ? (
                  <>
                    <span className="animate-spin">⏳</span>
                    <span>AI Sedang Memindai Berkas...</span>
                  </>
                ) : (
                  <span>🤖 Pindai Jadwal dengan AI</span>
                )}
              </button>
            </div>
          </form>
        </div>

        {/* Hasil Pemindaian AI */}
        {scanResult && (
          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div className="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h2 className="text-sm font-bold text-slate-800">
                  Hasil Pemindaian AI ({scanResult.baris?.length || 0} Baris)
                </h2>
                {scanResult.periode && (
                  <p className="text-xs text-slate-500">
                    Periode Terdeteksi: <strong>{scanResult.periode.tahun_ajaran} ({scanResult.periode.semester})</strong>
                  </p>
                )}
              </div>
              <button
                type="button"
                onClick={handleAktivasi}
                disabled={activating || siapCount === 0}
                className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow transition"
              >
                {activating ? 'Mengintegrasikan...' : `✅ Integrasikan ${siapCount} Jadwal ke Ruangan`}
              </button>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-100">
                  <tr>
                    <th className="py-2.5 px-4">Mata Kuliah &amp; Dosen</th>
                    <th className="py-2.5 px-4">Prodi</th>
                    <th className="py-2.5 px-4">Hari / Tanggal &amp; Jam</th>
                    <th className="py-2.5 px-4">Ruangan</th>
                    <th className="py-2.5 px-4">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {scanResult.baris.map((b, idx) => (
                    <tr key={idx} className="hover:bg-slate-50/70">
                      <td className="py-2.5 px-4">
                        <p className="font-bold text-slate-800">{b.nama_mata_kuliah || '-'}</p>
                        <p className="text-[11px] text-slate-500">👤 {b.nama_dosen} • Kelas {b.kelas}</p>
                      </td>
                      <td className="py-2.5 px-4 text-slate-700 font-medium">{b.program_studi}</td>
                      <td className="py-2.5 px-4 text-slate-700">
                        <span className="capitalize font-semibold">{b.hari || '-'}</span>
                        {b.tanggal ? <span className="text-slate-400"> ({b.tanggal})</span> : null}
                        <p className="text-[11px] text-slate-500">{b.jam_mulai} - {b.jam_selesai} WIB</p>
                      </td>
                      <td className="py-2.5 px-4 font-semibold text-slate-800">🚪 {b.lokasi_nama}</td>
                      <td className="py-2.5 px-4">
                        {b.status_scan === 'siap' ? (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
                            ✓ Siap Integrasi
                          </span>
                        ) : (
                          <div className="space-y-0.5">
                            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">
                              ✕ {b.status_scan === 'bentrok' ? 'Bentrok' : 'Perlu Perbaikan'}
                            </span>
                            {Array.isArray(b.galat) && b.galat.length > 0 && (
                              <p className="text-[10px] text-rose-600">{b.galat.join(', ')}</p>
                            )}
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
