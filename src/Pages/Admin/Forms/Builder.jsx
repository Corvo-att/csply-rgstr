import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';
import FieldPalette, { getFieldMeta } from '../../../Components/FormBuilder/FieldPalette.jsx';
import FieldEditor from '../../../Components/FormBuilder/FieldEditor.jsx';
import { api } from '../../../services/api.js';

function slugify(text) {
  return text.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
}

export default function Builder() {
  const { eventId, formId } = useParams();
  const evId = Number(eventId);
  const fId = Number(formId);

  const [event, setEvent] = useState(null);
  const [form, setForm] = useState(null);
  const [fields, setFields] = useState([]);
  const [loading, setLoading] = useState(true);

  const [selectedId, setSelectedId] = useState(null);
  const [dragOverIndex, setDragOverIndex] = useState(null);
  const [saved, setSaved] = useState(false);

  async function loadData() {
    try {
      setLoading(true);
      const data = await api.getForm(formId);
      setForm(data.form);
      setEvent(data.event);
      const normalizedFields = (data.fields || []).map((f, i) => ({
        id: f.id,
        formId: f.form_id || f.formId || fId,
        label: f.label,
        fieldKey: f.field_key || f.fieldKey,
        fieldType: f.field_type || f.fieldType,
        options: f.options || {},
        validationRules: f.validation_rules || f.validationRules || {},
        isRequired: Boolean(f.is_required !== undefined ? f.is_required : f.isRequired),
        sortOrder: f.sort_order !== undefined ? f.sort_order : (f.sortOrder || i),
        helpText: f.help_text || f.helpText || '',
      }));
      setFields(normalizedFields);
    } catch (err) {
      console.error('Failed to load builder form', err);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadData();
  }, [formId]);

  async function syncFields(updated) {
    setFields(updated);
    try {
      const payload = updated.map((f, i) => ({
        label: f.label,
        field_key: f.fieldKey,
        field_type: f.fieldType,
        options: f.options || {},
        is_required: Boolean(f.isRequired),
        sort_order: i + 1,
        help_text: f.helpText || null,
      }));
      await api.saveFormFields(fId, payload);
      flashSaved();
    } catch (err) {
      console.error('Failed to save fields to MySQL', err);
    }
  }

  function flashSaved() {
    setSaved(true);
    setTimeout(() => setSaved(false), 1800);
  }

  // ── Add a new field from palette ──────────────────────────────────────────
  async function handleAddField(type) {
    const meta = getFieldMeta(type);
    const newField = {
      id: 'temp_' + Date.now(),
      formId: fId,
      label: meta.label,
      fieldKey: slugify(meta.label) + '_' + Date.now().toString(36),
      fieldType: type,
      options: {},
      validationRules: {},
      isRequired: false,
      sortOrder: fields.length + 1,
      helpText: '',
    };
    const updated = [...fields, newField];
    setSelectedId(newField.id);
    await syncFields(updated);
  }

  // ── Save edits from FieldEditor ───────────────────────────────────────────
  async function handleSaveField(updates) {
    const updated = fields.map((f) =>
      f.id === selectedId ? { ...f, ...updates } : f
    );
    setSelectedId(null);
    await syncFields(updated);
  }

  // ── Delete a field ────────────────────────────────────────────────────────
  async function handleDelete(id) {
    const updated = fields.filter((f) => f.id !== id).map((f, i) => ({ ...f, sortOrder: i + 1 }));
    if (selectedId === id) setSelectedId(null);
    await syncFields(updated);
  }

  // ── Drag reorder ──────────────────────────────────────────────────────────
  function handleDragStart(e, index) {
    e.dataTransfer.setData('text/plain', String(index));
  }

  async function handleDrop(e, dropIndex) {
    e.preventDefault();
    const dragIndex = Number(e.dataTransfer.getData('text/plain'));
    if (dragIndex === dropIndex) { setDragOverIndex(null); return; }
    const reordered = [...fields];
    const [moved] = reordered.splice(dragIndex, 1);
    reordered.splice(dropIndex, 0, moved);
    const final = reordered.map((f, i) => ({ ...f, sortOrder: i + 1 }));
    setDragOverIndex(null);
    await syncFields(final);
  }

  if (loading) {
    return (
      <AdminLayout>
        <div className="page-content text-center py-5">
          <p className="text-muted">Loading form builder from database...</p>
        </div>
      </AdminLayout>
    );
  }

  if (!form) {
    return (
      <AdminLayout>
        <div className="page-content empty-state">
          <div className="empty-state-icon">!</div>
          <h3>Form not found</h3>
          <Link to="/admin/events"><button className="btn btn-ghost mt-2">Back to Events</button></Link>
        </div>
      </AdminLayout>
    );
  }

  const selectedField = fields.find((f) => f.id === selectedId) || null;

  return (
    <AdminLayout>
      <div className="page-content" style={{ paddingBottom: '1rem' }}>
        {/* Header */}
        <div className="page-header flex justify-between items-center">
          <div>
            <p className="text-sm text-muted mb-1">
              <Link to="/admin/events">Events</Link> /&nbsp;
              {event && <Link to={`/admin/events/${evId}/forms`}>{event.name}</Link>}
              {event && ' / '}
              {form.name}
            </p>
            <h1 className="page-title" style={{ fontSize: '1.4rem' }}>
              Form Builder — {form.name}
            </h1>
          </div>
          <div className="flex gap-1 items-center">
            {saved && <span className="text-sm" style={{ color: 'var(--color-success)' }}>Saved to MySQL</span>}
            <span className="text-sm text-muted">{fields.length} field{fields.length !== 1 ? 's' : ''}</span>
            <Link to={`/admin/forms/${fId}/submissions`}>
              <button className="btn btn-ghost btn-sm">Submissions</button>
            </Link>
          </div>
        </div>

        {/* 3-column builder */}
        <div className="builder-layout">
          {/* Palette */}
          <div className="builder-panel">
            <h3 style={{ fontSize: '0.82rem', textTransform: 'uppercase', letterSpacing: '0.06em', color: 'var(--color-text-muted)', marginBottom: '0.5rem' }}>
              Add Field
            </h3>
            <FieldPalette onAddField={handleAddField} />
          </div>

          {/* Canvas */}
          <div className="builder-canvas">
            {fields.length === 0 ? (
              <div className="empty-state" style={{ padding: '4rem 1rem' }}>
                <div className="empty-state-icon">+</div>
                <h3>No fields yet</h3>
                <p>Click a type in the left panel to add your first field (e.g. Video Upload, Image, Text).</p>
              </div>
            ) : (
              fields.map((f, index) => {
                const meta = getFieldMeta(f.fieldType);
                const isSelected = f.id === selectedId;
                return (
                  <div
                    key={f.id}
                    className={`field-card${isSelected ? ' selected' : ''}${dragOverIndex === index ? ' selected' : ''}`}
                    draggable
                    onDragStart={(e) => handleDragStart(e, index)}
                    onDragOver={(e) => { e.preventDefault(); setDragOverIndex(index); }}
                    onDragLeave={() => setDragOverIndex(null)}
                    onDrop={(e) => handleDrop(e, index)}
                    onClick={() => setSelectedId(f.id === selectedId ? null : f.id)}
                  >
                    <span className="field-drag-handle" title="Drag to reorder">⠿</span>
                    <span style={{ fontSize: '1.1rem', flexShrink: 0 }}>{meta.icon}</span>
                    <div className="field-card-info">
                      <div className="field-card-label">{f.label || `(${meta.label})`}</div>
                      <div className="field-card-type">{meta.label}{f.isRequired ? ' · required' : ''}</div>
                    </div>
                    <div className="field-card-actions" onClick={(e) => e.stopPropagation()}>
                      <button
                        className="btn btn-ghost btn-sm"
                        onClick={() => setSelectedId(f.id === selectedId ? null : f.id)}
                        title="Edit"
                      >Edit</button>
                      <button
                        className="btn btn-danger btn-sm"
                        onClick={() => handleDelete(f.id)}
                        title="Delete"
                      >Del</button>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Editor panel */}
          <div className="builder-panel">
            {selectedField ? (
              <FieldEditor
                key={selectedField.id}
                field={selectedField}
                onSave={handleSaveField}
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
