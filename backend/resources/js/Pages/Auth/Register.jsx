import React from 'react';
import { Link, useForm } from '@inertiajs/react';

export default function Register() {
  const { data, setData, post, processing, errors } = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
  });

  function handleSubmit(e) {
    e.preventDefault();
    post('/register');
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
          Join Egypt's largest cosplay community.
          Register once, enter everything.
        </p>

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

          <h1 className="auth-title">Create Account</h1>
          <p className="auth-subtitle">Join EGYCON — it only takes a minute.</p>

          <form onSubmit={handleSubmit} noValidate>
            <div className="form-group">
              <label htmlFor="reg-name">Full Name <span className="required-mark">*</span></label>
              <input
                id="reg-name"
                type="text"
                name="name"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="Your display name"
              />
              {errors.name && <span className="form-error">{errors.name}</span>}
            </div>

            <div className="form-group">
              <label htmlFor="reg-email">Email <span className="required-mark">*</span></label>
              <input
                id="reg-email"
                type="email"
                name="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                placeholder="you@example.com"
              />
              {errors.email && <span className="form-error">{errors.email}</span>}
            </div>

            <div className="form-group">
              <label htmlFor="reg-password">Password <span className="required-mark">*</span></label>
              <input
                id="reg-password"
                type="password"
                name="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                placeholder="Minimum 6 characters"
              />
              {errors.password && <span className="form-error">{errors.password}</span>}
            </div>

            <div className="form-group">
              <label htmlFor="reg-confirm">Confirm Password <span className="required-mark">*</span></label>
              <input
                id="reg-confirm"
                type="password"
                name="password_confirmation"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                placeholder="Repeat your password"
              />
              {errors.password_confirmation && (
                <span className="form-error">{errors.password_confirmation}</span>
              )}
            </div>

            <button
              type="submit"
              className="btn btn-primary btn-full"
              style={{ marginTop: '0.5rem' }}
              disabled={processing}
            >
              {processing ? 'Creating account…' : 'Create Account'}
            </button>
          </form>

          <p className="text-sm text-muted mt-3" style={{ textAlign: 'center' }}>
            Already have an account?{' '}
            <Link href="/login">Sign in</Link>
          </p>
        </div>
      </div>

    </div>
  );
}
