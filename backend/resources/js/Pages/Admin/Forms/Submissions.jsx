import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

const MEDIA_TYPES = ['file', 'image', 'video'];

function isUrl(val) {
  try { return Boolean(new URL(val)); } catch { return false; }
}

function isImage(url) {
  return /\.(png|jpe?g|gif|webp|svg|bmp|tiff?)(\?.*)?$/i.test(url);
}

function isVideo(url) {
  return /\.(mp4|webm|ogg|mov|avi|mkv)(\?.*)?$/i.test(url);
}

function MediaThumb({ url }) {
  if (!url || !isUrl(url)) return <span className="text-muted">—</span>;
  if (isVideo(url)) {
    return (
      <span title={url} style={{ display: 'inline-flex', alignItems: 'center', gap: '0.3rem', color: 'var(--color-primary)', fontSize: '0.82rem' }}>
        <span style={{ fontSize: '1rem' }}>▶</span> video
      </span>
    );
  }
  if (isImage(url)) {
    return (
      <img
        src={url}
        alt="submission"
        style={{ width: 48, height: 48, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--color-border)', verticalAlign: 'middle', cursor: 'pointer' }}
        onClick={() => window.open(url, '_blank')}
        title="Click to open full size"
      />
    );
  }
  // Generic file link
  return (
    <a href={url} target="_blank" rel="noreferrer" className="text-sm" style={{ color: 'var(--color-primary)' }}>
      📎 file
    </a>
  );
}

function exportSubmissionsCSV(form, fields, submissions) {
  const headers = ['Submission ID', 'Cosplayer', 'Character', 'Series', 'Submitted At', ...fields.map((f) => f.label)];
  const rows = submissions.map((sub) => {
    const values = fields.map((f) => {
      const v = sub.values ? sub.values[f.id] : '';
      return v ?? '';
    });
    return [
      sub.id,
      sub.cosplayer?.name || 'Unknown',
      sub.cosplayer?.character_name || '',
      sub.cosplayer?.series || '',
      sub.submitted_at || '',
      ...values,
    ];
  });
  const csv = [headers, ...rows].map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url;
  a.download = `${(form.name || 'form').replace(/\s+/g, '_')}_submissions.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

export default function Submissions({ form, fields = [], submissions = [] }) {
  const [showImportModal, setShowImportModal] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [uploading, setUploading] = useState(false);

  const handleUpload = (e) => {
    e.preventDefault();
    if (!importFile) return;

    setUploading(true);
    const formData = new FormData();
    formData.append('file', importFile);

    router.post(`/admin/forms/${form.id}/import`, formData, {
      onFinish: () => setUploading(false),
    });
  };

  return (
    <AdminLayout>
      <div className="page-content">
        <div className="page-header flex justify-between items-center">
          <div>
            <p className="text-sm text-muted mb-1">
              <Link href="/admin/events">Events</Link>
              {form?.event_id && (
                <> / <Link href={`/admin/events/${form.event_id}`}>Event</Link></>
              )}
              {' / '}{form.name}
            </p>
            <h1 className="page-title">Submissions</h1>
            <p className="page-subtitle">
              {form.name} &mdash; {submissions.length} response{submissions.length !== 1 ? 's' : ''} recorded
            </p>
          </div>
          <div className="flex gap-2">
            <Link href={`/admin/events/${form.event_id}/forms/${form.id}/builder`}>
              <button className="btn btn-ghost btn-sm">Builder</button>
            </Link>
            <Link href={`/admin/forms/${form.id}/judging`}>
              <button className="btn btn-secondary btn-sm">⚖️ Judging Sheets</button>
            </Link>
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => setShowImportModal(true)}
            >
              📥 Import Excel
            </button>
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => exportSubmissionsCSV(form, fields, submissions)}
              disabled={submissions.length === 0}
            >
              Export CSV
            </button>
          </div>
        </div>

        {/* Import Modal */}
        {showImportModal && (
          <div
            className="modal-backdrop"
            style={{
              position: 'fixed',
              inset: 0,
              backgroundColor: 'rgba(0,0,0,0.6)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              zIndex: 9999,
              backdropFilter: 'blur(3px)',
            }}
          >
            <div className="card" style={{ width: '100%', maxWidth: 480, padding: '1.75rem' }}>
              <div className="flex justify-between items-center mb-3">
                <h3 style={{ margin: 0, fontSize: '1.2rem', fontWeight: 600 }}>
                  Import Submissions from Excel
                </h3>
                <button
                  type="button"
                  className="btn btn-ghost btn-sm"
                  onClick={() => setShowImportModal(false)}
                >
                  ✕
                </button>
              </div>

              <p className="text-muted text-sm mb-4">
                Upload an <code>.xlsx</code>, <code>.xls</code>, or <code>.csv</code> file. You will be able to review, map columns, and fix validation issues before importing.
              </p>

              <div className="mb-4 p-3" style={{ background: 'var(--color-bg)', borderRadius: 8, border: '1px solid var(--color-border)' }}>
                <span className="text-xs text-muted block mb-1">Need a template?</span>
                <a
                  href={`/admin/forms/${form.id}/import/template`}
                  className="text-sm font-semibold"
                  style={{ color: 'var(--color-primary)' }}
                >
                  📄 Download pre-formatted Excel template
                </a>
              </div>

              <form onSubmit={handleUpload}>
                <div className="form-group mb-4">
                  <label className="form-label text-sm font-medium">Select Spreadsheet File</label>
                  <input
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    className="form-control"
                    onChange={(e) => setImportFile(e.target.files[0] || null)}
                    required
                  />
                </div>

                <div className="flex justify-end gap-2">
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setShowImportModal(false)}
                    disabled={uploading}
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={!importFile || uploading}
                  >
                    {uploading ? 'Uploading & Parsing...' : 'Next: Map Columns →'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {submissions.length === 0 ? (
          <div className="empty-state card">
            <div className="empty-state-icon" style={{ fontSize: '1rem' }}>—</div>
            <h3>No submissions yet</h3>
            <p>Responses will appear here once cosplayers start filling out this form.</p>
          </div>
        ) : (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Cosplayer</th>
                  <th>Submitted At</th>
                  {fields.map((f) => (
                    <th key={f.id}>{f.label}</th>
                  ))}
                  <th style={{ width: 80 }}>Detail</th>
                </tr>
              </thead>
              <tbody>
                {submissions.map((sub) => (
                  <tr key={sub.id}>
                    <td className="text-muted">{sub.id}</td>
                    <td>
                      <strong>{sub.cosplayer?.name || `Cosplayer #${sub.id}`}</strong>
                      {sub.cosplayer && (
                        <p className="text-sm text-muted">
                          {sub.cosplayer.character_name} &mdash; {sub.cosplayer.series}
                        </p>
                      )}
                    </td>
                    <td className="text-muted">
                      {sub.submitted_at ? new Date(sub.submitted_at).toLocaleString() : '—'}
                    </td>
                    {fields.map((f) => {
                      const rawVal = sub.values ? sub.values[f.id] : null;
                      const isMedia = MEDIA_TYPES.includes(f.field_type);

                      let display;
                      if (isMedia && rawVal) {
                        display = <MediaThumb url={rawVal} />;
                      } else if (rawVal === '1') {
                        display = 'Yes';
                      } else if (rawVal === '0') {
                        display = 'No';
                      } else {
                        display = rawVal ?? <span className="text-muted">—</span>;
                      }

                      return <td key={f.id}>{display}</td>;
                    })}
                    <td>
                      <Link href={`/admin/forms/${form.id}/submissions/${sub.id}`}>
                        <button className="btn btn-ghost btn-sm">View</button>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
