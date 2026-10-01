import React, { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

export default function EventForms({ event, forms = [] }) {
  const [showCreate, setShowCreate] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm({
    name:        '',
    description: '',
    is_active:   true,
  });

  function handleCreate(e) {
    e.preventDefault();
    post(`/admin/events/${event.id}/forms`, {
      onSuccess: () => {
        reset();
        setShowCreate(false);
      },
    });
  }

  function handleDeleteForm(form) {
    if (!window.confirm(`Delete "${form.name}"? All its fields and submissions will be permanently removed.`)) return;
    router.delete(`/admin/events/${event.id}/forms/${form.id}`);
  }

  return (
    <AdminLayout>
      <div className="page-content">
        <div className="page-header">
          <p className="text-sm text-muted mb-1">
            <Link href="/admin/events">Events</Link> / {event.name}
          </p>
          <h1 className="page-title">{event.name}</h1>
          <p className="page-subtitle">Manage dynamic registration forms for this event.</p>
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
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="e.g. Contest Entry"
                  autoFocus
                />
                {errors.name && <span className="form-error">{errors.name}</span>}
              </div>
              <div className="form-group">
                <label htmlFor="form-desc">Description</label>
                <textarea
                  id="form-desc"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  rows={2}
                  placeholder="Optional description…"
                />
              </div>
              <div className="flex gap-1" style={{ justifyContent: 'flex-end' }}>
                <button type="button" className="btn btn-ghost" onClick={() => setShowCreate(false)}>Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={processing}>
                  {processing ? 'Creating…' : 'Create & Open Builder'}
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
            {forms.map((f) => (
              <div key={f.id} className="card" style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                <div className="flex justify-between items-center">
                  <strong style={{ fontFamily: 'var(--font-display)', fontSize: '0.95rem' }}>{f.name}</strong>
                  <span className={`badge ${f.is_active ? 'badge-active' : 'badge-draft'}`}>
                    {f.is_active ? 'Active' : 'Inactive'}
                  </span>
                </div>
                {f.description && <p className="text-sm text-muted">{f.description}</p>}
                <p className="text-sm text-muted">
                  {f.field_count ?? 0} field{f.field_count !== 1 ? 's' : ''} · {f.submission_count ?? 0} submission{f.submission_count !== 1 ? 's' : ''}
                </p>
                <div className="flex gap-1 mt-1">
                  <Link href={`/admin/events/${event.id}/forms/${f.id}/builder`} style={{ flex: 1 }}>
                    <button className="btn btn-primary btn-sm w-full">Builder</button>
                  </Link>
                  <Link href={`/admin/forms/${f.id}/submissions`} style={{ flex: 1 }}>
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
            ))}
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
