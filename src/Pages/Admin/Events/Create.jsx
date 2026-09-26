import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';
import { api } from '../../../services/api.js';

const statusOptions = ['draft', 'published', 'closed'];

export default function EventCreate() {
  const navigate = useNavigate();
  const [form, setForm] = useState({
    name: '', description: '', startsAt: '', endsAt: '', location: '', status: 'draft',
  });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  function validate() {
    const e = {};
    if (!form.name.trim()) e.name = 'Event name is required.';
    if (form.startsAt && form.endsAt && form.endsAt < form.startsAt) {
      e.endsAt = 'End date must be after start date.';
    }
    return e;
  }

  function handleChange(e) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    setErrors((prev) => ({ ...prev, [e.target.name]: undefined }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length) { setErrors(errs); return; }
    setSubmitting(true);
    try {
      await api.createEvent({
        name: form.name.trim(),
        description: form.description.trim() || null,
        starts_at: form.startsAt || null,
        ends_at: form.endsAt || null,
        location: form.location.trim() || null,
        status: form.status,
      });
      setSubmitting(false);
      navigate('/admin/events');
    } catch (err) {
      setSubmitting(false);
      setErrors({ name: err.message || 'Failed to create event.' });
    }
  }

  return (
    <AdminLayout>
      <div className="page-content" style={{ maxWidth: 640 }}>
        <div className="page-header">
          <h1 className="page-title">New Event</h1>
          <p className="page-subtitle">Create a convention event in MySQL. You can add forms after saving.</p>
        </div>

        <div className="card">
          <form onSubmit={handleSubmit} noValidate>
            <div className="form-group">
              <label htmlFor="ev-name">Event Name <span className="required-mark">*</span></label>
              <input id="ev-name" type="text" name="name" value={form.name} onChange={handleChange} placeholder="e.g. EGYCON 2027" />
              {errors.name && <span className="form-error">{errors.name}</span>}
            </div>

            <div className="form-group">
              <label htmlFor="ev-desc">Description</label>
              <textarea id="ev-desc" name="description" value={form.description} onChange={handleChange} placeholder="Brief description of this event…" rows={3} />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
              <div className="form-group">
                <label htmlFor="ev-starts">Starts At</label>
                <input id="ev-starts" type="datetime-local" name="startsAt" value={form.startsAt} onChange={handleChange} />
              </div>
              <div className="form-group">
                <label htmlFor="ev-ends">Ends At</label>
                <input id="ev-ends" type="datetime-local" name="endsAt" value={form.endsAt} onChange={handleChange} />
                {errors.endsAt && <span className="form-error">{errors.endsAt}</span>}
              </div>
            </div>

            <div className="form-group">
              <label htmlFor="ev-location">Location</label>
              <input id="ev-location" type="text" name="location" value={form.location} onChange={handleChange} placeholder="e.g. Cairo International Convention Centre" />
            </div>

            <div className="form-group">
              <label htmlFor="ev-status">Status</label>
              <select id="ev-status" name="status" value={form.status} onChange={handleChange}>
                {statusOptions.map((s) => (
                  <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                ))}
              </select>
            </div>

            <div className="flex gap-1" style={{ justifyContent: 'flex-end' }}>
              <button type="button" className="btn btn-ghost" onClick={() => navigate('/admin/events')}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={submitting}>
                {submitting ? 'Creating…' : 'Create Event'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </AdminLayout>
  );
}
