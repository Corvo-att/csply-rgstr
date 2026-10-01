import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

// Blocks unauthenticated cosplayers.
export function ProtectedRoute({ children }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  return children;
}

// Blocks authenticated cosplayers (login / register pages).
export function GuestRoute({ children }) {
  const { user } = useAuth();
  if (user) return <Navigate to="/profile-page" replace />;
  return children;
}

// Blocks non-admins from /admin/* pages.
export function AdminRoute({ children }) {
  const { isAdmin } = useAuth();
  if (!isAdmin) return <Navigate to="/admin/login" replace />;
  return children;
}

// Blocks admins already logged in from /admin/login.
export function AdminGuestRoute({ children }) {
  const { isAdmin } = useAuth();
  if (isAdmin) return <Navigate to="/admin/dashboard" replace />;
  return children;
}
