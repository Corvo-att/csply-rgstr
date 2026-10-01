import React from 'react';
import { Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

const statusClass = { published: 'badge-published', draft: 'badge-draft', closed: 'badge-closed' };

export default function EventsIndex({ events = [] }) {

  function toggleStatus(ev) {
    if (ev.status === 'closed') return;
    const newStatus = ev.status === 'published' ? 'draft' : 'published';
    router.patch(`/admin/events/${ev.id}`, { status: newStatus }, { preserveScroll: true });
  }

  function handleDelete(ev) {
    if (!window.confirm(`Delete "${ev.name}"? This will permanently remove it.`)) return;
    router.delete(`/admin/events/${ev.id}`);
  }

  return (
    <AdminLayout>
      <div className="page-content">
        <div className="page-header flex justify-between items-center">
          <div>
            <h1 className="page-title">Events</h1>
            <p className="page-subtitle">Manage convention events and their forms.</p>
          </div>
          <Link href="/admin/events/create">
            <button className="btn btn-primary">New Event</button>
          </Link>
        </div>

        {events.length === 0 ? (
          <div className="empty-state card">
            <div className="empty-state-icon" style={{ fontSize: '1.2rem' }}>—</div>
            <h3>No events yet</h3>
            <p>Create your first event to get started.</p>
            <Link href="/admin/events/create">
              <button className="btn btn-primary mt-2">Create Event</button>
            </Link>
          </div>
        ) : (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Event</th>
                  <th>Location</th>
                  <th>Dates</th>
                  <th>Status</th>
                  <th>Forms</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {events.map((ev) => {
                  const isClosed = ev.status === 'closed';
                  return (
                    <tr key={ev.id}>
                      <td>
                        <strong>{ev.name}</strong>
                        {ev.description && (
                          <p className="text-sm text-muted" style={{ marginTop: '0.15rem' }}>
                            {ev.description}
                          </p>
                        )}
                      </td>
                      <td className="text-muted">{ev.location || '—'}</td>
                      <td className="text-muted" style={{ fontSize: '0.82rem', whiteSpace: 'nowrap' }}>
                        {ev.starts_at ? new Date(ev.starts_at).toLocaleDateString() : '—'}
                        {' → '}
                        {ev.ends_at   ? new Date(ev.ends_at).toLocaleDateString()   : '—'}
                      </td>
                      <td>
                        <span className={`badge ${statusClass[ev.status] || 'badge-draft'}`}>
                          {ev.status}
                        </span>
                      </td>
                      <td className="text-muted">{ev.forms_count ?? 0}</td>
                      <td>
                        <div className="flex gap-1">
                          <Link href={`/admin/events/${ev.id}`}>
                            <button className="btn btn-ghost btn-sm">Forms</button>
                          </Link>

                          {!isClosed && (
                            <button
                              id={`toggle-status-${ev.id}`}
                              className="btn btn-sm"
                              style={
                                ev.status === 'published'
                                  ? { background: 'rgba(201,138,10,0.1)', color: 'var(--color-warning)', border: '1px solid rgba(201,138,10,0.25)' }
                                  : { background: 'rgba(41,168,117,0.1)', color: 'var(--color-success)', border: '1px solid rgba(41,168,117,0.25)' }
                              }
                              onClick={() => toggleStatus(ev)}
                              title={ev.status === 'published' ? 'Revert to Draft' : 'Publish'}
                            >
                              {ev.status === 'published' ? 'Unpublish' : 'Publish'}
                            </button>
                          )}

                          <button
                            id={`delete-event-${ev.id}`}
                            className="btn btn-danger btn-sm"
                            onClick={() => handleDelete(ev)}
                            title="Delete event"
                          >
                            Delete
                          </button>
                        </div>
                      </td>
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
