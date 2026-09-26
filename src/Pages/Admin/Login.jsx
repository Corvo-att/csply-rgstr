import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext.jsx';

export default function AdminLogin() {
  const { loginAdmin } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  function handleChange(e) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    setError('');
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!form.email || !form.password) { setError('Please fill in all fields.'); return; }
    setSubmitting(true);
    const result = await loginAdmin(form.email, form.password);
    setSubmitting(false);
    if (!result.ok) { setError(result.message); return; }
    navigate('/admin/dashboard');
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

          {error && <div className="alert alert-error">{error}</div>}

          <form onSubmit={handleSubmit} noValidate>
            <div className="form-group">
              <label htmlFor="admin-email">Admin Email</label>
              <input
                id="admin-email"
                type="email"
                name="email"
                value={form.email}
                onChange={handleChange}
                placeholder="admin@egycon.com"
                autoFocus
              />
            </div>
            <div className="form-group">
              <label htmlFor="admin-password">Password</label>
              <input
                id="admin-password"
                type="password"
                name="password"
                value={form.password}
                onChange={handleChange}
                placeholder="Admin password"
              />
            </div>
            <button
              type="submit"
              className="btn btn-primary btn-full"
              style={{ marginTop: '0.25rem' }}
              disabled={submitting}
            >
              {submitting ? 'Signing in…' : 'Sign In'}
            </button>
          </form>

          <hr className="divider" />
          <p className="text-sm text-muted" style={{ textAlign: 'center' }}>
            <em>Demo: admin@egycon.com / admin123</em>
          </p>
        </div>
      </div>
    </div>
  );
}
