import React, { useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

/** Open/closed switch, schedule and entry limit for one form. */
function FormSettings({ event, form, onDone }) {
  const { data, setData, patch, processing, errors } = useForm({
    name:            form.name,
    description:     form.description || '',
    is_active:       Boolean(form.is_active),
    opens_at:        form.opens_at_input || '',
    closes_at:       form.closes_at_input || '',
    max_submissions: form.max_submissions ?? '',
  });

  function submit(e) {
    e.preventDefault();
    patch(`/admin/events/${event.id}/forms/${form.id}`, { preserveScroll: true, onSuccess: onDone });
  }

  return (
    <form onSubmit={submit} noValidate style={{ borderTop: '1px solid var(--color-border)', paddingTop: '0.75rem', marginTop: '0.25rem' }}>
      <div className="form-group">
        <label htmlFor={`fs-name-${form.id}`}>Form name</label>
        <input id={`fs-name-${form.id}`} type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
        {errors.name && <span className="form-error">{errors.name}</span>}
      </div>

      <div className="form-group">
        <label htmlFor={`fs-desc-${form.id}`}>Description</label>
        <textarea id={`fs-desc-${form.id}`} rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
      </div>

      <div className="form-group">
        <label className="check-label">
          <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
          Accepting responses
        </label>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.5rem' }}>
        <div className="form-group">
          <label htmlFor={`fs-open-${form.id}`}>Opens at</label>
          <input id={`fs-open-${form.id}`} type="datetime-local" value={data.opens_at} onChange={(e) => setData('opens_at', e.target.value)} />
        </div>
        <div className="form-group">
          <label htmlFor={`fs-close-${form.id}`}>Closes at (deadline)</label>
          <input id={`fs-close-${form.id}`} type="datetime-local" value={data.closes_at} onChange={(e) => setData('closes_at', e.target.value)} />
          {errors.closes_at && <span className="form-error">{errors.closes_at}</span>}
        </div>
        <div className="form-group">
          <label htmlFor={`fs-max-${form.id}`}>Max entries</label>
          <input id={`fs-max-${form.id}`} type="number" min={1} value={data.max_submissions} onChange={(e) => setData('max_submissions', e.target.value)} placeholder="unlimited" />
          {errors.max_submissions && <span className="form-error">{errors.max_submissions}</span>}
        </div>
      </div>

      <div className="flex gap-1" style={{ justifyContent: 'flex-end' }}>
        <button type="button" className="btn btn-ghost btn-sm" onClick={onDone}>Cancel</button>
        <button type="submit" className="btn btn-primary btn-sm" disabled={processing}>{processing ? 'Saving…' : 'Save settings'}</button>
      </div>
    </form>
  );
}

export default function EventForms({ event, forms = [] }) {
  const { auth, flash } = usePage().props;
  const canManage = auth?.adminUser?.can_manage;

  const [showCreate, setShowCreate]   = useState(false);
  const [editingId, setEditingId]     = useState(null);

  const { data, setData, post, processing, errors, reset } = useForm({ name: '', description: '', is_active: true });

  function handleCreate(e) {
    e.preventDefault();
    post(`/admin/events/${event.id}/forms`, {
      onSuccess: () => { reset(); setShowCreate(false); },
    });
  }

  function handleDeleteForm(form) {
    if (!window.confirm(`Delete "${form.name}"? All its fields, submissions and uploaded files will be permanently removed.`)) return;
    router.delete(`/admin/events/${event.id}/forms/${form.id}`);
  }

  return (
    <AdminLayout>
      <div className="page-content">
        {flash?.success && <div className="alert alert-success mb-3">{flash.success}</div>}

        <div className="page-header">
          <p className="text-sm text-muted mb-1"><Link href="/admin/events">Events</Link> / {event.name}</p>
          <h1 className="page-title">{event.name}</h1>
          <p className="page-subtitle">Registration forms for this event.</p>
          {event.status !== 'published' && (
            <div className="alert alert-info mt-1">
              This event is <strong>{event.status}</strong>, so none of its forms accept responses. Publish it from the Events list when you are ready.
            </div>
          )}
        </div>

        {canManage && (showCreate ? (
          <div className="card mb-3">
            <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1rem', fontWeight: 600, marginBottom: '1rem' }}>New Form</h2>
            <form onSubmit={handleCreate} noValidate>
              <div className="form-group">
                <label htmlFor="form-name">Form Name <span className="required-mark">*</span></label>
                <input id="form-name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Contest Entry" autoFocus />
                {errors.name && <span className="form-error">{errors.name}</span>}
              </div>
              <div className="form-group">
                <label htmlFor="form-desc">Description</label>
                <textarea id="form-desc" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} placeholder="Optional description…" />
              </div>
              <div className="flex gap-1" style={{ justifyContent: 'flex-end' }}>
                <button type="button" className="btn btn-ghost" onClick={() => setShowCreate(false)}>Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={processing}>{processing ? 'Creating…' : 'Create & Open Builder'}</button>
              </div>
            </form>
          </div>
        ) : (
          <div className="mb-3"><button className="btn btn-primary" onClick={() => setShowCreate(true)}>New Form</button></div>
        ))}

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
                  <span className={`badge ${f.is_active ? 'badge-active' : 'badge-draft'}`}>{f.is_active ? 'Open' : 'Closed'}</span>
                </div>
                {f.description && <p className="text-sm text-muted">{f.description}</p>}
                <p className="text-sm text-muted">
                  {f.field_count ?? 0} field{f.field_count !== 1 ? 's' : ''} · {f.submission_count ?? 0}
                  {f.max_submissions ? ` / ${f.max_submissions}` : ''} entr{f.submission_count === 1 ? 'y' : 'ies'}
                </p>
                {(f.opens_at || f.closes_at) && (
                  <p className="text-xs text-muted">
                    {f.opens_at ? `Opens ${new Date(f.opens_at).toLocaleString()}` : ''}
                    {f.opens_at && f.closes_at ? ' · ' : ''}
                    {f.closes_at ? `Closes ${new Date(f.closes_at).toLocaleString()}` : ''}
                  </p>
                )}

                <div className="flex gap-1 mt-1" style={{ flexWrap: 'wrap' }}>
                  {canManage && (
                    <Link href={`/admin/events/${event.id}/forms/${f.id}/builder`} style={{ flex: 1 }}>
                      <button className="btn btn-primary btn-sm w-full">Builder</button>
                    </Link>
                  )}
                  <Link href={`/admin/forms/${f.id}/submissions`} style={{ flex: 1 }}>
                    <button className="btn btn-ghost btn-sm w-full">Entries</button>
                  </Link>
                  {canManage && (
                    <>
                      <button className="btn btn-ghost btn-sm" onClick={() => setEditingId(editingId === f.id ? null : f.id)}>Settings</button>
                      <button id={`delete-form-${f.id}`} className="btn btn-danger btn-sm" onClick={() => handleDeleteForm(f)}>Delete</button>
                    </>
                  )}
                </div>

                {canManage && editingId === f.id && <FormSettings event={event} form={f} onDone={() => setEditingId(null)} />}
              </div>
            ))}
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
