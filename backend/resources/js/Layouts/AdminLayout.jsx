import React from 'react';
import { Link, router, usePage } from '@inertiajs/react';

const navItems = [
  { href: '/admin/dashboard', label: 'Dashboard' },
  { href: '/admin/events',    label: 'Events' },
];

export default function AdminLayout({ children }) {
  const { auth } = usePage().props;
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
