import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';
import { api } from '../../../services/api.js';

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
      sub.submitted_at || sub.submittedAt || '',
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

export default function Submissions() {
  const { formId } = useParams();

  const [form, setForm] = useState(null);
  const [event, setEvent] = useState(null);
  const [fields, setFields] = useState([]);
  const [submissions, setSubmissions] = useState([]);
  const [loading, setLoading] = useState(true);

  async function loadSubmissions() {
    try {
      setLoading(true);
      const data = await api.getSubmissions(formId);
      setForm(data.form);
      setEvent(data.event);
      setFields(data.fields || []);
      setSubmissions(data.submissions || []);
    } catch (err) {
      console.error('Failed to load submissions', err);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadSubmissions();
  }, [formId]);

  if (loading) {
    return (
      <AdminLayout>
        <div className="page-content text-center py-4">
          <p className="text-muted">Loading submissions from database...</p>
        </div>
      </AdminLayout>
    );
  }

  if (!form) {
    return (
      <AdminLayout>
        <div className="page-content">
          <div className="empty-state">
            <div className="empty-state-icon">!</div>
            <h3>Form not found</h3>
            <Link to="/admin/events">
              <button className="btn btn-ghost mt-2">Back to Events</button>
            </Link>
          </div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout>
      <div className="page-content">
        <div className="page-header flex justify-between items-center">
          <div>
            <p className="text-sm text-muted mb-1">
              <Link to="/admin/events">Events</Link>
              {event && <> / <Link to={`/admin/events/${event.id}/forms`}>{event.name}</Link></>}
              {' / '}{form.name}
            </p>
            <h1 className="page-title">Submissions</h1>
            <p className="page-subtitle">
              {form.name} &mdash; {submissions.length} response{submissions.length !== 1 ? 's' : ''} recorded in MySQL
            </p>
          </div>
          <div className="flex gap-1">
            <Link to={`/admin/events/${form.event_id || form.eventId}/forms/${form.id}/builder`}>
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
                {submissions.map((sub) => {
                  const dateStr = sub.submitted_at || sub.submittedAt;
                  return (
                    <tr key={sub.id}>
                      <td className="text-muted">{sub.id}</td>
                      <td>
                        <strong>{sub.cosplayer?.name || `Cosplayer #${sub.id}`}</strong>
                        {sub.cosplayer && (
                          <p className="text-sm text-muted">
                            {sub.cosplayer.character_name || sub.cosplayer.characterName} &mdash; {sub.cosplayer.series}
                          </p>
                        )}
                      </td>
                      <td className="text-muted">
                        {dateStr ? new Date(dateStr).toLocaleString() : '—'}
                      </td>
                      {fields.map((f) => {
                        const rawVal = sub.values ? sub.values[f.id] : null;
                        let display = rawVal ?? <span className="text-muted">—</span>;
                        if (rawVal === '1') display = 'Yes';
                        if (rawVal === '0') display = 'No';
                        return <td key={f.id}>{display}</td>;
                      })}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
