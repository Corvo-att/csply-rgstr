import React, { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.jsx';
import DynamicField from '../../Components/FormRenderer/DynamicField.jsx';
import { useAuth } from '../../context/AuthContext.jsx';
import { api } from '../../services/api.js';

function validateField(field, value) {
  const fieldType = field.field_type || field.fieldType;
  const isRequired = field.is_required || field.isRequired;
  const options = field.options || {};

  if (isRequired) {
    if (value === undefined || value === null || value === '' || value === '0') {
      if (fieldType === 'checkbox' || fieldType === 'terms') return 'This field is required.';
      if (Array.isArray(value) && value.length === 0) return 'Please select at least one option.';
      if (!value) return 'This field is required.';
    }
    if (Array.isArray(value) && value.length === 0) return 'Please select at least one option.';
  }

  if (value && fieldType === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value))
    return 'Please enter a valid email address.';
  if (value && fieldType === 'url') {
    try { new URL(value); } catch { return 'Please enter a valid URL.'; }
  }
  if (value && (fieldType === 'number' || fieldType === 'range')) {
    const n = Number(value);
    if (options.min !== undefined && n < Number(options.min)) return `Minimum value is ${options.min}.`;
    if (options.max !== undefined && n > Number(options.max)) return `Maximum value is ${options.max}.`;
  }
  if (value && (fieldType === 'text' || fieldType === 'textarea' || fieldType === 'password')) {
    if (options.min_length && value.length < Number(options.min_length)) return `Minimum ${options.min_length} characters required.`;
    if (options.max_length && value.length > Number(options.max_length)) return `Maximum ${options.max_length} characters allowed.`;
  }

  return null;
}

export default function Fill() {
  const { eventId, formId } = useParams();
  const { user, cosplayer } = useAuth();
  const navigate = useNavigate();

  const [form,    setForm]    = useState(null);
  const [event,   setEvent]   = useState(null);
  const [fields,  setFields]  = useState([]);
  const [loading, setLoading] = useState(true);

  const [values,      setValues]      = useState({});
  const [errors,      setErrors]      = useState({});
  const [submitted,   setSubmitted]   = useState(false);
  const [submitting,  setSubmitting]  = useState(false);
  const [serverError, setServerError] = useState('');

  useEffect(() => {
    async function load() {
      try {
        const data = await api.getForm(formId);
        setForm(data.form);
        setEvent(data.event);
        setFields(data.fields || []);
      } catch (err) {
        console.error('Failed to load form', err);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [formId]);

  /* ── Loading ─────────────────────────────────────────────────────── */
  if (loading) {
    return (
      <AuthenticatedLayout>
        <div className="page-content" style={{ maxWidth: 680 }}>
          <div className="card" style={{ textAlign: 'center', padding: '3rem' }}>
            <div className="loading-spinner" />
            <p className="text-muted text-sm mt-2">Loading form details…</p>
          </div>
        </div>
      </AuthenticatedLayout>
    );
  }

  /* ── Not found ───────────────────────────────────────────────────── */
  if (!form) {
    return (
      <AuthenticatedLayout>
        <div className="page-content empty-state" style={{ maxWidth: 680 }}>
          <div className="empty-state-icon">!</div>
          <h3>Form not found</h3>
          <p>This form doesn't exist or has been removed.</p>
          <Link to="/profile-page">
            <button className="btn btn-ghost mt-3">Back to Profile</button>
          </Link>
        </div>
      </AuthenticatedLayout>
    );
  }

  /* ── Cosplay required ────────────────────────────────────────────── */
  if (!cosplayer) {
    return (
      <AuthenticatedLayout>
        <div className="page-content" style={{ maxWidth: 680 }}>
          <div className="alert alert-error">
            You must complete your{' '}
            <Link to="/register-cosplay">cosplay registration</Link>{' '}
            before submitting event forms.
          </div>
        </div>
      </AuthenticatedLayout>
    );
  }

  /* ── Success ─────────────────────────────────────────────────────── */
  if (submitted) {
    return (
      <AuthenticatedLayout>
        <div className="page-content fade-up" style={{ maxWidth: 600 }}>
          <div className="card" style={{ textAlign: 'center', padding: '3rem 2rem' }}>
            <div className="success-icon">✓</div>
            <h2 style={{ marginBottom: '0.5rem' }}>Submission Received!</h2>
            <p className="text-muted">
              Your response to <strong>{form.name}</strong> has been recorded.
            </p>
            <Link to="/profile-page">
              <button className="btn btn-primary mt-3">Back to Profile</button>
            </Link>
          </div>
        </div>
      </AuthenticatedLayout>
    );
  }

  /* ── Handlers ────────────────────────────────────────────────────── */
  function handleChange(fieldKey, val) {
    setValues((prev)  => ({ ...prev, [fieldKey]: val }));
    setErrors((prev)  => ({ ...prev, [fieldKey]: undefined }));
    setServerError('');
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const newErrors = {};
    for (const field of fields) {
      const type = field.field_type || field.fieldType;
      if (type === 'section' || type === 'instructions') continue;
      const key = field.field_key || field.fieldKey || field.id;
      const err = validateField(field, values[key]);
      if (err) newErrors[key] = err;
    }
    if (Object.keys(newErrors).length) { setErrors(newErrors); return; }

    setSubmitting(true);
    setServerError('');
    try {
      await api.submitForm(formId, { user_id: user?.id, values });
      setSubmitting(false);
      setSubmitted(true);
    } catch (err) {
      setSubmitting(false);
      setServerError(err.message || 'Submission failed. Please try again.');
    }
  }

  /* ── Form ────────────────────────────────────────────────────────── */
  return (
    <AuthenticatedLayout>
      <div className="page-content" style={{ maxWidth: 680 }}>

        {/* Breadcrumb */}
        <div className="breadcrumb">
          <Link to="/profile-page">Profile</Link>
          {event && <><span className="breadcrumb-sep">›</span><span>{event.name}</span></>}
          <span className="breadcrumb-sep">›</span>
          <span style={{ color: 'var(--color-text)' }}>{form.name}</span>
        </div>

        {/* Form header */}
        <div className="form-fill-header mb-3">
          <h1 style={{ fontFamily: 'var(--font-display)', fontSize: '1.3rem', fontWeight: 700, marginBottom: '0.3rem' }}>
            {form.name}
          </h1>
          {form.description && (
            <p className="text-sm text-muted">{form.description}</p>
          )}
        </div>

        {serverError && (
          <div className="alert alert-error mb-3">{serverError}</div>
        )}

        {/* Fields */}
        <form onSubmit={handleSubmit} className="form-fill-card">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            {fields.map((field) => {
              const key = field.field_key || field.fieldKey || field.id;
              const mappedField = {
                ...field,
                fieldType:  field.field_type  || field.fieldType,
                fieldKey:   key,
                isRequired: field.is_required || field.isRequired,
                sortOrder:  field.sort_order  || field.sortOrder,
                helpText:   field.help_text   || field.helpText,
                options:    field.options     || {},
              };
              return (
                <DynamicField
                  key={field.id}
                  field={mappedField}
                  value={values[key]}
                  onChange={(val) => handleChange(key, val)}
                  error={errors[key]}
                />
              );
            })}
          </div>

          <div className="form-actions">
            <Link to="/profile-page">
              <button type="button" className="btn btn-ghost">Cancel</button>
            </Link>
            <button type="submit" className="btn btn-primary" disabled={submitting}>
              {submitting ? 'Submitting…' : 'Submit Response'}
            </button>
          </div>
        </form>

      </div>
    </AuthenticatedLayout>
  );
}
