import React, { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';
import FieldPalette, { getFieldMeta } from '../../../Components/FormBuilder/FieldPalette.jsx';
import FieldEditor from '../../../Components/FormBuilder/FieldEditor.jsx';

function slugify(text) {
  return text.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
}

let tempCounter = 0;

function fromServer(f, i) {
  return {
    id:         f.id,
    label:      f.label,
    fieldKey:   f.field_key,
    fieldType:  f.field_type,
    options:    f.options || {},
    isRequired: Boolean(f.is_required),
    sortOrder:  f.sort_order ?? i,
    helpText:   f.help_text || '',
  };
}

export default function Builder({ event, form, fields: serverFields = [], submission_count: submissionCount = 0, upload_limit_mb: uploadLimitMb }) {
  const { errors } = usePage().props;

  const [fields, setFields]           = useState(() => serverFields.map(fromServer));
  const [selectedId, setSelectedId]   = useState(null);
  const [dragOverIndex, setDragOver]  = useState(null);
  const [dirty, setDirty]             = useState(false);
  const [saving, setSaving]           = useState(false);
  const [savedAt, setSavedAt]         = useState(false);
  const pendingSelection              = useRef(null);

  // Warn before leaving the page (closing the tab or clicking a link) with unsaved work.
  useEffect(() => {
    if (!dirty) return undefined;

    const onBeforeUnload = (e) => { e.preventDefault(); e.returnValue = ''; };
    window.addEventListener('beforeunload', onBeforeUnload);
    const removeListener = router.on('before', (e) => {
      if (e.detail.visit.method !== 'get') return undefined;               // only guard navigation, not our own save
      return window.confirm('You have unsaved changes. Leave without saving?');
    });

    return () => { window.removeEventListener('beforeunload', onBeforeUnload); removeListener(); };
  }, [dirty]);

  function change(updated) {
    setFields(updated);
    setDirty(true);
    setSavedAt(false);
  }

  function handleAddField(type) {
    const meta = getFieldMeta(type);
    const id = `new_${++tempCounter}`;
    const newField = {
      id,
      label:      meta.label,
      fieldKey:   `${slugify(meta.label)}_${Date.now().toString(36)}`,
      fieldType:  type,
      options:    {},
      isRequired: false,
      helpText:   '',
    };
    setSelectedId(id);
    change([...fields, newField]);
  }

  function handleApplyField(updates) {
    change(fields.map((f) => (f.id === selectedId ? { ...f, ...updates } : f)));
    setSelectedId(null);
  }

  function handleDelete(f) {
    const hasAnswers = typeof f.id === 'number' && submissionCount > 0;
    const warning = hasAnswers
      ? `Delete "${f.label}"? Answers cosplayers already gave to this field will be permanently deleted when you save.`
      : `Delete "${f.label}"?`;
    if (!window.confirm(warning)) return;

    if (selectedId === f.id) setSelectedId(null);
    change(fields.filter((x) => x.id !== f.id));
  }

  function move(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= fields.length) return;
    const reordered = [...fields];
    [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
    change(reordered);
  }

  function handleDrop(e, dropIndex) {
    e.preventDefault();
    const dragIndex = Number(e.dataTransfer.getData('text/plain'));
    setDragOver(null);
    if (Number.isNaN(dragIndex) || dragIndex === dropIndex) return;

    const reordered = [...fields];
    const [moved] = reordered.splice(dragIndex, 1);
    reordered.splice(dropIndex, 0, moved);
    change(reordered);
  }

  function save() {
    setSaving(true);

    const payload = fields.map((f) => ({
      id:          typeof f.id === 'number' ? f.id : null,
      label:       f.label,
      field_key:   f.fieldKey || null,
      field_type:  f.fieldType,
      options:     f.options || {},
      is_required: Boolean(f.isRequired),
      help_text:   f.helpText || null,
    }));

    // Remember which row was selected (by position) so we can re-select it after new ids arrive.
    pendingSelection.current = fields.findIndex((f) => f.id === selectedId);

    router.put(`/admin/forms/${form.id}/fields`, { fields: payload }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (page) => {
        // The server returns the saved list with real ids: adopt them (new rows had temporary ids).
        const saved = page.props.fields.map(fromServer);
        setFields(saved);
        setSelectedId(pendingSelection.current >= 0 ? saved[pendingSelection.current]?.id ?? null : null);
        setDirty(false);
        setSavedAt(true);
        setTimeout(() => setSavedAt(false), 2500);
      },
      onFinish: () => setSaving(false),
    });
  }

  const selectedField = fields.find((f) => f.id === selectedId) || null;
  const errorMessages = Object.values(errors || {}).filter(Boolean);

  return (
    <AdminLayout>
      <div className="page-content" style={{ paddingBottom: '1rem' }}>
        <div className="page-header flex justify-between items-center" style={{ flexWrap: 'wrap', gap: '0.75rem' }}>
          <div>
            <p className="text-sm text-muted mb-1">
              <Link href="/admin/events">Events</Link> /&nbsp;
              {event && <Link href={`/admin/events/${event.id}`}>{event.name}</Link>}
              {event && ' / '}
              {form.name}
            </p>
            <h1 className="page-title" style={{ fontSize: '1.4rem' }}>Form Builder — {form.name}</h1>
          </div>
          <div className="flex gap-1 items-center" style={{ flexWrap: 'wrap' }}>
            {savedAt && <span className="text-sm" style={{ color: 'var(--color-success)' }}>Saved ✓</span>}
            {dirty && !saving && <span className="text-sm" style={{ color: 'var(--color-warning)' }}>Unsaved changes</span>}
            <span className="text-sm text-muted">{fields.length} field{fields.length !== 1 ? 's' : ''}</span>
            <Link href={`/admin/forms/${form.id}/submissions`}>
              <button type="button" className="btn btn-ghost btn-sm">Submissions ({submissionCount})</button>
            </Link>
            <button type="button" className="btn btn-primary" onClick={save} disabled={!dirty || saving}>
              {saving ? 'Saving…' : 'Save form'}
            </button>
          </div>
        </div>

        {submissionCount > 0 && (
          <div className="alert alert-info mb-3">
            This form already has {submissionCount} submission{submissionCount !== 1 ? 's' : ''}. Editing labels, help text and options is safe.
            Deleting a field deletes the answers given to it, and a field's type can no longer be changed.
          </div>
        )}

        {errorMessages.length > 0 && (
          <div className="alert alert-error mb-3">
            {errorMessages.map((m, i) => <div key={i}>{m}</div>)}
          </div>
        )}

        <div className="builder-layout">
          <div className="builder-panel">
            <h3 style={{ fontSize: '0.82rem', textTransform: 'uppercase', letterSpacing: '0.06em', color: 'var(--color-text-muted)', marginBottom: '0.5rem' }}>
              Add Field
            </h3>
            <FieldPalette onAddField={handleAddField} />
          </div>

          <div className="builder-canvas">
            {fields.length === 0 ? (
              <div className="empty-state" style={{ padding: '4rem 1rem' }}>
                <div className="empty-state-icon">+</div>
                <h3>No fields yet</h3>
                <p>Click a type in the left panel to add your first field.</p>
              </div>
            ) : (
              fields.map((f, index) => {
                const meta = getFieldMeta(f.fieldType);
                const isSelected = f.id === selectedId;
                return (
                  <div
                    key={f.id}
                    className={`field-card${isSelected || dragOverIndex === index ? ' selected' : ''}`}
                    draggable
                    onDragStart={(e) => e.dataTransfer.setData('text/plain', String(index))}
                    onDragOver={(e) => { e.preventDefault(); setDragOver(index); }}
                    onDragLeave={() => setDragOver(null)}
                    onDrop={(e) => handleDrop(e, index)}
                    onClick={() => setSelectedId(isSelected ? null : f.id)}
                  >
                    <span className="field-drag-handle" title="Drag to reorder">⠿</span>
                    <span style={{ fontSize: '1.1rem', flexShrink: 0 }}>{meta.icon}</span>
                    <div className="field-card-info">
                      <div className="field-card-label">{f.label || `(${meta.label})`}</div>
                      <div className="field-card-type">{meta.label}{f.isRequired ? ' · required' : ''}</div>
                    </div>
                    <div className="field-card-actions" onClick={(e) => e.stopPropagation()}>
                      {/* up/down buttons: drag & drop does not work on phones */}
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => move(index, -1)} disabled={index === 0} aria-label="Move up">↑</button>
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => move(index, 1)} disabled={index === fields.length - 1} aria-label="Move down">↓</button>
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => setSelectedId(isSelected ? null : f.id)}>Edit</button>
                      <button type="button" className="btn btn-danger btn-sm" onClick={() => handleDelete(f)}>Del</button>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          <div className="builder-panel">
            {selectedField ? (
              <FieldEditor
                key={selectedField.id}
                field={selectedField}
                uploadLimitMb={uploadLimitMb}
                onSave={handleApplyField}
                onCancel={() => setSelectedId(null)}
              />
            ) : (
              <div className="empty-state" style={{ padding: '2rem 0.5rem' }}>
                <div className="empty-state-icon">←</div>
                <p className="text-sm">Select a field on the canvas to edit it.</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
