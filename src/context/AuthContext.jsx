import React, { createContext, useContext, useState, useEffect } from 'react';
import { api } from '../services/api.js';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    try {
      const saved = localStorage.getItem('egycon_user');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  const [cosplayer, setCosplayer] = useState(() => {
    try {
      const saved = localStorage.getItem('egycon_cosplayer');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  const [isAdmin, setIsAdmin] = useState(() => {
    return localStorage.getItem('egycon_isAdmin') === 'true';
  });

  const [adminUser, setAdminUser] = useState(() => {
    try {
      const saved = localStorage.getItem('egycon_adminUser');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  useEffect(() => {
    if (user) {
      localStorage.setItem('egycon_user', JSON.stringify(user));
    } else {
      localStorage.removeItem('egycon_user');
    }
  }, [user]);

  useEffect(() => {
    if (cosplayer) {
      localStorage.setItem('egycon_cosplayer', JSON.stringify(cosplayer));
    } else {
      localStorage.removeItem('egycon_cosplayer');
    }
  }, [cosplayer]);

  useEffect(() => {
    localStorage.setItem('egycon_isAdmin', isAdmin ? 'true' : 'false');
    if (adminUser) {
      localStorage.setItem('egycon_adminUser', JSON.stringify(adminUser));
    } else {
      localStorage.removeItem('egycon_adminUser');
    }
  }, [isAdmin, adminUser]);

  // ── Cosplayer login ────────────────────────────────────────────────────────
  async function loginUser(email, password) {
    try {
      const res = await api.login(email, password);
      if (!res.ok) return { ok: false, message: res.message || 'Invalid credentials' };
      setUser(res.user);
      setCosplayer(res.cosplayer);
      setIsAdmin(false);
      setAdminUser(null);
      return { ok: true };
    } catch (err) {
      return { ok: false, message: err.message || 'Login failed.' };
    }
  }

  // ── Admin login ────────────────────────────────────────────────────────────
  async function loginAdmin(email, password) {
    try {
      const res = await api.adminLogin(email, password);
      if (!res.ok) return { ok: false, message: res.message || 'Invalid credentials' };
      setAdminUser(res.adminUser);
      setIsAdmin(true);
      setUser(null);
      setCosplayer(null);
      return { ok: true };
    } catch (err) {
      return { ok: false, message: err.message || 'Admin login failed.' };
    }
  }

  // ── Register new user ──────────────────────────────────────────────────────
  async function registerUser(name, email, password) {
    try {
      const res = await api.register(name, email, password);
      if (!res.ok) return { ok: false, message: res.message || 'Registration failed.' };
      setUser(res.user);
      setCosplayer(null);
      setIsAdmin(false);
      return { ok: true };
    } catch (err) {
      return { ok: false, message: err.message || 'Registration failed.' };
    }
  }

  // ── Save cosplayer profile ─────────────────────────────────────────────────
  async function saveCosplayer(data) {
    try {
      if (!user) return { ok: false, message: 'User not authenticated' };
      const res = await api.saveCosplayerProfile({
        user_id: user.id,
        ...data,
      });
      if (!res.ok) return { ok: false, message: res.message || 'Failed to save profile' };
      setCosplayer(res.cosplayer);
      return { ok: true };
    } catch (err) {
      return { ok: false, message: err.message || 'Failed to save profile' };
    }
  }

  // ── Logout ─────────────────────────────────────────────────────────────────
  function logout() {
    setUser(null);
    setCosplayer(null);
    setIsAdmin(false);
    setAdminUser(null);
    localStorage.removeItem('egycon_user');
    localStorage.removeItem('egycon_cosplayer');
    localStorage.removeItem('egycon_isAdmin');
    localStorage.removeItem('egycon_adminUser');
  }

  return (
    <AuthContext.Provider
      value={{ user, cosplayer, isAdmin, adminUser, loginUser, loginAdmin, registerUser, saveCosplayer, logout }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
