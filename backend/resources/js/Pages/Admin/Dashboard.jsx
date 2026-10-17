import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout.jsx';
import Pagination from '../../Components/Pagination.jsx';

const levelBadge = (level) => (
  level === 'beginner' ? 'badge-draft'
    : level === 'advanced' || level === 'professional' ? 'badge-published'
      : 'badge-active'
);

export default function AdminDashboard({ stats = {}, cosplayers, filters = {} }) {
  const [q, setQ] = useState(filters.q || '');
  const rows = cosplayers.data;

  function search(e) {
    e.preventDefault();
    router.get('/admin/dashboard', q ? { q } : {}, { preserveState: true, replace: true });
  }

  return (
    <AdminLayout>
      <div className="page-content">
        <div className="page-header flex justify-between items-center">
          <div>
            <h1 className="page-title">Dashboard</h1>
            <p className="page-subtitle">Overview of EGYCON registrations and events.</p>
          </div>
          <a href={`/admin/cosplayers/export${filters.q ? `?q=${encodeURIComponent(filters.q)}` : ''}`}>
            <button className="btn btn-secondary" disabled={cosplayers.total === 0}>Export Cosplayers</button>
          </a>
        </div>

        <div className="stats-grid">
          <div className="stat-card"><p className="stat-label">Total Cosplayers</p><p className="stat-value">{stats.total_cosplayers ?? 0}</p></div>
          <div className="stat-card"><p className="stat-label">Events</p><p className="stat-value">{stats.total_events ?? 0}</p></div>
          <div className="stat-card"><p className="stat-label">Published</p><p className="stat-value">{stats.published_events ?? 0}</p></div>
          <div className="stat-card"><p className="stat-label">Submissions</p><p className="stat-value">{stats.total_submissions ?? 0}</p></div>
        </div>

        <div className="card">
          <div className="flex justify-between items-center mb-3" style={{ flexWrap: 'wrap', gap: '0.5rem' }}>
            <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1rem', fontWeight: 600 }}>All Cosplayers</h2>
            <form className="toolbar" style={{ margin: 0 }} onSubmit={search}>
              <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, email, character…" aria-label="Search cosplayers" />
              <button type="submit" className="btn btn-ghost btn-sm">Search</button>
              {filters.q && (
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => { setQ(''); router.get('/admin/dashboard'); }}>Clear</button>
              )}
            </form>
          </div>

          {rows.length === 0 ? (
            <div className="empty-state">
              <div className="empty-state-icon">—</div>
              <h3>{filters.q ? 'No cosplayers match your search' : 'No cosplayers yet'}</h3>
              <p>Registrations will appear here once users sign up and register their character.</p>
            </div>
          ) : (
            <>
              <div className="table-wrapper">
                <table>
                  <thead>
                    <tr><th>#</th><th>Name</th><th>Email</th><th>Character</th><th>Series</th><th>Level</th><th>Registered</th></tr>
                  </thead>
                  <tbody>
                    {rows.map((c) => (
                      <tr key={c.id}>
                        <td className="text-muted">{c.id}</td>
                        <td><strong>{c.name || '—'}</strong></td>
                        <td className="text-muted">{c.email || '—'}</td>
                        <td>{c.character_name}</td>
                        <td>{c.series}</td>
                        <td><span className={`badge ${levelBadge(c.experience_level)}`}>{c.experience_level}</span></td>
                        <td className="text-muted">{c.created_at ? new Date(c.created_at).toLocaleDateString() : '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination links={cosplayers.links} />
              <p className="text-sm text-muted" style={{ textAlign: 'center', marginTop: '0.5rem' }}>
                Showing {cosplayers.from}–{cosplayers.to} of {cosplayers.total}
              </p>
            </>
          )}
        </div>
      </div>
    </AdminLayout>
  );
}
