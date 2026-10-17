import React from 'react';
import { Link, router, usePage } from '@inertiajs/react';

const navItems = [
  { href: '/admin/dashboard', label: 'Dashboard' },
  { href: '/admin/events',    label: 'Events' },
];

export default function AdminLayout({ children }) {
  const { auth, flash, googleConnection } = usePage().props;
  const adminUser = auth?.adminUser;

  function handleLogout() {
    router.post('/admin/logout');
  }

  return (
    <div className="admin-layout">
      <aside className="admin-sidebar">
        <div className="admin-sidebar-brand">
          EGYI<span className="brand-accent">CON</span>
        </div>

        <span className="sidebar-section-label">Navigation</span>

        {navItems.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            className="sidebar-link"
          >
            <span className="sidebar-dot" />
            {item.label}
          </Link>
        ))}

        {/* Google Integration Status */}
        <div style={{ marginTop: '1.5rem', padding: '0.75rem', background: 'var(--color-surface-2)', borderRadius: 'var(--radius-sm)', border: '1px solid var(--color-border)' }}>
          <div className="flex items-center justify-between mb-1">
            <span style={{ fontSize: '0.72rem', textTransform: 'uppercase', letterSpacing: '0.05em', color: 'var(--color-text-muted)', fontWeight: 600 }}>
              Google Sheets
            </span>
            <span style={{
              display: 'inline-block',
              width: '8px',
              height: '8px',
              borderRadius: '50%',
              backgroundColor: googleConnection ? 'var(--color-success)' : 'var(--color-text-dim)',
            }} />
          </div>
          {googleConnection ? (
            <div>
              <p style={{ fontSize: '0.78rem', color: 'var(--color-text)', wordBreak: 'break-all', marginBottom: '0.5rem' }}>
                {googleConnection.email}
              </p>
              <button
                className="btn btn-ghost btn-sm w-full"
                style={{ fontSize: '0.72rem', padding: '0.2rem 0.5rem', color: 'var(--color-danger)' }}
                onClick={() => {
                  if (confirm('Disconnect Google account?')) {
                    router.delete(`/admin/google/connections/${googleConnection.id}`);
                  }
                }}
              >
                Disconnect
              </button>
            </div>
          ) : (
            <div>
              <p style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)', marginBottom: '0.5rem' }}>
                Not connected
              </p>
              <a
                href="/admin/google/connect"
                className="btn btn-primary btn-sm w-full"
                style={{ fontSize: '0.75rem', padding: '0.35rem 0.5rem', textDecoration: 'none', textAlign: 'center', display: 'block' }}
              >
                Connect Google
              </a>
            </div>
          )}
        </div>

        <div className="sidebar-spacer" />

        <div style={{ padding: '0 0.25rem', borderTop: '1px solid var(--color-border)', paddingTop: '1rem' }}>
          <p className="text-sm text-muted mb-1" style={{ padding: '0 0.5rem' }}>{adminUser?.name}</p>
          <button className="btn btn-ghost btn-sm w-full" onClick={handleLogout}>
            Sign Out
          </button>
        </div>
      </aside>

      <main className="admin-main">
        {flash?.success && (
          <div style={{
            margin: '1rem 1.5rem 0',
            padding: '0.75rem 1rem',
            borderRadius: 'var(--radius-sm)',
            backgroundColor: 'rgba(52, 201, 138, 0.15)',
            border: '1px solid rgba(52, 201, 138, 0.4)',
            color: 'var(--color-success)',
            fontSize: '0.88rem'
          }}>
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div style={{
            margin: '1rem 1.5rem 0',
            padding: '0.75rem 1rem',
            borderRadius: 'var(--radius-sm)',
            backgroundColor: 'rgba(224, 90, 67, 0.15)',
            border: '1px solid rgba(224, 90, 67, 0.4)',
            color: 'var(--color-danger)',
            fontSize: '0.88rem'
          }}>
            {flash.error}
          </div>
        )}
        {children}
      </main>
    </div>
  );
}
