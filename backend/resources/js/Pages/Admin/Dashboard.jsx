import React from 'react';
import { Link } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout.jsx';

function exportCosplayersCSV(cosplayers) {
  const headers = ['ID', 'Name', 'Email', 'Character', 'Series', 'Experience', 'Registered At'];
  const rows = cosplayers.map((c) => [
    c.id,
    c.name || '',
    c.email || '',
    c.character_name || '',
    c.series || '',
    c.experience_level || '',
    c.created_at || '',
  ]);
  const csv = [headers, ...rows].map((r) => r.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url;
  a.download = 'cosplayers.csv';
  a.click();
  URL.revokeObjectURL(url);
}

export default function AdminDashboard({ stats = {}, cosplayers = [] }) {
  return (
    <AdminLayout>
      <div className="page-content">
        <div className="page-header flex justify-between items-center">
          <div>
            <h1 className="page-title">Dashboard</h1>
            <p className="page-subtitle">Overview of EGYCON registrations and events.</p>
          </div>
          <button
            className="btn btn-secondary"
            onClick={() => exportCosplayersCSV(cosplayers)}
            disabled={cosplayers.length === 0}
          >
            Export Cosplayers
          </button>
        </div>

        {/* Stat cards */}
        <div className="stats-grid">
          <div className="stat-card">
            <p className="stat-label">Total Cosplayers</p>
            <p className="stat-value">{stats.total_cosplayers ?? 0}</p>
          </div>
          <div className="stat-card">
            <p className="stat-label">Events</p>
            <p className="stat-value">{stats.total_events ?? 0}</p>
          </div>
          <div className="stat-card">
            <p className="stat-label">Published</p>
            <p className="stat-value">{stats.published_events ?? 0}</p>
          </div>
          <div className="stat-card">
            <p className="stat-label">Submissions</p>
            <p className="stat-value">{stats.total_submissions ?? 0}</p>
          </div>
        </div>

        {/* Cosplayer table */}
        <div className="card">
          <div className="flex justify-between items-center mb-3">
            <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1rem', fontWeight: 600 }}>All Cosplayers</h2>
            <span className="text-sm text-muted">{cosplayers.length} total</span>
          </div>

          {cosplayers.length === 0 ? (
            <div className="empty-state">
              <div className="empty-state-icon">—</div>
              <h3>No cosplayers yet</h3>
              <p>Registrations will appear here once users sign up and register their character.</p>
            </div>
          ) : (
            <div className="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Character</th>
                    <th>Series</th>
                    <th>Level</th>
                    <th>Registered</th>
                  </tr>
                </thead>
                <tbody>
                  {cosplayers.map((c) => (
                    <tr key={c.id}>
                      <td className="text-muted">{c.id}</td>
                      <td><strong>{c.name || '—'}</strong></td>
                      <td className="text-muted">{c.email || '—'}</td>
                      <td>{c.character_name}</td>
                      <td>{c.series}</td>
                      <td>
                        <span className={`badge ${
                          c.experience_level === 'beginner'
                            ? 'badge-draft'
                            : c.experience_level === 'advanced' || c.experience_level === 'professional'
                            ? 'badge-published'
                            : 'badge-active'
                        }`}>
                          {c.experience_level}
                        </span>
                      </td>
                      <td className="text-muted">{c.created_at ? new Date(c.created_at).toLocaleDateString() : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </AdminLayout>
  );
}
