import React from 'react';
import { Link } from '@inertiajs/react';
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
          <div className="flex gap-1">
            <Link href={`/admin/events/${form.event_id}/forms/${form.id}/builder`}>
              <button className="btn btn-ghost btn-sm">Builder</button>
            </Link>
            <button
              className="btn btn-secondary"
              onClick={() => exportSubmissionsCSV(form, fields, submissions)}
              disabled={submissions.length === 0}
            >
              Export CSV
            </button>
          </div>
        </div>

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
