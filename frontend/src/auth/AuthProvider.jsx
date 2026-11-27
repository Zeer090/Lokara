import React, { createContext, useContext, useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api/klien.js';

const AuthContext = createContext();

export const useAuth = () => useContext(AuthContext);

const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(() => {
    const saved = sessionStorage.getItem('auth_user');
    if (saved) {
      try { return JSON.parse(saved); } catch (e) {}
    }
    return null;
  });

  const navigate = useNavigate();

  useEffect(() => {
    const token = sessionStorage.getItem('accessToken');
    if (token && !user) {
      // Attempt to fetch current user profile from /auth/saya
      api.get('/auth/saya')
        .then((res) => {
          const p = res.data.pengguna || res.data;
          const u = {
            token,
            role: p.peran || 'pengguna',
            name: p.nama || 'Pengguna',
            email: p.email,
          };
          setUser(u);
          sessionStorage.setItem('auth_user', JSON.stringify(u));
        })
        .catch(() => {
          // Token invalid or expired
          sessionStorage.removeItem('accessToken');
          sessionStorage.removeItem('auth_user');
          setUser(null);
        });
    }
  }, []);

  const login = async (arg1, arg2) => {
    let email = '';
    let password = '';
    if (typeof arg1 === 'object' && arg1 !== null) {
      email = arg1.email || '';
      password = arg1.password || '';
    } else {
      email = arg1 || '';
      password = arg2 || '';
    }

    // Call real backend API: POST /auth/masuk
    const response = await api.post('/auth/masuk', { email, password });
    const { token, pengguna } = response.data;

    sessionStorage.setItem('accessToken', token);

    const loggedUser = {
      token,
      role: pengguna.peran || 'pengguna',
      name: pengguna.nama || email.split('@')[0],
      email: pengguna.email,
    };

    setUser(loggedUser);
    sessionStorage.setItem('auth_user', JSON.stringify(loggedUser));
    redirectToRole(loggedUser.role);
    return loggedUser;
  };

  const redirectToRole = (role) => {
    if (role === 'pengelola_gedung') {
      navigate('/pengelola');
    } else if (role === 'pengelola_sistem') {
      navigate('/sistem');
    } else if (role === 'akademik') {
      navigate('/akademik');
    } else {
      navigate('/app');
    }
  };

  const setRole = (newRole) => {
    if (!newRole) {
      logout();
      return;
    }
    const accounts = {
      pengguna: { email: 'pengguna@kampus.ac.id', pass: 'Pengguna2026!' },
      pengelola_gedung: { email: 'pengelola_gedung@kampus.ac.id', pass: 'Pengelola2026!' },
      pengelola_sistem: { email: 'sistem@kampus.ac.id', pass: 'Sistem2026!' },
      akademik: { email: 'akademik@kampus.ac.id', pass: 'Akademik2026!' },
    };
    if (accounts[newRole]) {
      login(accounts[newRole].email, accounts[newRole].pass);
    }
  };

  const logout = async () => {
    try {
      await api.post('/auth/keluar');
    } catch (e) {}
    sessionStorage.removeItem('accessToken');
    sessionStorage.removeItem('auth_user');
    setUser(null);
    navigate('/');
  };

  const value = { user, login, logout, setRole };
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export default AuthProvider;
