import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

export default function AuthenticatedLayout({ children }) {
  const { user, cosplayer, logout } = useAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate('/');
  }

  return (
    <div>
      <nav className="navbar">
        <Link to="/" className="navbar-brand">
          EGYI<span className="brand-accent">CON</span>
        </Link>
        <ul className="navbar-links">
          {!cosplayer && (
            <li><Link to="/register-cosplay">Complete Registration</Link></li>
          )}
          <li><Link to="/profile-page">My Profile</Link></li>
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
