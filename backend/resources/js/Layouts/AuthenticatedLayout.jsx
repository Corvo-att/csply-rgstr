import React from 'react';
import { Link, router, usePage } from '@inertiajs/react';

export default function AuthenticatedLayout({ children }) {
  const { auth } = usePage().props;
  const user     = auth?.user;
  const cosplayer= auth?.cosplayer;

  function handleLogout() {
    router.post('/logout');
  }

  return (
    <div>
      <nav className="navbar">
        <Link href="/" className="navbar-brand">
          EGYI<span className="brand-accent">CON</span>
        </Link>
        <ul className="navbar-links">
          {!cosplayer && (
            <li><Link href="/register-cosplay">Complete Registration</Link></li>
          )}
          <li><Link href="/profile-page">My Profile</Link></li>
          <li className="navbar-user">
            <span>{user?.name}</span>
            <button className="btn btn-ghost btn-sm" onClick={handleLogout}>
              Sign Out
            </button>
          </li>
        </ul>
      </nav>
      <main>{children}</main>
    </div>
  );
}
