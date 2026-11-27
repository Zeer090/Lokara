import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

export default function AkademikDashboard() {
  const { data: periods = [], isLoading: loadingPeriods } = useQuery({
    queryKey: ['akademik_periods'],
    queryFn: async () => {
      const res = await api.get('/akademik/periods');
      return res.data || [];
    },
  });

  const { data: uploads = [], isLoading: loadingUploads } = useQuery({
    queryKey: ['akademik_uploads'],
    queryFn: async () => {
      const res = await api.get('/akademik/upload');
      return res.data || [];
    },
  });

  const activePeriod = periods[0] || null;

  const { data: activeSchedules = [], isLoading: loadingSchedules } = useQuery({
    queryKey: ['akademik_schedules', activePeriod?.id],
    enabled: !!activePeriod?.id,
    queryFn: async () => {
      const res = await api.get(`/akademik/schedule/${activePeriod.id}`);
      return res.data || [];
    },
  });

  const formatDate = (isoStr) => {
    if (!isoStr) return '-';
    try {
      return new Date(isoStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    } catch {
      return isoStr;
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto space-y-6">
        {/* Banner Role Header */}
        <div className="bg-gradient-to-r from-teal-700 via-teal-800 to-emerald-900 rounded-3xl p-6 sm:p-8 text-white shadow-md relative overflow-hidden">
          <div className="relative z-10 max-w-3xl">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-teal-600/60 border border-teal-400/30 text-teal-100 mb-3 backdrop-blur-sm">
              <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              Portal Biro Administrasi Akademik (BAAK)
            </span>
            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight mb-2">
              Penjadwalan Kuliah & Alokasi Ruangan
            </h1>
            <p className="text-sm text-teal-100/90 leading-relaxed mb-6">
              Unggah datasheet jadwal perkuliahan semester aktif. Seluruh ruangan yang dialokasikan
              untuk mata kuliah otomatis terkunci di sistem SMARTSPACE AI dan mencegah bentrok peminjaman mahasiswa.
            </p>

            <div className="flex flex-wrap items-center gap-3">
              <Link
                to="/akademik/upload"
                className="px-5 py-2.5 bg-white text-teal-900 hover:bg-teal-50 rounded-xl font-bold text-xs sm:text-sm shadow transition flex items-center gap-2"
              >
                <span>📤 Upload Datasheet Jadwal</span>
              </Link>
              <Link
                to="/akademik/jadwal"
                className="px-5 py-2.5 bg-teal-600/80 hover:bg-teal-600 text-white border border-teal-400/40 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2"
              >
                <span>📋 Lihat Jadwal Kuliah Aktif</span>
              </Link>
              <Link
                to="/jadwal"
                className="px-4 py-2.5 bg-teal-950/40 hover:bg-teal-950/60 text-teal-200 border border-teal-500/30 rounded-xl font-medium text-xs sm:text-sm transition flex items-center gap-2"
              >
                <span>🗓️ Kalender Publik</span>
              </Link>
            </div>
          </div>
        </div>

        {/* 3 Metrics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          <div className="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
              <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">
                Periode Akademik Aktif
              </p>
              <h3 className="text-xl font-extrabold text-slate-800">
                {activePeriod ? `TA ${activePeriod.tahun_ajaran}` : 'Belum Ada'}
              </h3>
              <p className="text-xs text-teal-600 font-semibold mt-0.5 capitalize">
                Semester {activePeriod?.semester || '-'}
              </p>
              <p className="text-[11px] text-slate-400 mt-1">
                {formatDate(activePeriod?.tanggal_mulai)} s/d {formatDate(activePeriod?.tanggal_selesai)}
              </p>
            </div>
            <div className="w-12 h-12 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center text-2xl font-bold">
              🎓
            </div>
          </div>

          <div className="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
              <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">
                Jadwal Kuliah Aktif
              </p>
              <h3 className="text-3xl font-extrabold text-slate-800">
                {loadingSchedules ? '...' : activeSchedules.length}
              </h3>
              <p className="text-xs text-slate-500 mt-1">
                Sesi perkuliahan mengunci ruangan
              </p>
            </div>
            <div className="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-2xl font-bold">
              📚
            </div>
          </div>

          <div className="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
              <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">
                Total Riwayat Upload
              </p>
              <h3 className="text-3xl font-extrabold text-slate-800">
                {loadingUploads ? '...' : uploads.length}
              </h3>
              <p className="text-xs text-slate-500 mt-1">
                Berkas datasheet diproses
              </p>
            </div>
            <div className="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-2xl font-bold">
              📁
            </div>
          </div>
        </div>

        {/* Recent Upload History Table */}
        <div className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 className="text-base font-bold text-slate-800">
                Riwayat Pengunggahan Datasheet Jadwal
              </h2>
              <p className="text-xs text-slate-500">
                Daftar file CSV/Excel yang telah diunggah ke sistem database
              </p>
            </div>
            <Link
              to="/akademik/upload"
              className="text-xs font-semibold text-teal-600 hover:text-teal-700 hover:underline"
            >
              + Unggah Berkas Baru
            </Link>
          </div>

          {loadingUploads ? (
            <div className="py-12 text-center text-slate-400 text-sm">
              Memuat riwayat unggahan...
            </div>
          ) : uploads.length === 0 ? (
            <div className="py-12 px-4 text-center">
              <div className="text-4xl mb-3">📄</div>
              <h3 className="text-sm font-bold text-slate-700">Belum ada datasheet yang diunggah</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                Silakan unggah jadwal perkuliahan semester dalam format CSV untuk langsung mengunci slot ruangan perkuliahan.
              </p>
              <Link
                to="/akademik/upload"
                className="mt-4 inline-block px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
              >
                Mulai Upload Jadwal
              </Link>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 text-slate-600 uppercase tracking-wider font-semibold border-b border-slate-200/70">
                  <tr>
                    <th className="py-3 px-4">Waktu Unggah</th>
                    <th className="py-3 px-4">Nama Berkas</th>
                    <th className="py-3 px-4">Program Studi</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {uploads.map((up) => {
                    const isActive = up.status === 'active';
                    const isFailed = up.status === 'failed';
                    return (
                      <tr key={up.id} className="hover:bg-slate-50/70 transition">
                        <td className="py-3 px-4 font-mono text-slate-500">
                          {formatDate(up.created_at)}
                        </td>
                        <td className="py-3 px-4 font-medium text-slate-800">
                          <div className="flex items-center gap-2">
                            <span className="text-base">📄</span>
                            <span>{up.file_path ? up.file_path.split('/').pop() : 'datasheet.csv'}</span>
                          </div>
                        </td>
                        <td className="py-3 px-4 text-slate-600">
                          {up.study_program_id ? 'Prodi Terdaftar' : 'Semua Prodi'}
                        </td>
                        <td className="py-3 px-4">
                          <span
                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${
                              isActive
                                ? 'bg-emerald-100 text-emerald-800'
                                : isFailed
                                ? 'bg-rose-100 text-rose-800'
                                : 'bg-amber-100 text-amber-800'
                            }`}
                          >
                            {isActive ? 'Aktif' : isFailed ? 'Gagal Validasi' : up.status}
                          </span>
                        </td>
                        <td className="py-3 px-4 text-right">
                          <Link
                            to="/akademik/jadwal"
                            className="text-teal-600 hover:text-teal-800 font-semibold"
                          >
                            Lihat Jadwal →
                          </Link>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
