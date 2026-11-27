import React from 'react';
import { useNavigate } from 'react-router-dom';

export default function PerluPemetaan() {
  const navigate = useNavigate();

  const handleKeluar = () => {
    // Clear any stored auth tokens
    localStorage.clear();
    sessionStorage.clear();
    navigate('/masuk');
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="bg-white rounded-xl shadow-md w-full max-w-md p-10 text-center">
        {/* Icon */}
        <div className="flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full mx-auto mb-6">
          <span className="text-3xl">⚠️</span>
        </div>

        <h1 className="text-xl font-bold text-gray-800 mb-3">Akun Belum Dipetakan</h1>

        <p className="text-gray-500 text-sm leading-relaxed mb-6">
          Akun ini belum dipetakan ke role baru dalam sistem SMARTSPACE.
          <br />
          Silakan hubungi <span className="font-semibold text-gray-700">pemilik proyek</span> untuk
          mendapatkan akses yang sesuai.
        </p>

        <div className="bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs rounded-lg px-4 py-3 mb-6">
          Jika Anda merasa ini adalah kesalahan, harap cantumkan email akun Anda
          saat menghubungi administrator.
        </div>

        <button
          onClick={handleKeluar}
          className="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-2.5 rounded-lg transition"
        >
          Keluar
        </button>
      </div>
    </div>
  );
}
