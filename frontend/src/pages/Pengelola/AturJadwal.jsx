import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/klien.js';
import TombolChatAdmin from '../../components/TombolChatAdmin.jsx';

const formatTgl = (isoStr) => {
  if (!isoStr) return '-';
  try {
    return new Date(isoStr).toLocaleString('id-ID', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'Asia/Jakarta',
    }) + ' WIB';
  } catch {
    return isoStr;
  }
};

const hitungSisaHari = (targetIso) => {
  if (!targetIso) return null;
  const target = new Date(targetIso).getTime();
  const now = new Date().getTime();
  const diff = Math.ceil((target - now) / (1000 * 60 * 60 * 24));
  return diff;
};

export default function AturJadwal() {
  const qc = useQueryClient();
  const [tabAktif, setTabAktif] = useState('jadwal'); // 'jadwal' | 'acara' | 'kasus'
  const [filterGedungId, setFilterGedungId] = useState('semua');

  // Form Acara Kampus
  const [formAcara, setFormAcara] = useState({
    nama: '',
    lokasi_id: '',
    tgl_mulai: '',
    jam_mulai: '08:00',
    tgl_selesai: '',
    jam_selesai: '17:00',
    alasan: '',
  });

  const [pratinjauData, setPratinjauData] = useState(null);
  const [pratinjauError, setPratinjauError] = useState('');

  // 1. Fetch Gedung yang dikelola
  const { data: listLokasi = [] } = useQuery({
    queryKey: ['pengelola_lokasi_dikelola'],
    queryFn: async () => {
      const res = await api.get('/pengelola/lokasi');
      const data = res.data?.gedung_dikelola || res.data?.lokasi_dikelola || res.data?.data || res.data || [];
      return Array.isArray(data) ? data : [];
    },
  });

  // 2. Fetch Reservasi Disetujui
  const { data: listDisetujui = [], isLoading: loadingJadwal } = useQuery({
    queryKey: ['pengelola_jadwal_disetujui'],
    queryFn: async () => {
      const res = await api.get('/pengelola/reservasi', { params: { status: 'disetujui' } });
      return res.data?.data || res.data || [];
    },
  });

  // 3. Fetch Kasus Belum Diputuskan
  const { data: listKasusTerbuka = [], isLoading: loadingKasus } = useQuery({
    queryKey: ['pengelola_kasus_terbuka'],
    queryFn: async () => {
      const res = await api.get('/pengelola/kasus', { params: { status: 'terbuka' } });
      return res.data?.data || res.data || [];
    },
  });

  // Gabungkan jadwal reservasi
  const filteredJadwal = (Array.isArray(listDisetujui) ? listDisetujui : []).filter((item) => {
    if (filterGedungId === 'semua') return true;
    return item.lokasi_id === filterGedungId || item.gedung_id === filterGedungId;
  });

  // Helper bikin datetime ISO
  const buatIso = (tgl, jam) => {
    if (!tgl || !jam) return '';
    return `${tgl}T${jam}:00`;
  };

  // Mutasi Pratinjau
  const mutasiPratinjau = useMutation({
    mutationFn: async () => {
      setPratinjauError('');
      const payload = {
        lokasi_id: formAcara.lokasi_id,
        mulai: buatIso(formAcara.tgl_mulai, formAcara.jam_mulai),
        selesai: buatIso(formAcara.tgl_selesai, formAcara.jam_selesai),
      };
      const res = await api.post('/pengelola/acara-kampus/pratinjau', payload);
      return res.data;
    },
    onSuccess: (data) => {
      setPratinjauData(data);
    },
    onError: (err) => {
      setPratinjauError(err.response?.data?.galat || err.response?.data?.message || 'Gagal memeriksa bentrok acara.');
    },
  });

  // Mutasi Simpan Acara Kampus
  const mutasiSimpanAcara = useMutation({
    mutationFn: async () => {
      const payload = {
        nama: formAcara.nama,
        lokasi_id: formAcara.lokasi_id,
        mulai: buatIso(formAcara.tgl_mulai, formAcara.jam_mulai),
        selesai: buatIso(formAcara.tgl_selesai, formAcara.jam_selesai),
        alasan: formAcara.alasan,
      };
      const res = await api.post('/pengelola/acara-kampus', payload);
      return res.data;
    },
    onSuccess: (res) => {
      alert(res.pesan || 'Acara kampus berhasil didaftarkan.');
      setPratinjauData(null);
      setFormAcara({
        nama: '',
        lokasi_id: '',
        tgl_mulai: '',
        jam_mulai: '08:00',
        tgl_selesai: '',
        jam_selesai: '17:00',
        alasan: '',
      });
      qc.invalidateQueries({ queryKey: ['pengelola_jadwal_disetujui'] });
      qc.invalidateQueries({ queryKey: ['pengelola_kasus_terbuka'] });
      setTabAktif('jadwal');
    },
    onError: (err) => {
      alert(err.response?.data?.galat || err.response?.data?.message || 'Gagal mendaftarkan acara kampus.');
    },
  });

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-slate-900 mb-1">Atur Jadwal &amp; Acara Gedung</h1>
        <p className="text-sm text-slate-500">
          Kelola kalender pemakaian gedung, daftarkan kegiatan resmi kampus, dan pantau negosiasi penggusuran.
        </p>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-slate-200 pb-2">
        <button
          onClick={() => setTabAktif('jadwal')}
          className={`px-4 py-2 text-xs font-semibold rounded-xl transition ${
            tabAktif === 'jadwal'
              ? 'bg-blue-600 text-white shadow-sm'
              : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
          }`}
        >
          📅 Kalender Jadwal Aktif
        </button>
        <button
          onClick={() => setTabAktif('acara')}
          className={`px-4 py-2 text-xs font-semibold rounded-xl transition ${
            tabAktif === 'acara'
              ? 'bg-blue-600 text-white shadow-sm'
              : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
          }`}
        >
          🏛️ Daftarkan Acara Kampus (Prioritas)
        </button>
        <button
          onClick={() => setTabAktif('kasus')}
          className={`px-4 py-2 text-xs font-semibold rounded-xl transition flex items-center gap-1.5 ${
            tabAktif === 'kasus'
              ? 'bg-blue-600 text-white shadow-sm'
              : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
          }`}
        >
          <span>⏳ Kasus Belum Diputuskan</span>
          {listKasusTerbuka.length > 0 && (
            <span className="bg-rose-500 text-white text-[10px] px-1.5 py-0.2 rounded-full font-bold">
              {listKasusTerbuka.length}
            </span>
          )}
        </button>
      </div>

      {/* TAB 1: KALENDER JADWAL */}
      {tabAktif === 'jadwal' && (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <h2 className="font-bold text-slate-800 text-sm">Daftar Jadwal Gedung Disetujui</h2>
            <div className="flex items-center gap-2">
              <label className="text-xs text-slate-500 font-medium">Filter Gedung:</label>
              <select
                value={filterGedungId}
                onChange={(e) => setFilterGedungId(e.target.value)}
                className="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option value="semua">Semua Gedung Dikelola</option>
                {listLokasi.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.nama}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {loadingJadwal ? (
            <div className="py-16 text-center text-slate-400 text-xs">Memuat jadwal...</div>
          ) : filteredJadwal.length === 0 ? (
            <div className="py-16 text-center text-slate-400">
              <p className="text-3xl mb-2">🗓️</p>
              <p className="text-sm">Tidak ada jadwal peminjaman disetujui dalam filter ini.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                    <th className="py-3 px-4 text-left">Kode</th>
                    <th className="py-3 px-4 text-left">Gedung</th>
                    <th className="py-3 px-4 text-left">Pemohon &amp; Organisasi</th>
                    <th className="py-3 px-4 text-left">Waktu Penggunaan</th>
                    <th className="py-3 px-4 text-left">Keperluan</th>
                    <th className="py-3 px-4 text-left">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {filteredJadwal.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50/70 transition">
                      <td className="py-3 px-4 font-mono font-bold text-blue-600">{item.kode || item.id.slice(0, 8)}</td>
                      <td className="py-3 px-4 font-semibold text-slate-800">{item.gedung || item.lokasi?.nama || '-'}</td>
                      <td className="py-3 px-4">
                        <div className="text-slate-800 font-medium">{item.pemohon || item.pengguna?.nama || '-'}</div>
                        <div className="text-[10px] text-slate-400">{item.organisasi || item.pengguna?.organisasi || '-'}</div>
                      </td>
                      <td className="py-3 px-4 whitespace-nowrap text-slate-600">
                        <div>{formatTgl(item.mulai)}</div>
                        <div className="text-[10px] text-slate-400">s/d {formatTgl(item.selesai)}</div>
                      </td>
                      <td className="py-3 px-4 text-slate-600 max-w-xs truncate">{item.keperluan || '-'}</td>
                      <td className="py-3 px-4">
                        <span className="px-2.5 py-1 bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-full font-bold text-[10px]">
                          Disetujui
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: DAFTARKAN ACARA KAMPUS */}
      {tabAktif === 'acara' && (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-5">
          <div className="border-b border-slate-100 pb-3">
            <h2 className="font-bold text-slate-800 text-sm">Formulir Pendaftaran Acara Resmi Kampus</h2>
            <p className="text-xs text-slate-500 mt-0.5">
              Acara kampus memiliki hak prioritas utama. Jika waktu bertabrakan dengan reservasi organisasi/mahasiswa,
              sistem akan membuka alur penggusuran dan menugaskan Agen AI untuk menegosiasikan jadwal pengganti.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div className="sm:col-span-2">
              <label className="block text-slate-600 font-semibold mb-1">Nama Kegiatan Acara Kampus *</label>
              <input
                type="text"
                value={formAcara.nama}
                onChange={(e) => setFormAcara({ ...formAcara, nama: e.target.value })}
                placeholder="Misal: Wisuda Periode Gasal 2026 / Dies Natalis Polinela"
                className="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>

            <div>
              <label className="block text-slate-600 font-semibold mb-1">Pilih Gedung *</label>
              <select
                value={formAcara.lokasi_id}
                onChange={(e) => setFormAcara({ ...formAcara, lokasi_id: e.target.value })}
                className="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option value="">-- Pilih Gedung Dikelola --</option>
                {listLokasi.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.nama} (Kapasitas: {l.kapasitas} orang)
                  </option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-slate-600 font-semibold mb-1">Tanggal Mulai *</label>
                <input
                  type="date"
                  value={formAcara.tgl_mulai}
                  onChange={(e) => setFormAcara({ ...formAcara, tgl_mulai: e.target.value })}
                  className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="block text-slate-600 font-semibold mb-1">Jam Mulai *</label>
                <input
                  type="time"
                  value={formAcara.jam_mulai}
                  onChange={(e) => setFormAcara({ ...formAcara, jam_mulai: e.target.value })}
                  className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-slate-600 font-semibold mb-1">Tanggal Selesai *</label>
                <input
                  type="date"
                  value={formAcara.tgl_selesai}
                  onChange={(e) => setFormAcara({ ...formAcara, tgl_selesai: e.target.value })}
                  className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="block text-slate-600 font-semibold mb-1">Jam Selesai *</label>
                <input
                  type="time"
                  value={formAcara.jam_selesai}
                  onChange={(e) => setFormAcara({ ...formAcara, jam_selesai: e.target.value })}
                  className="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
            </div>

            <div className="sm:col-span-2">
              <label className="block text-slate-600 font-semibold mb-1">
                Alasan Penggusuran / Keterangan Resmi Kegiatan *
              </label>
              <textarea
                rows={3}
                value={formAcara.alasan}
                onChange={(e) => setFormAcara({ ...formAcara, alasan: e.target.value })}
                placeholder="Tuliskan keterangan lengkap kegiatan kampus. Teks ini akan langsung dikirimkan ke pemohon yang reservasi sebelumnya tergusur."
                className="w-full border border-slate-300 rounded-xl p-3 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500 leading-relaxed"
              />
            </div>
          </div>

          {/* Action Pratinjau */}
          <div className="flex gap-2.5 pt-2">
            <button
              type="button"
              onClick={() => mutasiPratinjau.mutate()}
              disabled={
                mutasiPratinjau.isPending ||
                !formAcara.nama ||
                !formAcara.lokasi_id ||
                !formAcara.tgl_mulai ||
                !formAcara.tgl_selesai
              }
              className="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl text-xs font-semibold shadow-sm transition"
            >
              {mutasiPratinjau.isPending ? 'Memeriksa Dampak Bentrok...' : '🔍 Periksa Pratinjau Dampak Acara'}
            </button>
          </div>

          {pratinjauError && (
            <div className="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-medium">
              ⚠️ {pratinjauError}
            </div>
          )}

          {/* Hasil Pratinjau */}
          {pratinjauData && (
            <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3 text-xs">
              <h3 className="font-bold text-slate-800 text-sm">📋 Hasil Pratinjau Dampak Penggusuran</h3>
              {pratinjauData.jumlah_terdampak > 0 ? (
                <div className="space-y-2">
                  <div className="bg-amber-50 border border-amber-200 p-3 rounded-xl text-amber-800">
                    <p className="font-semibold">
                      ⚠️ Acara ini akan menggusur {pratinjauData.jumlah_terdampak} reservasi yang telah disetujui sebelumnya:
                    </p>
                    <ul className="list-disc list-inside mt-1.5 space-y-1">
                      {pratinjauData.reservasi_terdampak?.map((r) => (
                        <li key={r.id}>
                          <span className="font-mono font-bold">{r.kode}</span> — {r.pengguna_nama || r.pemohon} ({r.keperluan || 'Kegiatan'})
                        </li>
                      ))}
                    </ul>
                  </div>
                  <p className="text-slate-500 text-[11px]">
                    Setelah Anda mengonfirmasi, reservasi di atas akan berstatus <strong>TERGUSUR</strong>, memo lama dibatalkan,
                    dan kasus negosiasi jadwal pengganti akan dibuka otomatis oleh Agen AI.
                  </p>
                </div>
              ) : (
                <div className="bg-emerald-50 border border-emerald-200 p-3 rounded-xl text-emerald-800">
                  ✅ <strong>Aman!</strong> Tidak ada reservasi disetujui yang bentrok dengan jadwal acara ini.
                </div>
              )}

              <div className="pt-2 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => mutasiSimpanAcara.mutate()}
                  disabled={mutasiSimpanAcara.isPending || !formAcara.alasan.trim()}
                  className="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 disabled:opacity-40 text-white rounded-xl text-xs font-semibold shadow-sm transition"
                >
                  {mutasiSimpanAcara.isPending ? 'Menyimpan Acara...' : '✅ Konfirmasi & Daftarkan Acara Kampus'}
                </button>
                {!formAcara.alasan.trim() && (
                  <span className="text-[11px] text-rose-500 italic">* Alasan kegiatan wajib diisi sebelum menyimpan</span>
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 3: BELUM DIPUTUSKAN PENGGUNA */}
      {tabAktif === 'kasus' && (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 space-y-4">
          <div className="border-b border-slate-100 pb-3">
            <h2 className="font-bold text-slate-800 text-sm">Daftar Kasus Penggusuran Belum Diputuskan</h2>
            <p className="text-xs text-slate-500 mt-0.5">
              Reservasi yang tergusur dan masih dalam tahap negosiasi pemilihan opsi pengganti bersama Agen AI.
              Baris dengan sisa hari acara ≤ 3 hari disorot untuk koordinasi mendesak via WhatsApp.
            </p>
          </div>

          {loadingKasus ? (
            <div className="py-16 text-center text-slate-400 text-xs">Memuat daftar kasus...</div>
          ) : listKasusTerbuka.length === 0 ? (
            <div className="py-16 text-center text-slate-400">
              <p className="text-3xl mb-2">🎉</p>
              <p className="text-sm">Tidak ada kasus penggusuran yang sedang tertunda.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold border-b border-slate-100">
                    <th className="py-3 px-4 text-left">Organisasi &amp; Pemohon</th>
                    <th className="py-3 px-4 text-left">Reservasi Asal</th>
                    <th className="py-3 px-4 text-left">Gedung</th>
                    <th className="py-3 px-4 text-left">Jadwal Acara Asal</th>
                    <th className="py-3 px-4 text-left">Sisa Waktu</th>
                    <th className="py-3 px-4 text-left">Aksi Koordinasi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {listKasusTerbuka.map((kasus) => {
                    const sisaHari = hitungSisaHari(kasus.mulai_tergusur);
                    const isMendesak = sisaHari !== null && sisaHari <= 3;

                    return (
                      <tr
                        key={kasus.id}
                        className={`transition ${isMendesak ? 'bg-rose-50/70' : 'hover:bg-slate-50/70'}`}
                      >
                        <td className="py-3 px-4">
                          <div className="font-semibold text-slate-800">{kasus.organisasi || kasus.pemohon}</div>
                          <div className="text-[10px] text-slate-400">{kasus.pemohon}</div>
                        </td>
                        <td className="py-3 px-4 font-mono font-bold text-blue-600">{kasus.kode_reservasi || '-'}</td>
                        <td className="py-3 px-4 text-slate-700 font-medium">{kasus.gedung || '-'}</td>
                        <td className="py-3 px-4 text-slate-600 whitespace-nowrap">{formatTgl(kasus.mulai_tergusur)}</td>
                        <td className="py-3 px-4 whitespace-nowrap">
                          {isMendesak ? (
                            <span className="px-2 py-0.5 bg-rose-100 text-rose-700 font-bold rounded-md text-[10px]">
                              ⚠️ {sisaHari <= 0 ? 'Hari Ini / Lewat' : `${sisaHari} hari lagi`}
                            </span>
                          ) : (
                            <span className="text-slate-600">{sisaHari !== null ? `${sisaHari} hari lagi` : '-'}</span>
                          )}
                        </td>
                        <td className="py-3 px-4">
                          <TombolChatAdmin kasusId={kasus.id} />
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
