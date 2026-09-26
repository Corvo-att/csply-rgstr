import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

const navItems = [
  { to: '/admin/dashboard', label: 'Dashboard' },
  { to: '/admin/events',    label: 'Events' },
];

export default function AdminLayout({ children }) {
  const { adminUser, logout } = useAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate('/admin/login');
  }

  return (
    <div className="admin-layout">
      <aside className="admin-sidebar">
        <div className="admin-sidebar-brand">
          EGYI<span className="brand-accent">CON</span>
        </div>

        <span className="sidebar-section-label">Navigation</span>

        {navItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}
          >
            <span className="sidebar-dot" />
            {item.label}
          </NavLink>
        ))}

        <div className="sidebar-spacer" />

        <div style={{ padding: '0 0.25rem', borderTop: '1px solid var(--color-border)', paddingTop: '1rem' }}>
          <p className="text-sm text-muted mb-1" style={{ padding: '0 0.5rem' }}>{adminUser?.name}</p>
          <button className="btn btn-ghost btn-sm w-full" onClick={handleLogout}>
            Sign Out
          </button>
        </div>
      </aside>

      <main className="admin-main">
        {children}
      </main>
    </div>
  );
}
