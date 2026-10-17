import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

const statusOptions = ['draft', 'published', 'closed'];

export default function EventCreate({ event = null }) {
  const editing = Boolean(event);
  const { data, setData, post, put, processing, errors } = useForm({
    name:        event?.name        ?? '',
    description: event?.description ?? '',
    starts_at:   event?.starts_at   ?? '',
    ends_at:     event?.ends_at     ?? '',
    location:    event?.location    ?? '',
    status:      event?.status      ?? 'draft',
  });

  function handleSubmit(e) {
    e.preventDefault();
    if (editing) {
      put(`/admin/events/${event.id}`, { onSuccess: () => {} });
    } else {
      post('/admin/events');
    }
  }

  return (
    <AdminLayout>
      <div className="page-content" style={{ maxWidth: 640 }}>
        <div className="page-header">
          <h1 className="page-title">{editing ? 'Edit Event' : 'New Event'}</h1>
          <p className="page-subtitle">
            {editing ? 'Change the event details.' : 'Create a convention event. You can add forms after saving.'}
          </p>
        </div>

        <div className="card">
          <form onSubmit={handleSubmit} noValidate>
            <div className="form-group">
              <label htmlFor="ev-name">Event Name <span className="required-mark">*</span></label>
              <input
                id="ev-name"
                type="text"
                name="name"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="e.g. EGYCON 2027"
              />
              {errors.name && <span className="form-error">{errors.name}</span>}
            </div>

            <div className="form-group">
              <label htmlFor="ev-desc">Description</label>
              <textarea
                id="ev-desc"
                name="description"
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                placeholder="Brief description of this event…"
                rows={3}
              />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
              <div className="form-group">
                <label htmlFor="ev-starts">Starts At</label>
                <input
                  id="ev-starts"
                  type="datetime-local"
                  name="starts_at"
                  value={data.starts_at}
                  onChange={(e) => setData('starts_at', e.target.value)}
                />
              </div>
              <div className="form-group">
                <label htmlFor="ev-ends">Ends At</label>
                <input
                  id="ev-ends"
                  type="datetime-local"
                  name="ends_at"
                  value={data.ends_at}
                  onChange={(e) => setData('ends_at', e.target.value)}
                />
                {errors.ends_at && <span className="form-error">{errors.ends_at}</span>}
              </div>
            </div>

            <div className="form-group">
              <label htmlFor="ev-location">Location</label>
              <input
                id="ev-location"
                type="text"
                name="location"
                value={data.location}
                onChange={(e) => setData('location', e.target.value)}
                placeholder="e.g. Cairo International Convention Centre"
              />
            </div>

            <div className="form-group">
              <label htmlFor="ev-status">Status</label>
              <select
                id="ev-status"
                name="status"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
              >
                {statusOptions.map((s) => (
                  <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                ))}
              </select>
            </div>

            <div className="flex gap-1" style={{ justifyContent: 'flex-end' }}>
              <Link href="/admin/events">
                <button type="button" className="btn btn-ghost">Cancel</button>
              </Link>
              <button type="submit" className="btn btn-primary" disabled={processing}>
                {processing ? 'Saving…' : editing ? 'Save Changes' : 'Create Event'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </AdminLayout>
  );
}
