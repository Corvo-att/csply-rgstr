import React from 'react';
import { Link, useForm } from '@inertiajs/react';

export default function Login() {
  const { data, setData, post, processing, errors } = useForm({
    email:    '',
    password: '',
  });

  function handleSubmit(e) {
    e.preventDefault();
    post('/login');
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

          {errors.email && <div className="alert alert-error">{errors.email}</div>}

          <form onSubmit={handleSubmit} noValidate>
            <div className="form-group">
              <label htmlFor="login-email">Email address</label>
              <input
                id="login-email"
                type="email"
                name="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
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
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                placeholder="Your password"
              />
            </div>

            <button
              type="submit"
              className="btn btn-primary btn-full"
              style={{ marginTop: '0.5rem' }}
              disabled={processing}
            >
              {processing ? 'Signing in…' : 'Sign In'}
            </button>
          </form>

          <p className="text-sm text-muted mt-3" style={{ textAlign: 'center' }}>
            Don't have an account?{' '}
            <Link href="/register">Create one</Link>
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
