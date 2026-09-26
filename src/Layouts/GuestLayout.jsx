import React from 'react';
import { Link } from 'react-router-dom';

export default function GuestLayout({ children }) {
  return (
    <div>
      <nav className="navbar">
        <Link to="/" className="navbar-brand">
          EGYI<span className="brand-accent">CON</span>
        </Link>
        <ul className="navbar-links">
          <li><Link to="/login">Login</Link></li>
          <li>
            <Link to="/register">
              <button className="btn btn-primary btn-sm">Register</button>
            </Link>
          </li>
        </ul>
      </nav>
      <main>{children}</main>
    </div>
  );
}
