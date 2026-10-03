import React, { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

/* ── Helpers ─────────────────────────────────────────────────────────────── */
function isUrl(val) {
  try { return Boolean(new URL(String(val))); } catch { return false; }
}
function isImage(url) {
  return /\.(png|jpe?g|gif|webp|bmp|avif)(\?.*)?$/i.test(url);
}
function isVideo(url) {
  return /\.(mp4|webm|ogg|mov|avi|mkv|m4v)(\?.*)?$/i.test(url);
}

const LAYOUT_ONLY = ['section', 'instructions', 'hidden'];
const MEDIA_TYPES = ['file', 'image', 'video'];
const EXP_COLOR = {
  beginner:     '#6c757d',
  intermediate: '#0d6efd',
  advanced:     '#198754',
  professional: '#6f42c1',
};

/* ── Lightbox ─────────────────────────────────────────────────────────────── */
function Lightbox({ src, onClose }) {
  return (
    <div
      onClick={onClose}
      style={{
        position: 'fixed', inset: 0,
        background: 'rgba(0,0,0,0.85)',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        zIndex: 9999, cursor: 'zoom-out',
      }}
    >
      <img
        src={src}
        alt="preview"
        style={{ maxWidth: '90vw', maxHeight: '90vh', borderRadius: 8, boxShadow: '0 0 40px rgba(0,0,0,0.8)' }}
        onClick={(e) => e.stopPropagation()}
      />
      <button
        onClick={onClose}
        style={{
          position: 'fixed', top: 16, right: 20,
          background: 'none', border: 'none',
          color: '#fff', fontSize: '2rem', cursor: 'pointer', lineHeight: 1,
        }}
        aria-label="Close"
      >×</button>
    </div>
  );
}

/* ── Value renderer ──────────────────────────────────────────────────────── */
function ValueCell({ field, value, processing, onLightbox }) {
  const ft = field.field_type;

  if (LAYOUT_ONLY.includes(ft)) return null;

  if (!value && value !== '0' && value !== 0) {
    return <span className="text-muted" style={{ fontStyle: 'italic' }}>— no response —</span>;
  }

  // Boolean
  if (ft === 'checkbox' || ft === 'toggle' || ft === 'terms') {
    return (
      <span className={`badge ${value === '1' ? 'badge-active' : 'badge-draft'}`}>
        {value === '1' ? 'Yes ✓' : 'No ✗'}
      </span>
    );
  }

  // Media: image
  if ((ft === 'image' || ft === 'file') && isUrl(value) && isImage(value)) {
    return (
      <div>
        <img
          src={value}
          alt={field.label}
          onClick={() => onLightbox(value)}
          style={{
            maxWidth: 280, maxHeight: 200, objectFit: 'contain',
            borderRadius: 8, border: '1px solid var(--color-border)',
            cursor: 'zoom-in', display: 'block', marginBottom: '0.4rem',
          }}
        />
        <a href={value} target="_blank" rel="noreferrer" className="text-sm" style={{ color: 'var(--color-primary)' }}>
          Open full size ↗
        </a>
      </div>
    );
  }

  // Media: video
  if ((ft === 'video' || ft === 'file') && isUrl(value) && isVideo(value)) {
    return (
      <div>
        <video
          src={value}
          controls
          style={{
            maxWidth: 360, maxHeight: 240, borderRadius: 8,
            border: '1px solid var(--color-border)', background: '#000',
            display: 'block', marginBottom: '0.4rem',
          }}
        />
        <div className="flex gap-1 items-center" style={{ flexWrap: 'wrap' }}>
          <a href={value} target="_blank" rel="noreferrer" className="text-sm" style={{ color: 'var(--color-primary)' }}>
            Download ↗
          </a>
          {processing && (
            <span className={`badge ${processing.status === 'ready' ? 'badge-approved' : processing.status === 'failed' ? 'badge-rejected' : 'badge-pending'}`}>
              {processing.status === 'ready' ? 'show-ready' : processing.status}
            </span>
          )}
          {processing?.meta?.duration && (
            <span className="text-xs text-muted">
              {Math.round(processing.meta.duration)}s{processing.meta.height ? ` · ${processing.meta.height}p` : ''}
              {processing.meta.trimmed ? ' · trimmed' : ''}
            </span>
          )}
          {processing?.status === 'failed' && processing.meta?.error && (
            <span className="text-xs text-muted">Original kept. {processing.meta.error}</span>
          )}
        </div>
      </div>
    );
  }

  // Generic file link
  if (MEDIA_TYPES.includes(ft) && isUrl(value)) {
    return (
      <a href={value} target="_blank" rel="noreferrer" style={{ color: 'var(--color-primary)' }}>
        📎 {value.split('/').pop()}
      </a>
    );
  }

  // Rating (star display)
  if (ft === 'rating') {
    const max = field.options?.max_stars || 5;
    return (
      <span>
        {Array.from({ length: max }, (_, i) => (
          <span key={i} style={{ color: i < Number(value) ? '#f0a500' : 'var(--color-border)', fontSize: '1.1rem' }}>★</span>
        ))}
        <span className="text-muted text-sm" style={{ marginLeft: '0.4rem' }}>{value}/{max}</span>
      </span>
    );
  }

  // Long text
  if (ft === 'textarea' || (typeof value === 'string' && value.length > 100)) {
    return (
      <p style={{ whiteSpace: 'pre-wrap', margin: 0, fontSize: '0.9rem', lineHeight: 1.6 }}>
        {value}
      </p>
    );
  }

  return <span style={{ fontWeight: 500 }}>{String(value)}</span>;
}

/* ── Page ────────────────────────────────────────────────────────────────── */
export default function SubmissionDetail({ form, fields = [], submission }) {
  const { auth, flash } = usePage().props;
  const canManage = auth?.adminUser?.can_manage;

  const [lightboxSrc, setLightboxSrc] = useState(null);
  const [notes, setNotes] = useState(submission.admin_notes || '');

  const { cosplayer, values = {}, processing = {}, submitted_at, id } = submission;

  function review(status) {
    router.patch(`/admin/forms/${form.id}/submissions/${id}`, { status, admin_notes: notes }, { preserveScroll: true });
  }

  function saveNotes() {
    router.patch(`/admin/forms/${form.id}/submissions/${id}`, { admin_notes: notes }, { preserveScroll: true });
  }

  function remove() {
    if (!window.confirm(`Delete entry #${submission.entry_number}? Its uploaded files are removed too, and ${cosplayer.name} will be able to submit again.`)) return;
    router.delete(`/admin/forms/${form.id}/submissions/${id}`);
  }
  const displayFields = fields.filter((f) => !LAYOUT_ONLY.includes(f.field_type));

  return (
    <AdminLayout>
      {lightboxSrc && <Lightbox src={lightboxSrc} onClose={() => setLightboxSrc(null)} />}

      <div className="page-content" style={{ maxWidth: 860 }}>

        {/* ── Breadcrumb ─────────────────────────────────────────────── */}
        <div className="breadcrumb" style={{ marginBottom: '1.5rem' }}>
          <Link href="/admin/events">Events</Link>
          <span className="breadcrumb-sep">›</span>
          <Link href={`/admin/forms/${form.id}/submissions`}>{form.name} — Submissions</Link>
          <span className="breadcrumb-sep">›</span>
          <span style={{ color: 'var(--color-text)' }}>#{id}</span>
        </div>

        {/* ── Header ─────────────────────────────────────────────────── */}
        <div className="page-header flex justify-between items-center" style={{ marginBottom: '1.5rem' }}>
          <div>
            <h1 className="page-title" style={{ fontSize: '1.4rem' }}>
              Submission #{id}
            </h1>
            <p className="text-sm text-muted">
              {form.name} &mdash; submitted {submitted_at ? new Date(submitted_at).toLocaleString() : '—'}
            </p>
          </div>
          <Link href={`/admin/forms/${form.id}/submissions`}>
            <button className="btn btn-ghost btn-sm">← Back to List</button>
          </Link>
        </div>

        {flash?.success && <div className="alert alert-success mb-3">{flash.success}</div>}

        {/* ── Review ─────────────────────────────────────────────────── */}
        <div className="card mb-3" style={{ padding: '1.25rem 1.5rem' }}>
          <div className="card-header" style={{ marginBottom: '0.75rem' }}>
            <span className="card-title">Entry #{submission.entry_number}</span>
            <span className={`badge badge-${submission.status}`}>{submission.status}</span>
          </div>

          {canManage ? (
            <>
              <div className="form-group">
                <label htmlFor="admin-notes">Internal notes (never shown to the cosplayer)</label>
                <textarea id="admin-notes" rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={5000} />
              </div>
              <div className="flex gap-1" style={{ flexWrap: 'wrap' }}>
                <button className="btn btn-primary btn-sm" onClick={() => review('approved')} disabled={submission.status === 'approved'}>Approve</button>
                <button className="btn btn-ghost btn-sm" onClick={() => review('pending')} disabled={submission.status === 'pending'}>Mark pending</button>
                <button className="btn btn-ghost btn-sm" onClick={() => review('rejected')} disabled={submission.status === 'rejected'}>Reject</button>
                <button className="btn btn-ghost btn-sm" onClick={saveNotes}>Save notes</button>
                <button className="btn btn-danger btn-sm" style={{ marginLeft: 'auto' }} onClick={remove}>Delete entry</button>
              </div>
            </>
          ) : (
            submission.admin_notes && <p className="text-sm" style={{ whiteSpace: 'pre-wrap' }}>{submission.admin_notes}</p>
          )}
        </div>

        {/* ── Cosplayer Profile Card ──────────────────────────────────── */}
        <div className="card mb-3" style={{ padding: '1.25rem 1.5rem' }}>
          <div className="card-header" style={{ marginBottom: '1rem' }}>
            <span className="card-title">Cosplayer Profile</span>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem 2rem' }}>
            <div className="profile-field">
              <span className="profile-field-label">Full Name</span>
              <span className="profile-field-value">{cosplayer.name}</span>
            </div>
            <div className="profile-field">
              <span className="profile-field-label">Email</span>
              <span className="profile-field-value">
                <a href={`mailto:${cosplayer.email}`} style={{ color: 'var(--color-primary)' }}>
                  {cosplayer.email}
                </a>
              </span>
            </div>
            <div className="profile-field">
              <span className="profile-field-label">Character</span>
              <span className="profile-field-value" style={{ fontWeight: 600 }}>{cosplayer.character_name}</span>
            </div>
            <div className="profile-field">
              <span className="profile-field-label">Series / Source</span>
              <span className="profile-field-value">{cosplayer.series}</span>
            </div>
            <div className="profile-field">
              <span className="profile-field-label">Experience</span>
              <span className="profile-field-value">
                <span
                  className="badge"
                  style={{
                    background: `${EXP_COLOR[cosplayer.experience_level] || '#6c757d'}22`,
                    color: EXP_COLOR[cosplayer.experience_level] || '#6c757d',
                    border: `1px solid ${EXP_COLOR[cosplayer.experience_level] || '#6c757d'}44`,
                    textTransform: 'capitalize',
                  }}
                >
                  {cosplayer.experience_level}
                </span>
              </span>
            </div>
            {cosplayer.bio && (
              <div className="profile-field" style={{ gridColumn: '1 / -1' }}>
                <span className="profile-field-label">Bio</span>
                <span className="profile-field-value text-sm">{cosplayer.bio}</span>
              </div>
            )}
          </div>
        </div>

        {/* ── Form Responses ─────────────────────────────────────────── */}
        <div className="card" style={{ padding: '1.25rem 1.5rem' }}>
          <div className="card-header" style={{ marginBottom: '1.25rem' }}>
            <span className="card-title">Form Responses</span>
            <span className="text-sm text-muted">{displayFields.length} field{displayFields.length !== 1 ? 's' : ''}</span>
          </div>

          {displayFields.length === 0 ? (
            <p className="text-muted text-sm">This form has no answerable fields.</p>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1.1rem' }}>
              {displayFields.map((field) => {
                const val = values[field.id] ?? null;
                return (
                  <div
                    key={field.id}
                    style={{
                      display: 'grid',
                      gridTemplateColumns: '200px 1fr',
                      gap: '0.5rem 1.25rem',
                      borderBottom: '1px solid var(--color-border)',
                      paddingBottom: '1rem',
                      alignItems: 'start',
                    }}
                  >
                    <div>
                      <p style={{ fontWeight: 600, fontSize: '0.85rem', marginBottom: '0.1rem', color: 'var(--color-text)' }}>
                        {field.label}
                        {field.is_required && <span className="required-mark" style={{ marginLeft: '0.2rem' }}>*</span>}
                      </p>
                      <p className="text-xs text-muted" style={{ textTransform: 'capitalize' }}>{field.field_type}</p>
                    </div>
                    <div>
                      <ValueCell
                        field={field}
                        value={val}
                        processing={processing[field.id]}
                        onLightbox={setLightboxSrc}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

      </div>
    </AdminLayout>
  );
}
