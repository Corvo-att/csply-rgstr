import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext.jsx';

export default function Register() {
  const { registerUser } = useAuth();
  const navigate = useNavigate();

  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors]       = useState({});
  const [submitting, setSubmitting] = useState(false);

  function validate() {
    const e = {};
    if (!form.name.trim())                              e.name = 'Name is required.';
    if (!form.email.includes('@'))                      e.email = 'Valid email is required.';
    if (form.password.length < 6)                       e.password = 'Password must be at least 6 characters.';
    if (form.password !== form.password_confirmation)   e.password_confirmation = 'Passwords do not match.';
    return e;
  }

  function handleChange(e) {
    setForm((prev)   => ({ ...prev, [e.target.name]: e.target.value }));
    setErrors((prev) => ({ ...prev, [e.target.name]: undefined }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length) { setErrors(errs); return; }
    setSubmitting(true);
    const result = await registerUser(form.name, form.email, form.password);
    setSubmitting(false);
    if (!result.ok) { setErrors({ email: result.message }); return; }
    navigate('/register-cosplay');
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
                value={form.name}
                onChange={handleChange}
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
                value={form.email}
                onChange={handleChange}
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
                value={form.password}
                onChange={handleChange}
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
                value={form.password_confirmation}
                onChange={handleChange}
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
              disabled={submitting}
            >
              {submitting ? 'Creating account…' : 'Create Account'}
            </button>
          </form>

          <p className="text-sm text-muted mt-3" style={{ textAlign: 'center' }}>
            Already have an account?{' '}
            <Link to="/login">Sign in</Link>
          </p>
        </div>
      </div>

    </div>
  );
}
