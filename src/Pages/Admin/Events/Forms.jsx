import React, { useState, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';
import { api } from '../../../services/api.js';

export default function EventForms() {
  const { eventId } = useParams();
  const navigate    = useNavigate();

  const [event, setEvent] = useState(null);
  const [forms, setForms] = useState([]);
  const [loading, setLoading] = useState(true);

  const [showCreate, setShowCreate] = useState(false);
  const [newForm,    setNewForm]    = useState({ name: '', description: '' });
  const [errors,     setErrors]     = useState({});
  const [creating,   setCreating]   = useState(false);

  async function loadData() {
    try {
      setLoading(true);
      const data = await api.getForms(eventId);
      setEvent(data.event);
      setForms(data.forms || []);
    } catch (err) {
      console.error('Failed to load event forms', err);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadData();
  }, [eventId]);

  async function handleCreate(e) {
    e.preventDefault();
    if (!newForm.name.trim()) { setErrors({ name: 'Form name is required.' }); return; }
    setCreating(true);
    try {
      const res = await api.createForm(eventId, {
        name: newForm.name.trim(),
        description: newForm.description.trim() || null,
        is_active: true,
      });
      setNewForm({ name: '', description: '' });
      setShowCreate(false);
      setErrors({});
      navigate(`/admin/events/${eventId}/forms/${res.form.id}/builder`);
    } catch (err) {
      setErrors({ name: err.message || 'Failed to create form.' });
    } finally {
      setCreating(false);
    }
  }

  async function handleDeleteForm(form) {
    if (!window.confirm(`Delete "${form.name}"? All its fields and submissions will be permanently removed from MySQL.`)) return;
    try {
      await api.deleteForm(form.id);
      await loadData();
    } catch (err) {
      alert(err.message || 'Failed to delete form');
    }
  }

  if (loading) {
    return (
      <AdminLayout>
        <div className="page-content text-center py-4">
          <p className="text-muted">Loading event forms from database...</p>
        </div>
      </AdminLayout>
    );
  }

  if (!event) {
    return (
      <AdminLayout>
        <div className="page-content">
          <div className="empty-state">
            <div className="empty-state-icon">!</div>
            <h3>Event not found</h3>
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
        <div className="page-header">
          <p className="text-sm text-muted mb-1">
            <Link to="/admin/events">Events</Link> / {event.name}
          </p>
          <h1 className="page-title">{event.name}</h1>
          <p className="page-subtitle">Manage dynamic registration forms for this event in MySQL.</p>
        </div>

        {/* Create form inline */}
        {showCreate ? (
          <div className="card mb-3">
            <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1rem', fontWeight: 600, marginBottom: '1rem' }}>
              New Form
            </h2>
            <form onSubmit={handleCreate} noValidate>
              <div className="form-group">
                <label htmlFor="form-name">Form Name <span className="required-mark">*</span></label>
                <input
                  id="form-name"
                  type="text"
                  value={newForm.name}
                  onChange={(e) => setNewForm((p) => ({ ...p, name: e.target.value }))}
                  placeholder="e.g. Contest Entry"
                  autoFocus
                />
                {errors.name && <span className="form-error">{errors.name}</span>}
              </div>
              <div className="form-group">
                <label htmlFor="form-desc">Description</label>
                <textarea
                  id="form-desc"
                  value={newForm.description}
                  onChange={(e) => setNewForm((p) => ({ ...p, description: e.target.value }))}
                  rows={2}
                  placeholder="Optional description…"
                />
              </div>
              <div className="flex gap-1" style={{ justifyContent: 'flex-end' }}>
                <button type="button" className="btn btn-ghost" onClick={() => setShowCreate(false)}>Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={creating}>
                  {creating ? 'Creating…' : 'Create & Open Builder'}
                </button>
              </div>
            </form>
          </div>
        ) : (
          <div className="mb-3">
            <button className="btn btn-primary" onClick={() => setShowCreate(true)}>New Form</button>
          </div>
        )}

        {forms.length === 0 && !showCreate ? (
          <div className="empty-state card">
            <div className="empty-state-icon" style={{ fontSize: '1rem', fontFamily: 'var(--font-display)' }}>—</div>
            <h3>No forms yet</h3>
            <p>Create a form to start collecting responses from cosplayers.</p>
          </div>
        ) : (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(290px, 1fr))', gap: '1rem' }}>
            {forms.map((f) => {
              const fieldCount = f.field_count !== undefined ? f.field_count : (f.fields_count || 0);
              const subCount   = f.submission_count !== undefined ? f.submission_count : (f.submissions_count || 0);
              const isActive   = f.is_active !== undefined ? f.is_active : f.isActive;

              return (
                <div key={f.id} className="card" style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                  <div className="flex justify-between items-center">
                    <strong style={{ fontFamily: 'var(--font-display)', fontSize: '0.95rem' }}>{f.name}</strong>
                    <span className={`badge ${isActive ? 'badge-active' : 'badge-draft'}`}>
                      {isActive ? 'Active' : 'Inactive'}
                    </span>
                  </div>
                  {f.description && <p className="text-sm text-muted">{f.description}</p>}
                  <p className="text-sm text-muted">
                    {fieldCount} field{fieldCount !== 1 ? 's' : ''} &middot; {subCount} submission{subCount !== 1 ? 's' : ''}
                  </p>
                  <div className="flex gap-1 mt-1">
                    <Link to={`/admin/events/${eventId}/forms/${f.id}/builder`} style={{ flex: 1 }}>
                      <button className="btn btn-primary btn-sm w-full">Builder</button>
                    </Link>
                    <Link to={`/admin/forms/${f.id}/submissions`} style={{ flex: 1 }}>
                      <button className="btn btn-ghost btn-sm w-full">Submissions</button>
                    </Link>
                    <button
                      id={`delete-form-${f.id}`}
                      className="btn btn-danger btn-sm"
                      onClick={() => handleDeleteForm(f)}
                      title="Delete this form"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
