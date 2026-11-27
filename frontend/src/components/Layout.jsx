import React, { useState, useRef, useEffect } from 'react';
import { NavLink, Link, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '../auth/AuthProvider.jsx';
import api from '../api/klien.js';
import { parseTeksRingkas } from '../utils/format.js';

const Layout = ({ children }) => {
  const { user, logout, setRole } = useAuth();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const [showAssistant, setShowAssistant] = useState(false);
  const [chatMessages, setChatMessages] = useState([
    { sender: 'bot', text: 'Halo! Saya Asisten Informasi SMARTSPACE. Ada yang bisa saya bantu terkait jadwal, kapasitas, atau aturan peminjaman gedung?' }
  ]);
  const [chatInput, setChatInput] = useState('');
  const [isBotTyping, setIsBotTyping] = useState(false);
  const [sessionId] = useState(() => {
    let s = sessionStorage.getItem('asisten_info_sesi');
    if (!s) {
      s = 'sesi_' + Math.random().toString(36).substring(2, 12);
      sessionStorage.setItem('asisten_info_sesi', s);
    }
    return s;
  });

  // 0. Daftar lokasi aktif untuk kartu konfirmasi laporan fasilitas
  const { data: daftarLokasiChat = [] } = useQuery({
    queryKey: ['chat_daftar_lokasi'],
    queryFn: async () => {
      const res = await api.get('/lokasi');
      const data = res.data?.data || res.data || [];
      return Array.isArray(data) ? data : [];
    },
    staleTime: 60000,
  });

  // 1. Polling notifikasi unread tiap 30 detik
  const { data: notifData = [] } = useQuery({
    queryKey: ['header_notifikasi_unread', user?.role],
    queryFn: async () => {
      if (!user) return [];
      const res = await api.get('/notifikasi');
      const list = Array.isArray(res.data?.data) ? res.data.data : Array.isArray(res.data) ? res.data : [];
      return list.filter((n) => !n.dibaca_pada);
    },
    enabled: Boolean(user),
    refetchInterval: 30000,
  });
  const unreadNotifCount = notifData.length;

  // 2. Kasus penggusuran aktif milik pengguna (untuk menu & notifikasi banner)
  const { data: userKasusList = [] } = useQuery({
    queryKey: ['header_user_kasus_aktif'],
    queryFn: async () => {
      if (user?.role !== 'pengguna') return [];
      const res = await api.get('/kasus/saya');
      const list = Array.isArray(res.data) ? res.data : [];
      return list.filter((k) => ['terbuka', 'diputuskan'].includes(k.status));
    },
    enabled: user?.role === 'pengguna',
    refetchInterval: 30000,
  });
  const openKasus = userKasusList[0];

  // 3. Counter pengajuan menunggu milik pengelola gedung
  const { data: pengelolaMenunggu = [] } = useQuery({
    queryKey: ['header_pengelola_menunggu'],
    queryFn: async () => {
      if (user?.role !== 'pengelola_gedung') return [];
      const res = await api.get('/pengelola/reservasi', { params: { status: 'menunggu' } });
      return res.data?.data || res.data || [];
    },
    enabled: user?.role === 'pengelola_gedung',
    refetchInterval: 30000,
  });
  const countMenunggu = pengelolaMenunggu.length;

  const kirimPesanCepat = async (msg) => {
    if (!msg.trim() || isBotTyping) return;
    setChatMessages((prev) => [...prev, { sender: 'user', text: msg }]);
    setIsBotTyping(true);
    try {
      const res = await api.post('/asisten-info/pesan', { pesan: msg, sesi: sessionId });
      const reply = res.data?.jawaban || res.data?.pesan || 'Terima kasih, pesan Anda telah diterima.';
      setChatMessages((prev) => [
        ...prev,
        {
          sender: 'bot',
          text: reply,
          usulanLaporan: res.data?.usulan_laporan || null,
          laporanStatus: null,
        },
      ]);
    } catch {
      setChatMessages((prev) => [
        ...prev,
        { sender: 'bot', text: 'Asisten sedang sibuk. Silakan coba sesaat lagi.' }
      ]);
    } finally {
      setIsBotTyping(false);
    }
  };

  const handleKonfirmasiLaporan = async (msgIdx, payload) => {
    setChatMessages((prev) =>
      prev.map((m, i) => (i === msgIdx ? { ...m, laporanStatus: 'sending' } : m))
    );
    try {
      const formData = new FormData();
      if (payload.lokasi_id) formData.append('lokasi_id', payload.lokasi_id);
      formData.append('ringkasan', payload.ringkasan);
      formData.append('isi', payload.isi);
      formData.append('konfirmasi', '1');
      formData.append('anonim', payload.anonim ? '1' : '0');
      if (payload.foto) formData.append('foto', payload.foto);

      await api.post('/laporan-fasilitas', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      qc.invalidateQueries({ queryKey: ['publik_beranda_lokasi'] });
      qc.invalidateQueries({ queryKey: ['manajemen_laporan_fasilitas'] });

      setChatMessages((prev) =>
        prev.map((m, i) => (i === msgIdx ? { ...m, laporanStatus: 'sent' } : m))
      );
    } catch {
      setChatMessages((prev) =>
        prev.map((m, i) => (i === msgIdx ? { ...m, laporanStatus: 'error' } : m))
      );
    }
  };

  const handleBatalLaporan = (msgIdx) => {
    setChatMessages((prev) =>
      prev.map((m, i) => (i === msgIdx ? { ...m, laporanStatus: 'cancelled' } : m))
    );
  };

  const handleSendChat = async (e) => {
    e.preventDefault();
    if (!chatInput.trim() || isBotTyping) return;
    const msg = chatInput.trim();
    setChatInput('');
    await kirimPesanCepat(msg);
  };

  const navClass = ({ isActive }) =>
    `px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
      isActive
        ? 'bg-blue-600 text-white shadow-sm'
        : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50'
    }`;

  return (
    <div className="min-h-screen flex flex-col bg-slate-50 text-gray-800">
      {/* 🎭 TOP DEMO PREVIEW BAR */}
      <aside aria-label="Demo role switcher" className="bg-slate-900 text-slate-300 text-xs py-2 px-4 border-b border-slate-800">
        <div className="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <span className="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span className="font-semibold text-white">SMARTSPACE UI Preview</span>
            <span className="hidden sm:inline text-slate-400">| Pratinjau Alur 4 Peran Sistem</span>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-slate-400">Ganti Peran:</span>
            <button
              onClick={() => { setRole(null); navigate('/'); }}
              className={`px-2.5 py-1 rounded text-xs font-medium transition ${
                !user ? 'bg-blue-500 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'
              }`}
            >
              🌐 Tamu (Publik)
            </button>
            <button
              onClick={() => { setRole('pengguna'); navigate('/app'); }}
              className={`px-2.5 py-1 rounded text-xs font-medium transition ${
                user?.role === 'pengguna' ? 'bg-blue-500 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'
              }`}
            >
              👤 Pengguna
            </button>
            <button
              onClick={() => { setRole('pengelola_gedung'); navigate('/pengelola'); }}
              className={`px-2.5 py-1 rounded text-xs font-medium transition ${
                user?.role === 'pengelola_gedung' ? 'bg-amber-500 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'
              }`}
            >
              🏛️ Pengelola Gedung
            </button>
            <button
              onClick={() => { setRole('pengelola_sistem'); navigate('/sistem'); }}
              className={`px-2.5 py-1 rounded text-xs font-medium transition ${
                user?.role === 'pengelola_sistem' ? 'bg-purple-500 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'
              }`}
            >
              ⚙️ Pengelola Sistem
            </button>
            <button
              onClick={() => { setRole('akademik'); navigate('/akademik'); }}
              className={`px-2.5 py-1 rounded text-xs font-medium transition ${
                user?.role === 'akademik' ? 'bg-teal-500 text-white font-bold' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'
              }`}
            >
              📚 Akademik (BAAK)
            </button>
          </div>
        </div>
      </aside>

      {/* 🏛️ MAIN HEADER & NAVIGATION */}
      <header className="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            {/* Logo Brand */}
            <div className="flex items-center gap-3">
              <Link to={user?.role === 'pengelola_gedung' ? '/pengelola' : user?.role === 'pengelola_sistem' ? '/sistem' : user?.role === 'akademik' ? '/akademik' : user ? '/app' : '/'} className="flex items-center gap-2">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white text-xl shadow-md font-black">
                  S
                </div>
                <div>
                  <span className="font-extrabold text-xl tracking-tight text-slate-900 flex items-center gap-1.5">
                    SMARTSPACE <span className="text-xs bg-blue-100 text-blue-700 font-semibold px-2 py-0.5 rounded-full">AI</span>
                  </span>
                  <span className="block text-[11px] text-slate-400 font-medium -mt-1">Peminjaman Gedung & Ruangan</span>
                </div>
              </Link>
            </div>

            {/* Navigation Menus based on Role */}
            <nav className="hidden md:flex items-center gap-1">
              {/* Public Menus */}
              {!user && (
                <>
                  <NavLink to="/" end className={navClass}>Beranda</NavLink>
                  <NavLink to="/jadwal" className={navClass}>Jadwal Publik</NavLink>
                </>
              )}

              {/* Pengguna Menus */}
              {user?.role === 'pengguna' && (
                <>
                  <NavLink to="/app" end className={navClass}>Dashboard</NavLink>
                  <NavLink to="/app/ajukan?jenis=gedung" className={navClass}>🏛️ Pinjam Gedung</NavLink>
                  <NavLink to="/app/ajukan?jenis=ruangan" className={navClass}>⚡ Pinjam Ruangan</NavLink>
                  <NavLink to="/app/reservasi" className={navClass}>Riwayat Saya</NavLink>
                  {openKasus && (
                    <NavLink to={`/app/negosiasi/${openKasus.id}`} className={navClass}>
                      ⚖️ Negosiasi Kasus
                      <span className="ml-1.5 px-1.5 py-0.5 text-[10px] bg-red-100 text-red-600 rounded-full font-bold animate-pulse">
                        {userKasusList.length}
                      </span>
                    </NavLink>
                  )}
                  <NavLink to="/app/notifikasi" className={navClass}>
                    Notifikasi
                    {unreadNotifCount > 0 && (
                      <span className="ml-1.5 px-1.5 py-0.5 text-[10px] bg-rose-500 text-white rounded-full font-bold">
                        {unreadNotifCount}
                      </span>
                    )}
                  </NavLink>
                </>
              )}

              {/* Pengelola Gedung Menus */}
              {user?.role === 'pengelola_gedung' && (
                <>
                  <NavLink to="/pengelola" end className={navClass}>Dashboard Gedung</NavLink>
                  <NavLink to="/pengelola/pengajuan" className={navClass}>
                    Daftar Pengajuan
                    {countMenunggu > 0 && (
                      <span className="ml-1.5 px-1.5 py-0.5 text-[10px] bg-amber-500 text-white rounded-full font-bold">
                        {countMenunggu}
                      </span>
                    )}
                  </NavLink>
                  <NavLink to="/pengelola/atur-jadwal" className={navClass}>Atur Jadwal</NavLink>
                  <NavLink to="/pengelola/kasus" className={navClass}>Kasus Negosiasi</NavLink>
                  <NavLink to="/pengelola/laporan-fasilitas" className={navClass}>Laporan Kerusakan</NavLink>
                  <NavLink to="/pengelola/laporan" className={navClass}>Laporan</NavLink>
                </>
              )}

              {/* Pengelola Sistem Menus */}
              {user?.role === 'pengelola_sistem' && (
                <>
                  <NavLink to="/sistem" end className={navClass}>Dashboard</NavLink>
                  <NavLink to="/sistem/pengguna" className={navClass}>Akun & Role</NavLink>
                  <NavLink to="/sistem/lokasi" className={navClass}>Lokasi</NavLink>
                  <NavLink to="/sistem/penugasan" className={navClass}>Penugasan</NavLink>
                  <NavLink to="/sistem/gedung-tanpa-pengelola" className={navClass}>Gedung Terbengkalai</NavLink>
                  <NavLink to="/sistem/pengaturan" className={navClass}>Pengaturan</NavLink>
                  <NavLink to="/sistem/log" className={navClass}>Log</NavLink>
                  <NavLink to="/sistem/laporan-fasilitas" className={navClass}>Kerusakan</NavLink>
                  <NavLink to="/sistem/laporan" className={navClass}>Laporan</NavLink>
                </>
              )}

              {/* Akademik (BAAK) Menus */}
              {user?.role === 'akademik' && (
                <>
                  <NavLink to="/akademik" end className={navClass}>Dashboard</NavLink>
                  <NavLink to="/akademik/upload" className={navClass}>📤 Upload Jadwal</NavLink>
                  <NavLink to="/akademik/jadwal" className={navClass}>📋 Jadwal Kuliah</NavLink>
                  <NavLink to="/jadwal" className={navClass}>🗓️ Kalender Ruangan</NavLink>
                </>
              )}
            </nav>

            {/* Right Side / Auth actions */}
            <div className="flex items-center gap-3">
              {!user ? (
                <div className="flex items-center gap-2">
                  <Link
                    to="/masuk"
                    className="px-4 py-2 text-sm font-medium text-slate-700 hover:text-blue-600 transition"
                  >
                    Masuk
                  </Link>
                  <Link
                    to="/daftar"
                    className="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition"
                  >
                    Daftar
                  </Link>
                </div>
              ) : (
                <div className="flex items-center gap-3">
                  {/* Lonceng Notifikasi */}
                  <Link
                    to="/app/notifikasi"
                    title="Notifikasi"
                    className="relative p-2 text-slate-500 hover:text-blue-600 hover:bg-slate-100 rounded-xl transition"
                  >
                    <span className="text-lg leading-none">🔔</span>
                    {unreadNotifCount > 0 && (
                      <span className="absolute top-1 right-1 flex items-center justify-center min-w-[16px] h-4 px-1 text-[9px] font-extrabold text-white bg-rose-600 rounded-full shadow-xs animate-pulse">
                        {unreadNotifCount > 9 ? '9+' : unreadNotifCount}
                      </span>
                    )}
                  </Link>

                  <div className="text-right hidden sm:block">
                    <p className="text-xs font-semibold text-slate-800 leading-tight">{user.name}</p>
                    <span className="text-[10px] font-medium uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                      {user.role.replace('_', ' ')}
                    </span>
                  </div>
                  <button
                    onClick={logout}
                    className="px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-lg border border-rose-200 transition"
                  >
                    Keluar
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Mobile Nav Bar */}
        <div className="md:hidden border-t border-slate-100 px-4 py-2 bg-slate-50 overflow-x-auto flex gap-1 whitespace-nowrap">
          {!user && (
            <>
              <NavLink to="/" end className={navClass}>Beranda</NavLink>
              <NavLink to="/jadwal" className={navClass}>Jadwal</NavLink>
            </>
          )}
          {user?.role === 'pengguna' && (
            <>
              <NavLink to="/app" end className={navClass}>Dashboard</NavLink>
              <NavLink to="/app/ajukan?jenis=gedung" className={navClass}>🏛️ Pinjam Gedung</NavLink>
              <NavLink to="/app/ajukan?jenis=ruangan" className={navClass}>⚡ Pinjam Ruangan</NavLink>
              <NavLink to="/app/reservasi" className={navClass}>Riwayat</NavLink>
              <NavLink to="/app/negosiasi/1" className={navClass}>Negosiasi</NavLink>
              <NavLink to="/app/notifikasi" className={navClass}>Notifikasi</NavLink>
            </>
          )}
          {user?.role === 'pengelola_gedung' && (
            <>
              <NavLink to="/pengelola" end className={navClass}>Dashboard</NavLink>
              <NavLink to="/pengelola/pengajuan" className={navClass}>Pengajuan</NavLink>
              <NavLink to="/pengelola/atur-jadwal" className={navClass}>Atur Jadwal</NavLink>
              <NavLink to="/pengelola/kasus" className={navClass}>Kasus</NavLink>
              <NavLink to="/pengelola/laporan" className={navClass}>Laporan</NavLink>
            </>
          )}
          {user?.role === 'pengelola_sistem' && (
            <>
              <NavLink to="/sistem" end className={navClass}>Dashboard</NavLink>
              <NavLink to="/sistem/pengguna" className={navClass}>Pengguna</NavLink>
              <NavLink to="/sistem/lokasi" className={navClass}>Lokasi</NavLink>
              <NavLink to="/sistem/penugasan" className={navClass}>Penugasan</NavLink>
              <NavLink to="/sistem/pengaturan" className={navClass}>Pengaturan</NavLink>
              <NavLink to="/sistem/log" className={navClass}>Log</NavLink>
            </>
          )}
          {user?.role === 'akademik' && (
            <>
              <NavLink to="/akademik" end className={navClass}>Dashboard</NavLink>
              <NavLink to="/akademik/upload" className={navClass}>Upload Jadwal</NavLink>
              <NavLink to="/akademik/jadwal" className={navClass}>Jadwal Kuliah</NavLink>
              <NavLink to="/jadwal" className={navClass}>Kalender</NavLink>
            </>
          )}
        </div>
      </header>

      {/* 🚀 MAIN CONTENT CONTAINER */}
      <main className="flex-1">
        {children}
      </main>

      {/* 💬 FLOATING AI ASSISTANT (ASISTEN INFORMASI) */}
      <div className="fixed bottom-6 right-6 z-50">
        {!showAssistant ? (
          <button
            onClick={() => setShowAssistant(true)}
            className="flex items-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-4 py-3 rounded-full shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 font-medium text-sm"
          >
            <span className="text-xl">🤖</span>
            <span>Asisten Informasi</span>
          </button>
        ) : (
          <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-80 sm:w-96 overflow-hidden flex flex-col h-[420px]">
            <div className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-3 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="text-lg">🤖</span>
                <div>
                  <h4 className="font-bold text-sm leading-tight">Asisten Informasi SMARTSPACE</h4>
                  <span className="text-[10px] text-blue-100">Bantuan cepat jadwal & aturan gedung</span>
                </div>
              </div>
              <button
                onClick={() => setShowAssistant(false)}
                className="text-white hover:text-slate-200 text-xl font-bold p-1 leading-none"
              >
                ×
              </button>
            </div>

            {/* Chat message history */}
            <div className="flex-1 p-2.5 overflow-y-auto space-y-2 bg-slate-50 text-xs">
              {chatMessages.map((m, idx) => (
                <div
                  key={idx}
                  className={`flex flex-col ${m.sender === 'user' ? 'items-end' : 'items-start'}`}
                >
                  <div
                    className={`max-w-[88%] rounded-xl px-2.5 py-1.5 leading-snug whitespace-pre-line ${
                      m.sender === 'user'
                        ? 'bg-blue-600 text-white rounded-br-none'
                        : 'bg-white border border-slate-200 text-slate-800 rounded-bl-none shadow-xs'
                    }`}
                  >
                    {parseTeksRingkas(m.text).map((tok, i) => {
                      if (tok.type === 'bold') return <strong key={i} className="font-semibold">{tok.value}</strong>;
                      if (tok.type === 'italic') return <em key={i} className="italic">{tok.value}</em>;
                      return <React.Fragment key={i}>{tok.value}</React.Fragment>;
                    })}
                  </div>

                  {m.sender === 'bot' && m.usulanLaporan && (
                    <KartuKonfirmasiLaporan
                      usulan={m.usulanLaporan}
                      status={m.laporanStatus}
                      daftarLokasi={daftarLokasiChat}
                      onKonfirmasi={(payload) => handleKonfirmasiLaporan(idx, payload)}
                      onBatal={() => handleBatalLaporan(idx)}
                    />
                  )}
                </div>
              ))}
              {isBotTyping && (
                <div className="flex justify-start">
                  <div className="bg-white border border-slate-200 text-slate-500 rounded-xl rounded-bl-none px-3 py-2 text-xs italic shadow-sm flex items-center gap-1.5">
                    <span className="inline-block w-1.5 h-1.5 bg-blue-600 rounded-full animate-bounce"></span>
                    <span className="inline-block w-1.5 h-1.5 bg-blue-600 rounded-full animate-bounce" style={{ animationDelay: '0.15s' }}></span>
                    <span className="inline-block w-1.5 h-1.5 bg-blue-600 rounded-full animate-bounce" style={{ animationDelay: '0.3s' }}></span>
                    <span className="ml-1 text-[11px]">Asisten sedang menjawab...</span>
                  </div>
                </div>
              )}
            </div>

            {/* Input form */}
            <div className="border-t border-slate-200 bg-white p-2 space-y-1.5">
              <div className="flex flex-wrap gap-1">
                {['Kapasitas GSG?', 'Syarat pinjam gedung?', 'Gedung kosong hari ini?'].map((q) => (
                  <button
                    key={q}
                    type="button"
                    disabled={isBotTyping}
                    onClick={() => kirimPesanCepat(q)}
                    className="text-[10px] bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-700 px-2 py-0.5 rounded-full border border-slate-200 transition"
                  >
                    {q}
                  </button>
                ))}
              </div>
              <a
                href="https://wa.me/62895336819200?text=Halo%20Bu%20Eci,%20saya%20ingin%20berkonsultasi%20mengenai%20fasilitas%20gedung%20kampus%20di%20SMARTSPACE."
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-1.5 text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100/80 py-1 px-2.5 rounded-lg border border-emerald-200 transition"
              >
                <span>💬</span> Hubungi Bu Eci via WhatsApp
              </a>
              <form onSubmit={handleSendChat} className="flex gap-1.5">
                <input
                  type="text"
                  value={chatInput}
                  onChange={(e) => setChatInput(e.target.value)}
                  placeholder="Tanyakan jadwal, kapasitas, atau aturan..."
                  disabled={isBotTyping}
                  className="flex-1 text-xs border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-60"
                />
                <button
                  type="submit"
                  disabled={isBotTyping || !chatInput.trim()}
                  className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-3 py-2 rounded-lg text-xs font-semibold"
                >
                  Kirim
                </button>
              </form>
              <div className="text-[10px] text-slate-400 text-center">
                Percakapan publik tersimpan maksimal 30 hari.
              </div>
            </div>
          </div>
        )}
      </div>

      {/* 📄 FOOTER */}
      <footer className="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        <div className="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
          <p>© 2026 SMARTSPACE AI — Sistem Peminjaman Gedung &amp; Ruangan Cerdas</p>
          <div className="flex items-center gap-4 text-slate-400">
            <span>Privasi &amp; Aturan</span>
            <span>•</span>
            <span>Kontrak Data Bisnis</span>
            <span>•</span>
            <span>Zona Waktu Asia/Jakarta</span>
          </div>
        </div>
      </footer>
    </div>
  );
};

function KartuKonfirmasiLaporan({ usulan, status, daftarLokasi, onKonfirmasi, onBatal }) {
  const [lokasiId, setLokasiId] = useState(usulan.lokasi_id || '');
  const [ringkasan, setRingkasan] = useState(
    `[${(usulan.kategori || 'fasilitas').replace(/_/g, ' ').toUpperCase()}] ${String(usulan.deskripsi_kerusakan || '').slice(0, 80)}`
  );
  const [isi, setIsi] = useState(usulan.deskripsi_kerusakan || '');
  const [anonim, setAnonim] = useState(false);
  const [foto, setFoto] = useState(null);
  const [previewUrl, setPreviewUrl] = useState('');

  const handleFotoChange = (e) => {
    const f = e.target.files?.[0] || null;
    setFoto(f);
    if (f) {
      setPreviewUrl(URL.createObjectURL(f));
    } else {
      setPreviewUrl('');
    }
  };

  if (status === 'cancelled') {
    return (
      <div className="mt-1.5 bg-slate-100 border border-slate-200 rounded-xl px-2.5 py-1.5 text-[11px] text-slate-500">
        Pengiriman laporan dibatalkan.
      </div>
    );
  }

  if (status === 'sent') {
    return (
      <div className="mt-1.5 w-[92%] bg-emerald-50 border border-emerald-200 rounded-xl p-2.5 text-[11px] text-emerald-800 space-y-0.5">
        <p className="font-bold">✅ Laporan Terkirim &amp; Terintegrasi AI</p>
        <p className="text-emerald-700">Deskripsi fasilitas ruangan/gedung telah diperbarui dan diteruskan ke pengelola.</p>
      </div>
    );
  }

  return (
    <div className="mt-1.5 w-[92%] bg-amber-50/90 border border-amber-200 rounded-xl p-2.5 text-[11px] space-y-2 shadow-xs">
      <div className="flex items-center justify-between">
        <span className="font-bold text-amber-900">🛠️ Konfirmasi Laporan Fasilitas</span>
      </div>

      <div>
        <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Lokasi:</label>
        <select
          value={lokasiId}
          onChange={(e) => setLokasiId(e.target.value)}
          className="w-full bg-white border border-amber-200 rounded-lg px-2 py-1 text-[11px] text-slate-800 focus:outline-none focus:ring-1 focus:ring-amber-500"
        >
          <option value="">-- Umum / Belum spesifik --</option>
          {daftarLokasi.map((l) => (
            <option key={l.id} value={l.id}>
              {l.nama} ({l.jenis})
            </option>
          ))}
        </select>
      </div>

      <div>
        <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Ringkasan:</label>
        <input
          type="text"
          value={ringkasan}
          onChange={(e) => setRingkasan(e.target.value.slice(0, 200))}
          className="w-full bg-white border border-amber-200 rounded-lg px-2 py-1 text-[11px] text-slate-800 focus:outline-none focus:ring-1 focus:ring-amber-500"
        />
      </div>

      <div>
        <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Detail Keluhan:</label>
        <textarea
          rows={2}
          value={isi}
          onChange={(e) => setIsi(e.target.value.slice(0, 2000))}
          className="w-full bg-white border border-amber-200 rounded-lg px-2 py-1 text-[11px] text-slate-800 resize-none focus:outline-none focus:ring-1 focus:ring-amber-500"
        />
      </div>

      <div>
        <label className="block text-[10px] font-semibold text-slate-600 mb-0.5">Foto / Kamera (opsional):</label>
        <input
          type="file"
          accept="image/*"
          capture="environment"
          onChange={handleFotoChange}
          className="w-full text-[10px] text-slate-600 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200"
        />
        {previewUrl && (
          <img
            src={previewUrl}
            alt="Preview foto kerusakan"
            className="mt-1.5 h-20 w-full object-cover rounded-lg border border-amber-200"
          />
        )}
      </div>

      <label className="flex items-center gap-1.5 text-[10px] text-slate-600 cursor-pointer">
        <input
          type="checkbox"
          checked={anonim}
          onChange={(e) => setAnonim(e.target.checked)}
          className="rounded border-slate-300"
        />
        <span>Kirim sebagai anonim</span>
      </label>

      {status === 'error' && (
        <p className="text-[10px] text-rose-600 font-semibold">Gagal mengirim laporan. Periksa kembali isian Anda.</p>
      )}

      <div className="flex items-center gap-1.5 pt-0.5">
        <button
          type="button"
          disabled={status === 'sending' || !ringkasan.trim() || !isi.trim()}
          onClick={() => onKonfirmasi({ lokasi_id: lokasiId || null, ringkasan, isi, anonim, foto })}
          className="flex-1 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-bold py-1.5 px-2 rounded-lg text-[11px] transition"
        >
          {status === 'sending' ? 'Memproses AI...' : '✅ Konfirmasi Kirim'}
        </button>
        <button
          type="button"
          disabled={status === 'sending'}
          onClick={onBatal}
          className="px-2.5 py-1.5 bg-white hover:bg-slate-100 text-slate-600 border border-slate-200 rounded-lg text-[11px] font-medium transition"
        >
          Batal
        </button>
      </div>
    </div>
  );
}

export default Layout;
