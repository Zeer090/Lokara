import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

// Pre-seeded departments and study programs in database
const DEPARTMENTS = [
  { id: 'd9e8f353-562c-48c9-ad30-6c87de71cd9d', nama: 'Fakultas Ilmu Komputer' }
];

const STUDY_PROGRAMS = [
  { id: '98416190-e88f-466d-92ef-33fdfdf7b543', department_id: 'd9e8f353-562c-48c9-ad30-6c87de71cd9d', nama: 'Teknik Informatika' },
  { id: '272bcbbc-d254-4e3e-87b8-298c15325454', department_id: 'd9e8f353-562c-48c9-ad30-6c87de71cd9d', nama: 'Sistem Informasi' }
];

export default function UploadJadwal() {
  const [periodId, setPeriodId] = useState('');
  const [departmentId, setDepartmentId] = useState(DEPARTMENTS[0].id);
  const [programId, setProgramId] = useState(STUDY_PROGRAMS[0].id);
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [errorDetails, setErrorDetails] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');
  const [successInfo, setSuccessInfo] = useState(null);
  const [copiedId, setCopiedId] = useState('');
  const [showRoomReference, setShowRoomReference] = useState(false);

  // Fetch academic periods
  const { data: periods = [] } = useQuery({
    queryKey: ['akademik_periods'],
    queryFn: async () => {
      const res = await api.get('/akademik/periods');
      const list = res.data || [];
      if (list.length > 0 && !periodId) {
        setPeriodId(list[0].id);
      }
      return list;
    },
  });

  // Fetch rooms for helper reference
  const { data: rooms = [] } = useQuery({
    queryKey: ['lokasi_rooms'],
    queryFn: async () => {
      const res = await api.get('/lokasi');
      const all = res.data?.data || res.data || [];
      return all.filter((l) => l.jenis === 'ruangan' && l.aktif);
    },
  });

  const selectedProgram = STUDY_PROGRAMS.find((p) => p.id === programId);

  const handleDownloadTemplate = () => {
    const defaultRoomId = rooms[0]?.id || '01a1e9de-2962-7306-8808-4455fd5dd795';
    const secondRoomId = rooms[1]?.id || defaultRoomId;
    const progName = selectedProgram?.nama || 'Teknik Informatika';

    const csvContent = [
      'nama_mata_kuliah,kode_mk,kelas,program_studi,nama_dosen,hari,jam_mulai,jam_selesai,lokasi_id',
      `Algoritma dan Pemrograman,IF101,A,${progName},Dr. Ir. Hendra M.T.,senin,08:00,10:00,${defaultRoomId}`,
      `Basis Data Lanjut,IF201,B,${progName},Siti Nurhaliza M.Kom.,senin,10:30,12:30,${defaultRoomId}`,
      `Pemrograman Web,IF301,A,${progName},Budi Santoso M.Kom.,rabu,13:00,15:30,${secondRoomId}`,
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `template_jadwal_${progName.toLowerCase().replace(/\s+/g, '_')}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleCopyId = (id) => {
    navigator.clipboard.writeText(id);
    setCopiedId(id);
    setTimeout(() => setCopiedId(''), 2000);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setErrorDetails(null);
    setSuccessInfo(null);

    if (!file) {
      setErrorMessage('Silakan pilih berkas CSV jadwal terlebih dahulu.');
      return;
    }
    if (!periodId) {
      setErrorMessage('Silakan pilih periode akademik yang dituju.');
      return;
    }

    setLoading(true);

    try {
      const formData = new FormData();
      formData.append('academic_period_id', periodId);
      formData.append('department_id', departmentId);
      formData.append('study_program_id', programId);
      formData.append('file', file);

      const res = await api.post('/akademik/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setSuccessInfo(res.data);
      setFile(null);
    } catch (err) {
      const galat = err.response?.data?.galat || 'Gagal memproses berkas jadwal.';
      const details = err.response?.data?.details || null;
      setErrorMessage(galat);
      setErrorDetails(details);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
      <div className="max-w-4xl mx-auto space-y-6">
        {/* Header Breadcrumb */}
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-2 text-xs text-slate-500 mb-1">
              <Link to="/akademik" className="hover:text-teal-600">Portal Akademik</Link>
              <span>/</span>
              <span className="text-slate-800 font-semibold">Upload Datasheet Jadwal</span>
            </div>
            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
              Unggah Datasheet Jadwal Perkuliahan
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
              Jadwal yang diunggah akan otomatis menempati slot ruangan perkuliahan dan memblokir reservasi yang berbentrok.
            </p>
          </div>
          <button
            type="button"
            onClick={handleDownloadTemplate}
            className="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200/80 rounded-xl text-xs font-semibold transition flex items-center gap-1.5"
          >
            <span>📥 Unduh Template CSV</span>
          </button>
        </div>

        {/* Success Alert */}
        {successInfo && (
          <div className="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 text-emerald-900 shadow-sm">
            <div className="flex items-start gap-3">
              <span className="text-2xl">🎉</span>
              <div className="flex-1">
                <h3 className="text-sm font-bold">Datasheet Berhasil Diunggah & Diaktifkan!</h3>
                <p className="text-xs text-emerald-800 mt-1">
                  Semua sesi perkuliahan pada datasheet telah tersimpan di database. Ruangan-ruangan terkait
                  kini resmi terkunci dan terlindungi dari bentrok jadwal peminjaman umum.
                </p>
                <div className="mt-3 flex items-center gap-2">
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
          <div className="bg-rose-50 border border-rose-200 rounded-2xl p-5 text-rose-900 shadow-sm space-y-3">
            <div className="flex items-start gap-3">
              <span className="text-xl">⚠️</span>
              <div className="flex-1">
                <h3 className="text-sm font-bold">{errorMessage}</h3>
                <p className="text-xs text-rose-700 mt-0.5">
                  Periksa format CSV dan pastikan tidak ada jadwal perkuliahan yang saling tumpang tindih pada ruangan yang sama.
                </p>
              </div>
            </div>

            {/* Error Line Breakdown */}
            {errorDetails && (
              <div className="bg-white/80 rounded-xl p-3 border border-rose-200 text-xs font-mono max-h-60 overflow-y-auto space-y-1">
                {typeof errorDetails === 'object' ? (
                  Object.entries(errorDetails).map(([key, msgs]) => (
                    <div key={key} className="text-rose-800">
                      <strong>Baris {key}:</strong> {Array.isArray(msgs) ? msgs.join(', ') : String(msgs)}
                    </div>
                  ))
                ) : (
                  <div className="text-rose-800">{String(errorDetails)}</div>
                )}
              </div>
            )}
          </div>
        )}

        {/* Form Card */}
        <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8">
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              {/* Periode Akademik */}
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1.5">
                  1. Periode Akademik <span className="text-rose-500">*</span>
                </label>
                <select
                  value={periodId}
                  onChange={(e) => setPeriodId(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                  required
                >
                  {periods.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.tahun_ajaran} ({p.semester})
                    </option>
                  ))}
                </select>
              </div>

              {/* Fakultas / Department */}
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1.5">
                  2. Fakultas <span className="text-rose-500">*</span>
                </label>
                <select
                  value={departmentId}
                  onChange={(e) => setDepartmentId(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                  required
                >
                  {DEPARTMENTS.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.nama}
                    </option>
                  ))}
                </select>
              </div>

              {/* Program Studi */}
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1.5">
                  3. Program Studi <span className="text-rose-500">*</span>
                </label>
                <select
                  value={programId}
                  onChange={(e) => setProgramId(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                  required
                >
                  {STUDY_PROGRAMS.map((sp) => (
                    <option key={sp.id} value={sp.id}>
                      {sp.nama}
                    </option>
                  ))}
                </select>
                <span className="text-[10px] text-slate-400 mt-1 block">
                  Kolom `program_studi` di CSV harus bernilai "{selectedProgram?.nama}"
                </span>
              </div>
            </div>

            {/* File Dropzone */}
            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1.5">
                4. Berkas Datasheet (.CSV) <span className="text-rose-500">*</span>
              </label>
              <div className="border-2 border-dashed border-slate-300 hover:border-teal-500 rounded-2xl p-6 text-center transition bg-slate-50/50">
                <input
                  type="file"
                  id="csvFileInput"
                  accept=".csv,text/csv"
                  onChange={(e) => setFile(e.target.files?.[0] || null)}
                  className="hidden"
                />
                <label
                  htmlFor="csvFileInput"
                  className="cursor-pointer flex flex-col items-center justify-center space-y-2"
                >
                  <div className="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-2xl shadow-sm">
                    📊
                  </div>
                  {file ? (
                    <div>
                      <p className="text-sm font-bold text-slate-800">{file.name}</p>
                      <p className="text-xs text-slate-400">
                        {(file.size / 1024).toFixed(1)} KB • Klik untuk mengganti berkas
                      </p>
                    </div>
                  ) : (
                    <div>
                      <p className="text-sm font-semibold text-slate-700">
                        Pilih berkas CSV atau seret ke sini
                      </p>
                      <p className="text-xs text-slate-400 mt-0.5">
                        Format kolom standar: nama_mata_kuliah, kode_mk, kelas, program_studi, nama_dosen, hari, jam_mulai, jam_selesai, lokasi_id
                      </p>
                    </div>
                  )}
                </label>
              </div>
            </div>

            {/* Submit Button */}
            <div className="flex items-center justify-between pt-4 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setShowRoomReference(!showRoomReference)}
                className="text-xs font-semibold text-teal-700 hover:text-teal-800 flex items-center gap-1"
              >
                <span>{showRoomReference ? 'Sembunyikan' : 'Buka'} Referensi ID Ruangan</span>
                <span className="text-[10px] bg-teal-100 text-teal-800 px-1.5 py-0.5 rounded font-mono">
                  {rooms.length} Ruangan
                </span>
              </button>

              <button
                type="submit"
                disabled={loading || !file}
                className="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-xs sm:text-sm font-bold rounded-xl shadow transition flex items-center gap-2"
              >
                {loading ? (
                  <>
                    <span className="animate-spin">⏳</span>
                    <span>Memproses Datasheet...</span>
                  </>
                ) : (
                  <>
                    <span>📤 Proses & Kunci Jadwal</span>
                  </>
                )}
              </button>
            </div>
          </form>
        </div>

        {/* Room Reference ID Helper (Collapsible) */}
        {showRoomReference && (
          <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-bold text-slate-800">
                  Daftar ID Ruangan Perkuliahan (Lokasi ID)
                </h3>
                <p className="text-xs text-slate-500">
                  Salin UUID ruangan di bawah untuk dimasukkan ke kolom `lokasi_id` pada file CSV.
                </p>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 text-slate-600 uppercase tracking-wider font-semibold border-b border-slate-100">
                  <tr>
                    <th className="py-2.5 px-3">Nama Ruangan</th>
                    <th className="py-2.5 px-3">Kapasitas</th>
                    <th className="py-2.5 px-3">Lokasi ID (UUID)</th>
                    <th className="py-2.5 px-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 font-mono">
                  {rooms.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50">
                      <td className="py-2.5 px-3 font-sans font-medium text-slate-800">
                        🚪 {r.nama}
                      </td>
                      <td className="py-2.5 px-3 font-sans text-slate-600">
                        {r.kapasitas} orang
                      </td>
                      <td className="py-2.5 px-3 text-slate-500 text-[11px]">
                        {r.id}
                      </td>
                      <td className="py-2.5 px-3 text-right font-sans">
                        <button
                          type="button"
                          onClick={() => handleCopyId(r.id)}
                          className={`px-2.5 py-1 rounded text-[11px] font-semibold transition ${
                            copiedId === r.id
                              ? 'bg-emerald-600 text-white'
                              : 'bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-teal-700'
                          }`}
                        >
                          {copiedId === r.id ? 'Tersalin ✓' : 'Salin ID'}
                        </button>
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
