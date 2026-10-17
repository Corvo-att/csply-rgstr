import React from 'react';
import { useForm } from '@inertiajs/react';

export default function AdminLogin() {
  const { data, setData, post, processing, errors } = useForm({
    email:    '',
    password: '',
  });

  function handleSubmit(e) {
    e.preventDefault();
    post('/admin/login');
  }

  return (
    <div className="auth-split">
      {/* Left panel */}
      <div className="auth-split-panel">
        <div className="auth-panel-brand">
          EGYI<span>CON</span>
        </div>
        <div className="auth-panel-line" />
        <p className="auth-panel-tagline">
          Staff administration portal.<br />
          Authorised personnel only.
        </p>
      </div>

      {/* Right form side */}
      <div className="auth-form-side">
        <div className="auth-form-inner">
          <h1 className="auth-title">Admin Portal</h1>
          <p className="auth-subtitle">EGYCON staff access — do not share credentials.</p>

          {errors.email && <div className="alert alert-error">{errors.email}</div>}

          <form onSubmit={handleSubmit} noValidate>
            <div className="form-group">
              <label htmlFor="admin-email">Admin Email</label>
              <input
                id="admin-email"
                type="email"
                name="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                placeholder="name@egycon.com"
                autoFocus
              />
            </div>
            <div className="form-group">
              <label htmlFor="admin-password">Password</label>
              <input
                id="admin-password"
                type="password"
                name="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                placeholder="Admin password"
              />
            </div>
            <button
              type="submit"
              className="btn btn-primary btn-full"
              style={{ marginTop: '0.25rem' }}
              disabled={processing}
            >
              {processing ? 'Signing in…' : 'Sign In'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
