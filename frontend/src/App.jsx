import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout.jsx';

// Public Pages
import Beranda from './pages/Public/Beranda.jsx';
import Jadwal from './pages/Public/Jadwal.jsx';
import Lokasi from './pages/Public/Lokasi.jsx';
import Masuk from './pages/Public/Masuk.jsx';
import Daftar from './pages/Public/Daftar.jsx';
import PerluPemetaan from './pages/Public/PerluPemetaan.jsx';

// User (Pengguna) Pages
import UserDashboard from './pages/User/Dashboard.jsx';
import Ajukan from './pages/User/Ajukan.jsx';
import Riwayat from './pages/User/Riwayat.jsx';
import DetailReservasi from './pages/User/DetailReservasi.jsx';
import Negosiasi from './pages/User/Negosiasi.jsx';
import Notifikasi from './pages/User/Notifikasi.jsx';

// Pengelola Gedung Pages
import PengelolaDashboard from './pages/Pengelola/Dashboard.jsx';
import PengajuanList from './pages/Pengelola/Pengajuan.jsx';
import PeriksaPengajuan from './pages/Pengelola/PeriksaPengajuan.jsx';
import AturJadwal from './pages/Pengelola/AturJadwal.jsx';
import KasusList from './pages/Pengelola/Kasus.jsx';
import DetailKasus from './pages/Pengelola/DetailKasus.jsx';
import LaporanPengelola from './pages/Pengelola/Laporan.jsx';

// Pengelola Sistem Pages
import SistemDashboard from './pages/Sistem/Dashboard.jsx';
import Pengguna from './pages/Sistem/Pengguna.jsx';
import LokasiSistem from './pages/Sistem/Lokasi.jsx';
import Penugasan from './pages/Sistem/Penugasan.jsx';
import GedungTanpaPengelola from './pages/Sistem/GedungTanpaPengelola.jsx';
import Pengaturan from './pages/Sistem/Pengaturan.jsx';
import Log from './pages/Sistem/Log.jsx';
import LaporanSistem from './pages/Sistem/Laporan.jsx';

// Akademik (BAAK) Pages
import AkademikDashboard from './pages/Akademik/Dashboard.jsx';
import UploadJadwal from './pages/Akademik/UploadJadwal.jsx';
import JadwalKuliah from './pages/Akademik/JadwalKuliah.jsx';

// Auth Guard
import ProtectedRoute from './auth/ProtectedRoute.jsx';

const App = () => {
  return (
    <Layout>
      <Routes>
        {/* Public Routes */}
        <Route path="/" element={<Beranda />} />
        <Route path="/jadwal" element={<Jadwal />} />
        <Route path="/lokasi" element={<Lokasi />} />
        <Route path="/masuk" element={<Masuk />} />
        <Route path="/login" element={<Masuk />} />
        <Route path="/daftar" element={<Daftar />} />
        <Route path="/register" element={<Daftar />} />
        <Route path="/perlu-pemetaan" element={<PerluPemetaan />} />

        {/* 👤 Role: Pengguna (/app/...) */}
        <Route element={<ProtectedRoute allowedRoles={['pengguna']} />}>
          <Route path="/app" element={<UserDashboard />} />
          <Route path="/app/dashboard" element={<Navigate to="/app" replace />} />
          <Route path="/app/ajukan" element={<Ajukan />} />
          <Route path="/app/reservasi" element={<Riwayat />} />
          <Route path="/app/reservasi/:id" element={<DetailReservasi />} />
          <Route path="/app/negosiasi/:kasusId" element={<Negosiasi />} />
          <Route path="/app/notifikasi" element={<Notifikasi />} />
        </Route>

        {/* 🏛️ Role: Pengelola Gedung (/pengelola/...) */}
        <Route element={<ProtectedRoute allowedRoles={['pengelola_gedung']} />}>
          <Route path="/pengelola" element={<PengelolaDashboard />} />
          <Route path="/pengelola/pengajuan" element={<PengajuanList />} />
          <Route path="/pengelola/pengajuan/:id" element={<PeriksaPengajuan />} />
          <Route path="/pengelola/atur-jadwal" element={<AturJadwal />} />
          <Route path="/pengelola/jadwal" element={<Navigate to="/pengelola/atur-jadwal" replace />} />
          <Route path="/pengelola/kasus" element={<KasusList />} />
          <Route path="/pengelola/kasus/:id" element={<DetailKasus />} />
          <Route path="/pengelola/laporan" element={<LaporanPengelola />} />
        </Route>

        {/* ⚙️ Role: Pengelola Sistem (/sistem/...) */}
        <Route element={<ProtectedRoute allowedRoles={['pengelola_sistem']} />}>
          <Route path="/sistem" element={<SistemDashboard />} />
          <Route path="/sistem/pengguna" element={<Pengguna />} />
          <Route path="/sistem/lokasi" element={<LokasiSistem />} />
          <Route path="/sistem/penugasan" element={<Penugasan />} />
          <Route path="/sistem/gedung-tanpa-pengelola" element={<GedungTanpaPengelola />} />
          <Route path="/sistem/pengaturan" element={<Pengaturan />} />
          <Route path="/sistem/log" element={<Log />} />
          <Route path="/sistem/laporan" element={<LaporanSistem />} />
        </Route>

        {/* 📚 Role: Bagian Akademik BAAK (/akademik/...) */}
        <Route element={<ProtectedRoute allowedRoles={['akademik']} />}>
          <Route path="/akademik" element={<AkademikDashboard />} />
          <Route path="/akademik/upload" element={<UploadJadwal />} />
          <Route path="/akademik/jadwal" element={<JadwalKuliah />} />
        </Route>

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Layout>
  );
};

export default App;
