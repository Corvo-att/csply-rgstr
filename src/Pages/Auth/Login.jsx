import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext.jsx';

export default function Login() {
  const { loginUser } = useAuth();
  const navigate = useNavigate();

  const [form, setForm]           = useState({ email: '', password: '' });
  const [error, setError]         = useState('');
  const [submitting, setSubmitting] = useState(false);

  function handleChange(e) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    setError('');
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!form.email || !form.password) { setError('Please fill in all fields.'); return; }
    setSubmitting(true);
    const result = await loginUser(form.email, form.password);
    setSubmitting(false);
    if (!result.ok) { setError(result.message); return; }
    navigate('/profile-page');
  }

  return (
    <div className="auth-split">

      {/* ── Left decorative panel (desktop only) ─────────────────────── */}
      <div className="auth-split-panel">
        <div className="auth-panel-brand">
          EGYI<span className="brand-accent">CON</span>
        </div>
        <div className="auth-panel-line" />
        <p className="auth-panel-tagline">
          Egypt's premier anime &amp; cosplay convention platform.
          Join thousands of cosplayers.
        </p>

        {/* decorative dot grid */}
        <div className="auth-panel-dots">
          {Array.from({ length: 25 }).map((_, i) => <span key={i} />)}
        </div>
      </div>

      {/* ── Right form side ───────────────────────────────────────────── */}
      <div className="auth-form-side">
        <div className="auth-form-inner fade-up">

          {/* Brand shown only on mobile */}
          <span className="auth-mobile-brand">
            EGYI<span className="brand-accent">CON</span>
          </span>

          <h1 className="auth-title">Welcome Back</h1>
          <p className="auth-subtitle">Sign in to your EGYCON account to continue.</p>

          {error && <div className="alert alert-error">{error}</div>}

          <form onSubmit={handleSubmit} noValidate>
            <div className="form-group">
              <label htmlFor="login-email">Email address</label>
              <input
                id="login-email"
                type="email"
                name="email"
                value={form.email}
                onChange={handleChange}
                placeholder="you@example.com"
                autoFocus
              />
            </div>

            <div className="form-group">
              <label htmlFor="login-password">Password</label>
              <input
                id="login-password"
                type="password"
                name="password"
                value={form.password}
                onChange={handleChange}
                placeholder="Your password"
              />
            </div>

            <button
              type="submit"
              className="btn btn-primary btn-full"
              style={{ marginTop: '0.5rem' }}
              disabled={submitting}
            >
              {submitting ? 'Signing in…' : 'Sign In'}
            </button>
          </form>

          <p className="text-sm text-muted mt-3" style={{ textAlign: 'center' }}>
            Don't have an account?{' '}
            <Link to="/register">Create one</Link>
          </p>

          <hr className="divider" />
          <p className="text-xs text-muted" style={{ textAlign: 'center' }}>
            <em>Demo: layla@example.com / password</em>
          </p>
        </div>
      </div>

    </div>
  );
}
