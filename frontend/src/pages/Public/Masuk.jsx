import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/AuthProvider.jsx';

export default function Masuk() {
  const { login } = useAuth();
  const navigate = useNavigate();

  const [email, setEmail] = useState('pengguna@kampus.ac.id');
  const [password, setPassword] = useState('Pengguna2026!');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login(email, password);
    } catch (err) {
      const msg = err.response?.data?.galat || err.response?.data?.message || err.message || 'Email atau kata sandi salah.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const handleQuickLogin = (quickEmail, quickPass) => {
    setEmail(quickEmail);
    setPassword(quickPass);
    setError('');
    setLoading(true);
    login(quickEmail, quickPass).catch((err) => {
      const msg = err.response?.data?.galat || err.message || 'Gagal login';
      setError(msg);
      setLoading(false);
    });
  };

  return (
    <div className="min-h-[85vh] bg-slate-50 flex items-center justify-center px-4 py-12">
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 w-full max-w-md p-8">
        {/* Header Brand */}
        <div className="text-center mb-6">
          <div className="inline-flex w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 items-center justify-center text-white text-2xl font-black mb-3 shadow">
            S
          </div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Selamat Datang</h1>
          <p className="text-xs text-slate-500 mt-1">Masuk ke akun SMARTSPACE AI (Database Nyata)</p>
        </div>

        {error && (
          <div className="bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl px-4 py-3 mb-4">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Alamat Email
            </label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="nama@kampus.ac.id"
              className="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Kata Sandi
            </label>
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              className="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl transition text-sm shadow-sm disabled:opacity-60"
          >
            {loading ? 'Memvalidasi...' : 'Masuk Sekarang'}
          </button>
        </form>

        {/* Quick Official Seeded Logins */}
        <div className="mt-6 pt-6 border-t border-slate-100">
          <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2 text-center">
            Akun Database Resmi (1-Klik)
          </p>
          <div className="grid grid-cols-1 gap-1.5 text-xs">
            <button
              type="button"
              onClick={() => handleQuickLogin('pengguna@kampus.ac.id', 'Pengguna2026!')}
              className="text-left px-3 py-2 rounded-lg bg-slate-50 hover:bg-blue-50 hover:text-blue-700 text-slate-700 border border-slate-200/60 transition flex justify-between items-center"
            >
              <span>👤 <strong>Pengguna</strong> (Mahasiswa Contoh)</span>
              <span className="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-mono">1-Klik</span>
            </button>
            <button
              type="button"
              onClick={() => handleQuickLogin('pengelola_gedung@kampus.ac.id', 'Pengelola2026!')}
              className="text-left px-3 py-2 rounded-lg bg-slate-50 hover:bg-amber-50 hover:text-amber-800 text-slate-700 border border-slate-200/60 transition flex justify-between items-center"
            >
              <span>🏛️ <strong>Pengelola Gedung</strong> (Bu Eci)</span>
              <span className="text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-mono">1-Klik</span>
            </button>
            <button
              type="button"
              onClick={() => handleQuickLogin('sistem@kampus.ac.id', 'Sistem2026!')}
              className="text-left px-3 py-2 rounded-lg bg-slate-50 hover:bg-purple-50 hover:text-purple-800 text-slate-700 border border-slate-200/60 transition flex justify-between items-center"
            >
              <span>⚙️ <strong>Pengelola Sistem</strong> (Admin)</span>
              <span className="text-[10px] bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded font-mono">1-Klik</span>
            </button>
            <button
              type="button"
              onClick={() => handleQuickLogin('akademik@kampus.ac.id', 'Akademik2026!')}
              className="text-left px-3 py-2 rounded-lg bg-slate-50 hover:bg-teal-50 hover:text-teal-800 text-slate-700 border border-slate-200/60 transition flex justify-between items-center"
            >
              <span>📚 <strong>Bagian Akademik</strong> (BAAK)</span>
              <span className="text-[10px] bg-teal-100 text-teal-800 px-1.5 py-0.5 rounded font-mono">1-Klik</span>
            </button>
          </div>
        </div>

        <p className="mt-6 text-center text-xs text-slate-500">
          Belum punya akun?{' '}
          <Link to="/daftar" className="text-blue-600 font-semibold hover:underline">
            Daftar akun pengguna baru
          </Link>
        </p>
      </div>
    </div>
  );
}
