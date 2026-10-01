import React from 'react';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
  return (
    <div>
      <nav className="navbar">
        <Link href="/" className="navbar-brand">
          EGYI<span className="brand-accent">CON</span>
        </Link>
        <ul className="navbar-links">
          <li><Link href="/login">Login</Link></li>
          <li>
            <Link href="/register">
              <button className="btn btn-primary btn-sm">Register</button>
            </Link>
          </li>
        </ul>
      </nav>
      <main>{children}</main>
    </div>
  );
}
