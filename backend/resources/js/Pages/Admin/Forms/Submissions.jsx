import React from 'react';
import { Link } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

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
                      let display  = rawVal ?? <span className="text-muted">—</span>;
                      if (rawVal === '1') display = 'Yes';
                      if (rawVal === '0') display = 'No';
                      return <td key={f.id}>{display}</td>;
                    })}
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
