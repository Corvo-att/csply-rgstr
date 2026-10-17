import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.jsx';
import DynamicField from '../../Components/FormRenderer/DynamicField.jsx';

const LAYOUT_ONLY = ['section', 'instructions'];

/**
 * Instant feedback only - the server re-checks every one of these rules
 * (App\Http\Requests\SubmitFormRequest), so nothing here is a security measure.
 */
function validateField(field, value) {
  const type    = field.field_type;
  const options = field.options || {};
  const empty   = value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0);

  if (field.is_required) {
    if (type === 'checkbox' || type === 'terms') {
      if (value !== '1') return 'You must tick this box to continue.';
    } else if (empty) {
      return Array.isArray(value) ? 'Please select at least one option.' : 'This field is required.';
    }
  }
  if (empty) return null;

  if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Please enter a valid email address.';
  if (type === 'url') {
    try { new URL(value); } catch { return 'Please enter a valid URL.'; }
  }
  if (type === 'number' || type === 'range') {
    const n = Number(value);
    if (options.min !== undefined && options.min !== '' && n < Number(options.min)) return `Minimum value is ${options.min}.`;
    if (options.max !== undefined && options.max !== '' && n > Number(options.max)) return `Maximum value is ${options.max}.`;
  }
  return null;
}

/** Inertia returns errors as { "values.12": "...", "values": "..." } - split them into per-field and general. */
function splitErrors(serverErrors) {
  const byField = {};
  const general = [];

  for (const [key, message] of Object.entries(serverErrors)) {
    const match = key.match(/^values\.(\d+)/);
    if (match) byField[match[1]] = byField[match[1]] || message;
    else general.push(message);
  }
  return { byField, general };
}

function Notice({ children, kind = 'info' }) {
  return (
    <AuthenticatedLayout>
      <div className="page-content" style={{ maxWidth: 680 }}>
        <div className={`alert alert-${kind}`}>{children}</div>
        <Link href="/profile-page"><button className="btn btn-ghost mt-2">Back to my profile</button></Link>
      </div>
    </AuthenticatedLayout>
  );
}

export default function Fill({ event, form, fields = [], cosplayer, closed_reason: closedReason, submission, upload_limit_mb: uploadLimitMb }) {
  const [values,      setValues]      = useState({});
  const [errors,      setErrors]      = useState({});
  const [submitting,  setSubmitting]  = useState(false);
  const [progress,    setProgress]    = useState(null);
  const [serverError, setServerError] = useState('');

  if (!cosplayer) {
    return (
      <Notice kind="error">
        You must complete your <Link href="/register-cosplay">cosplay registration</Link> before submitting event forms.
      </Notice>
    );
  }

  if (submission) {
    return (
      <Notice kind="success">
        You have already submitted <strong>{form.name}</strong> — your entry number is <strong>#{submission.entry_number}</strong>.
        Need to change something? Contact the organisers and mention your entry number.
      </Notice>
    );
  }

  if (closedReason) return <Notice kind="error">{closedReason}</Notice>;

  function handleChange(fieldId, val) {
    setValues((prev) => ({ ...prev, [fieldId]: val }));
    setErrors((prev) => ({ ...prev, [fieldId]: undefined }));
    setServerError('');
  }

  function handleSubmit(e) {
    e.preventDefault();

    const newErrors = {};
    for (const field of fields) {
      if (LAYOUT_ONLY.includes(field.field_type)) continue;
      const err = validateField(field, values[field.id]);
      if (err) newErrors[field.id] = err;
    }
    if (Object.keys(newErrors).length) {
      setErrors(newErrors);
      setServerError('Please fix the highlighted fields.');
      return;
    }

    // One request carries every file; warn before a huge upload that the server would refuse anyway.
    const totalMb = Object.values(values).filter((v) => v instanceof File).reduce((sum, f) => sum + f.size, 0) / 1048576;
    if (uploadLimitMb && totalMb > uploadLimitMb) {
      setServerError(`Your files total ${totalMb.toFixed(1)} MB but the server accepts at most ${uploadLimitMb} MB per submission. Please use smaller files.`);
      return;
    }

    setSubmitting(true);
    setServerError('');

    router.post(`/events/${event.id}/forms/${form.id}/submit`, { values }, {
      forceFormData: true,
      preserveScroll: true,
      onProgress: (p) => setProgress(p?.percentage ?? null),
      onError: (serverErrors) => {
        const { byField, general } = splitErrors(serverErrors);
        setErrors(byField);
        setServerError(general[0] || 'Some answers need fixing - see the highlighted fields.');
      },
      onFinish: () => { setSubmitting(false); setProgress(null); },
    });
  }

  return (
    <AuthenticatedLayout>
      <div className="page-content" style={{ maxWidth: 680 }}>

        <div className="breadcrumb">
          <Link href="/profile-page">Profile</Link>
          {event && <><span className="breadcrumb-sep">›</span><span>{event.name}</span></>}
          <span className="breadcrumb-sep">›</span>
          <span style={{ color: 'var(--color-text)' }}>{form.name}</span>
        </div>

        <div className="form-fill-header mb-3">
          <h1 style={{ fontFamily: 'var(--font-display)', fontSize: '1.3rem', fontWeight: 700, marginBottom: '0.3rem' }}>
            {form.name}
          </h1>
          {form.description && <p className="text-sm text-muted">{form.description}</p>}
          {form.closes_at && (
            <p className="text-sm text-muted mt-1">Closes {new Date(form.closes_at).toLocaleString()}</p>
          )}
        </div>

        {serverError && <div className="alert alert-error mb-3">{serverError}</div>}

        <form onSubmit={handleSubmit} className="form-fill-card" noValidate>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            {fields.map((field) => (
              <DynamicField
                key={field.id}
                field={{
                  ...field,
                  fieldType:  field.field_type,
                  fieldKey:   field.field_key,
                  isRequired: field.is_required,
                  helpText:   field.help_text,
                  options:    field.options || {},
                }}
                value={values[field.id]}
                onChange={(val) => handleChange(field.id, val)}
                error={errors[field.id]}
              />
            ))}
          </div>

          {submitting && progress !== null && (
            <div className="upload-progress" role="progressbar" aria-valuenow={Math.round(progress)} aria-valuemin={0} aria-valuemax={100}>
              <div className="upload-progress-bar" style={{ width: `${progress}%` }} />
              <span>{progress < 100 ? `Uploading… ${Math.round(progress)}%` : 'Processing your files…'}</span>
            </div>
          )}

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
