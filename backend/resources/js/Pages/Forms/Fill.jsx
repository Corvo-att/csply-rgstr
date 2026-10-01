import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.jsx';
import DynamicField from '../../Components/FormRenderer/DynamicField.jsx';

function validateField(field, value) {
  const isRequired = field.is_required;
  const fieldType  = field.field_type;
  const options    = field.options || {};

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

  return null;
}

export default function Fill({ event, form, fields = [], cosplayer }) {
  const [values,      setValues]     = useState({});
  const [errors,      setErrors]     = useState({});
  const [submitted,   setSubmitted]  = useState(false);
  const [submitting,  setSubmitting] = useState(false);
  const [serverError, setServerError]= useState('');

  /* ── Cosplay required ────────────────────────────────────────────── */
  if (!cosplayer) {
    return (
      <AuthenticatedLayout>
        <div className="page-content" style={{ maxWidth: 680 }}>
          <div className="alert alert-error">
            You must complete your{' '}
            <Link href="/register-cosplay">cosplay registration</Link>{' '}
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
            <Link href="/profile-page">
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

  function handleSubmit(e) {
    e.preventDefault();
    const newErrors = {};
    for (const field of fields) {
      const type = field.field_type;
      if (type === 'section' || type === 'instructions') continue;
      const key = field.id;
      const err = validateField(field, values[key]);
      if (err) newErrors[key] = err;
    }
    if (Object.keys(newErrors).length) { setErrors(newErrors); return; }

    setSubmitting(true);
    setServerError('');

    router.post(`/events/${event.id}/forms/${form.id}/submit`, { values }, {
      onSuccess: () => setSubmitted(true),
      onError:   (err) => {
        setSubmitting(false);
        setServerError(Object.values(err)[0] || 'Submission failed. Please try again.');
      },
      onFinish: () => setSubmitting(false),
    });
  }

  /* ── Form ────────────────────────────────────────────────────────── */
  return (
    <AuthenticatedLayout>
      <div className="page-content" style={{ maxWidth: 680 }}>

        {/* Breadcrumb */}
        <div className="breadcrumb">
          <Link href="/profile-page">Profile</Link>
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
              const key = field.id;
              const mappedField = {
                ...field,
                fieldType:  field.field_type,
                fieldKey:   field.field_key,
                isRequired: field.is_required,
                sortOrder:  field.sort_order,
                helpText:   field.help_text,
                options:    field.options || {},
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
            <Link href="/profile-page">
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
