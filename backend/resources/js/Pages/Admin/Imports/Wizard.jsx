import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';

export default function Wizard({
  batch,
  form,
  event,
  sheetHeaders = [],
  rows,
  statusFilter = 'all',
  stats,
}) {
  const [columnMap, setColumnMap] = useState(batch.column_map || {});
  const [duplicateStrategy, setDuplicateStrategy] = useState(
    batch.options?.duplicate_strategy || 'skip'
  );
  const [savingMapping, setSavingMapping] = useState(false);
  const [committing, setCommitting] = useState(false);
  const [rollingBack, setRollingBack] = useState(false);
  const [editingRow, setEditingRow] = useState(null);
  const [rowEditForm, setRowEditForm] = useState({ identity: {}, values: {} });

  const identityFields = [
    { key: 'email', label: 'Email Address', required: true },
    { key: 'name', label: 'Cosplayer Full Name', required: false },
    { key: 'character_name', label: 'Character Name', required: false },
    { key: 'series', label: 'Series / Origin', required: false },
    { key: 'experience_level', label: 'Experience Level', required: false },
    { key: 'bio', label: 'Bio / Notes', required: false },
  ];

  const handleMapChange = (targetKey, headerVal) => {
    setColumnMap((prev) => ({
      ...prev,
      [targetKey]: headerVal,
    }));
  };

  const handleSaveMapping = (e) => {
    e.preventDefault();
    setSavingMapping(true);
    router.put(
      `/admin/imports/${batch.id}/mapping`,
      {
        column_map: columnMap,
        options: { duplicate_strategy: duplicateStrategy },
      },
      {
        onFinish: () => setSavingMapping(false),
      }
    );
  };

  const handleCommit = () => {
    if (
      !confirm(
        `Are you sure you want to commit this import? This will write ${stats.valid} submissions to the database and sync them to Google Sheets.`
      )
    ) {
      return;
    }
    setCommitting(true);
    router.post(
      `/admin/imports/${batch.id}/commit`,
      {},
      {
        onFinish: () => setCommitting(false),
      }
    );
  };

  const handleRollback = () => {
    if (
      !confirm(
        'Are you sure you want to rollback this import? All submissions created from this batch will be permanently deleted.'
      )
    ) {
      return;
    }
    setRollingBack(true);
    router.post(
      `/admin/imports/${batch.id}/rollback`,
      {},
      {
        onFinish: () => setRollingBack(false),
      }
    );
  };

  const openRowEditor = (row) => {
    setEditingRow(row);
    setRowEditForm({
      identity: { ...(row.normalized?.identity || {}) },
      values: { ...(row.normalized?.values || {}) },
    });
  };

  const handleSaveRow = (e) => {
    e.preventDefault();
    router.put(
      `/admin/imports/rows/${editingRow.id}`,
      rowEditForm,
      {
        onSuccess: () => setEditingRow(null),
      }
    );
  };

  return (
    <AdminLayout>
      <Head title={`Import Wizard: ${batch.original_filename}`} />
      <div className="page-content" style={{ maxWidth: 1200, margin: '0 auto' }}>
        {/* Breadcrumb Header */}
        <div className="page-header flex justify-between items-center mb-4">
          <div>
            <p className="text-sm text-muted mb-1">
              <Link href="/admin/events">Events</Link> /{' '}
              <Link href={`/admin/events/${event.id}`}>{event.name}</Link> /{' '}
              <Link href={`/admin/forms/${form.id}/submissions`}>{form.name}</Link> / Import Wizard
            </p>
            <div className="flex items-center gap-2">
              <h1 className="page-title" style={{ margin: 0 }}>
                {batch.original_filename}
              </h1>
              <span
                className={`badge badge-${
                  batch.status === 'completed'
                    ? 'success'
                    : batch.status === 'ready'
                    ? 'primary'
                    : batch.status === 'rolled_back'
                    ? 'warning'
                    : 'secondary'
                }`}
                style={{ textTransform: 'uppercase', fontSize: '0.75rem', letterSpacing: '0.05em' }}
              >
                {batch.status.replace('_', ' ')}
              </span>
            </div>
          </div>

          <div className="flex gap-2">
            <a
              href={`/admin/forms/${form.id}/import/template`}
              className="btn btn-secondary btn-sm"
              title="Download Excel Template"
            >
              📄 Download Template
            </a>
            {stats.invalid > 0 && (
              <a
                href={`/admin/imports/${batch.id}/errors`}
                className="btn btn-danger btn-sm"
                title="Download Error Report"
              >
                ⚠️ Error Report ({stats.invalid})
              </a>
            )}
            {batch.status === 'completed' && (
              <button
                className="btn btn-warning btn-sm"
                onClick={handleRollback}
                disabled={rollingBack}
              >
                {rollingBack ? 'Rolling back...' : '⏪ Rollback Batch'}
              </button>
            )}
          </div>
        </div>

        {/* Stats Row */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
            gap: '1rem',
            marginBottom: '1.5rem',
          }}
        >
          <div className="card" style={{ padding: '1rem', textAlign: 'center' }}>
            <span className="text-xs text-muted font-bold uppercase">Total Rows</span>
            <div style={{ fontSize: '1.75rem', fontWeight: 700, marginTop: '0.25rem' }}>
              {stats.total}
            </div>
          </div>
          <div
            className="card"
            style={{
              padding: '1rem',
              textAlign: 'center',
              borderTop: '3px solid #10b981',
            }}
          >
            <span className="text-xs font-bold uppercase" style={{ color: '#10b981' }}>
              Valid Rows
            </span>
            <div
              style={{
                fontSize: '1.75rem',
                fontWeight: 700,
                marginTop: '0.25rem',
                color: '#10b981',
              }}
            >
              {stats.valid}
            </div>
          </div>
          <div
            className="card"
            style={{
              padding: '1rem',
              textAlign: 'center',
              borderTop: '3px solid #ef4444',
            }}
          >
            <span className="text-xs font-bold uppercase" style={{ color: '#ef4444' }}>
              Invalid Rows
            </span>
            <div
              style={{
                fontSize: '1.75rem',
                fontWeight: 700,
                marginTop: '0.25rem',
                color: '#ef4444',
              }}
            >
              {stats.invalid}
            </div>
          </div>
          <div
            className="card"
            style={{
              padding: '1rem',
              textAlign: 'center',
              borderTop: '3px solid #f59e0b',
            }}
          >
            <span className="text-xs font-bold uppercase" style={{ color: '#f59e0b' }}>
              Skipped / Duplicates
            </span>
            <div
              style={{
                fontSize: '1.75rem',
                fontWeight: 700,
                marginTop: '0.25rem',
                color: '#f59e0b',
              }}
            >
              {stats.skipped}
            </div>
          </div>
          <div
            className="card"
            style={{
              padding: '1rem',
              textAlign: 'center',
              borderTop: '3px solid var(--color-primary)',
            }}
          >
            <span className="text-xs font-bold uppercase" style={{ color: 'var(--color-primary)' }}>
              Imported
            </span>
            <div
              style={{
                fontSize: '1.75rem',
                fontWeight: 700,
                marginTop: '0.25rem',
                color: 'var(--color-primary)',
              }}
            >
              {stats.imported}
            </div>
          </div>
        </div>

        {/* Commitment Banner */}
        {batch.status === 'ready' && stats.valid > 0 && (
          <div
            className="card mb-4"
            style={{
              background: 'linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(16, 185, 129, 0.08) 100%)',
              border: '1px solid rgba(79, 70, 229, 0.3)',
              padding: '1.25rem 1.5rem',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
            }}
          >
            <div>
              <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600 }}>
                Ready to Commit: {stats.valid} row{stats.valid !== 1 ? 's' : ''} verified
              </h3>
              <p className="text-muted text-sm" style={{ margin: '0.25rem 0 0' }}>
                All valid records will be atomically written to the database and batch-appended to Google Sheets.
              </p>
            </div>
            <button
              className="btn btn-primary"
              style={{ padding: '0.75rem 1.75rem', fontWeight: 600 }}
              onClick={handleCommit}
              disabled={committing}
            >
              {committing ? 'Importing...' : `🚀 Commit Import (${stats.valid} Rows)`}
            </button>
          </div>
        )}

        {/* Column Mapping Section */}
        <div className="card mb-4" style={{ padding: '1.5rem' }}>
          <div className="flex justify-between items-center mb-3">
            <div>
              <h2 style={{ fontSize: '1.15rem', fontWeight: 600, margin: 0 }}>
                1. Column Mapping & Strategy
              </h2>
              <p className="text-muted text-sm" style={{ margin: '0.2rem 0 0' }}>
                Match headers from your spreadsheet to the form fields.
              </p>
            </div>
            <button
              className="btn btn-secondary btn-sm"
              onClick={handleSaveMapping}
              disabled={savingMapping}
            >
              {savingMapping ? 'Validating...' : '⚡ Apply & Re-validate'}
            </button>
          </div>

          <form onSubmit={handleSaveMapping}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '2rem' }}>
              {/* Identity fields */}
              <div>
                <h4 className="text-xs uppercase font-bold text-muted mb-2">
                  Identity & Profile Fields
                </h4>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                  {identityFields.map((field) => (
                    <div key={field.key} className="flex items-center justify-between">
                      <label className="text-sm font-medium" style={{ minWidth: 160 }}>
                        {field.label} {field.required && <span style={{ color: '#ef4444' }}>*</span>}
                      </label>
                      <select
                        className="form-control text-sm"
                        style={{ maxWidth: 260 }}
                        value={columnMap[field.key] || ''}
                        onChange={(e) => handleMapChange(field.key, e.target.value)}
                        required={field.required}
                      >
                        <option value="">— Skip / Not in Sheet —</option>
                        {sheetHeaders.map((header) => (
                          <option key={header} value={header}>
                            {header}
                          </option>
                        ))}
                      </select>
                    </div>
                  ))}
                </div>
              </div>

              {/* Dynamic form fields */}
              <div>
                <h4 className="text-xs uppercase font-bold text-muted mb-2">
                  Dynamic Form Questions
                </h4>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                  {form.fields.map((field) => {
                    const mapKey = `field_${field.id}`;
                    return (
                      <div key={field.id} className="flex items-center justify-between">
                        <label className="text-sm font-medium" style={{ minWidth: 160 }}>
                          {field.label || field.field_key}{' '}
                          {field.is_required && <span style={{ color: '#ef4444' }}>*</span>}
                        </label>
                        <select
                          className="form-control text-sm"
                          style={{ maxWidth: 260 }}
                          value={columnMap[mapKey] || ''}
                          onChange={(e) => handleMapChange(mapKey, e.target.value)}
                        >
                          <option value="">— Skip / Not in Sheet —</option>
                          {sheetHeaders.map((header) => (
                            <option key={header} value={header}>
                              {header}
                            </option>
                          ))}
                        </select>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>

            {/* Duplicate handling settings */}
            <div
              style={{
                marginTop: '1.5rem',
                paddingTop: '1.25rem',
                borderTop: '1px solid var(--color-border)',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
              }}
            >
              <div>
                <label className="text-sm font-bold block mb-1">Duplicate Handling:</label>
                <div className="flex gap-4">
                  <label className="flex items-center gap-1 text-sm cursor-pointer">
                    <input
                      type="radio"
                      name="duplicateStrategy"
                      value="skip"
                      checked={duplicateStrategy === 'skip'}
                      onChange={(e) => setDuplicateStrategy(e.target.value)}
                    />
                    Skip duplicates (recommended)
                  </label>
                  <label className="flex items-center gap-1 text-sm cursor-pointer">
                    <input
                      type="radio"
                      name="duplicateStrategy"
                      value="update"
                      checked={duplicateStrategy === 'update'}
                      onChange={(e) => setDuplicateStrategy(e.target.value)}
                    />
                    Update existing submissions
                  </label>
                  <label className="flex items-center gap-1 text-sm cursor-pointer">
                    <input
                      type="radio"
                      name="duplicateStrategy"
                      value="create"
                      checked={duplicateStrategy === 'create'}
                      onChange={(e) => setDuplicateStrategy(e.target.value)}
                    />
                    Allow multiple entries
                  </label>
                </div>
              </div>

              <button
                type="submit"
                className="btn btn-primary"
                disabled={savingMapping}
              >
                {savingMapping ? 'Processing...' : 'Save & Re-validate'}
              </button>
            </div>
          </form>
        </div>

        {/* Data Review & Dry-Run Preview Table */}
        <div className="card" style={{ padding: '1.5rem' }}>
          <div className="flex justify-between items-center mb-3">
            <div>
              <h2 style={{ fontSize: '1.15rem', fontWeight: 600, margin: 0 }}>
                2. Data Review & Dry-Run Inspection
              </h2>
              <p className="text-muted text-sm" style={{ margin: '0.2rem 0 0' }}>
                Inspect rows, view validation errors, and correct fields inline before committing.
              </p>
            </div>

            {/* Filter Tabs */}
            <div className="flex gap-1">
              {[
                { key: 'all', label: `All (${stats.total})` },
                { key: 'valid', label: `Valid (${stats.valid})` },
                { key: 'invalid', label: `Errors (${stats.invalid})` },
                { key: 'duplicate', label: `Skipped (${stats.skipped})` },
              ].map((tab) => (
                <Link
                  key={tab.key}
                  href={`/admin/imports/${batch.id}?status=${tab.key}`}
                  preserveState
                  className={`btn btn-sm ${
                    statusFilter === tab.key ? 'btn-primary' : 'btn-ghost'
                  }`}
                >
                  {tab.label}
                </Link>
              ))}
            </div>
          </div>

          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th style={{ width: 60 }}>Row</th>
                  <th style={{ width: 100 }}>Status</th>
                  <th>Email</th>
                  <th>Cosplayer Name</th>
                  <th>Character</th>
                  <th>Series</th>
                  <th>Validation Notes</th>
                  <th style={{ width: 80 }}>Action</th>
                </tr>
              </thead>
              <tbody>
                {rows.data.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="text-center text-muted" style={{ padding: '2rem' }}>
                      No rows found matching current filter.
                    </td>
                  </tr>
                ) : (
                  rows.data.map((row) => {
                    const idn = row.normalized?.identity || {};
                    const isInvalid = row.status === 'invalid';
                    const isDup = row.status === 'duplicate' || row.status === 'skipped';
                    const isValid = row.status === 'valid';

                    return (
                      <tr
                        key={row.id}
                        style={{
                          backgroundColor: isInvalid
                            ? 'rgba(239, 68, 68, 0.05)'
                            : isDup
                            ? 'rgba(245, 158, 11, 0.05)'
                            : undefined,
                        }}
                      >
                        <td className="text-muted font-mono text-xs">#{row.row_number}</td>
                        <td>
                          <span
                            className={`badge badge-${
                              isValid ? 'success' : isInvalid ? 'danger' : 'warning'
                            }`}
                            style={{ fontSize: '0.72rem' }}
                          >
                            {row.status}
                          </span>
                        </td>
                        <td style={{ fontWeight: 500 }}>
                          {idn.email || (
                            <span className="text-muted text-xs">— unmapped —</span>
                          )}
                        </td>
                        <td>{idn.name || '—'}</td>
                        <td>{idn.character_name || '—'}</td>
                        <td>{idn.series || '—'}</td>
                        <td>
                          {row.errors ? (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                              {Object.entries(row.errors).map(([field, msgs]) => (
                                <span
                                  key={field}
                                  className="text-xs"
                                  style={{ color: '#ef4444', fontWeight: 500 }}
                                >
                                  <strong>{field}:</strong> {Array.isArray(msgs) ? msgs.join(', ') : msgs}
                                </span>
                              ))}
                            </div>
                          ) : (
                            <span className="text-muted text-xs">All checks passed ✓</span>
                          )}
                        </td>
                        <td>
                          <button
                            className="btn btn-ghost btn-sm"
                            onClick={() => openRowEditor(row)}
                            title="Edit Row"
                          >
                            ✏️ Edit
                          </button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {rows.links && rows.links.length > 3 && (
            <div className="flex justify-center gap-1 mt-4">
              {rows.links.map((link, i) => (
                <Link
                  key={i}
                  href={link.url || '#'}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                  className={`btn btn-sm ${
                    link.active ? 'btn-primary' : 'btn-ghost'
                  } ${!link.url ? 'opacity-50 pointer-events-none' : ''}`}
                />
              ))}
            </div>
          )}
        </div>

        {/* Row Edit Modal */}
        {editingRow && (
          <div
            className="modal-backdrop"
            style={{
              position: 'fixed',
              inset: 0,
              backgroundColor: 'rgba(0,0,0,0.6)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              zIndex: 9999,
              backdropFilter: 'blur(3px)',
            }}
          >
            <div
              className="card"
              style={{
                width: '100%',
                maxWidth: 600,
                maxHeight: '90vh',
                overflowY: 'auto',
                padding: '1.75rem',
              }}
            >
              <div className="flex justify-between items-center mb-4">
                <h3 style={{ margin: 0, fontSize: '1.2rem', fontWeight: 600 }}>
                  Edit Row #{editingRow.row_number}
                </h3>
                <button
                  type="button"
                  className="btn btn-ghost btn-sm"
                  onClick={() => setEditingRow(null)}
                >
                  ✕
                </button>
              </div>

              <form onSubmit={handleSaveRow}>
                <h4 className="text-xs uppercase font-bold text-muted mb-2">
                  Identity Details
                </h4>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                  <div className="form-group" style={{ gridColumn: 'span 2' }}>
                    <label className="form-label text-xs">Email Address *</label>
                    <input
                      type="email"
                      className="form-control"
                      value={rowEditForm.identity.email || ''}
                      onChange={(e) =>
                        setRowEditForm((prev) => ({
                          ...prev,
                          identity: { ...prev.identity, email: e.target.value },
                        }))
                      }
                      required
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label text-xs">Name</label>
                    <input
                      type="text"
                      className="form-control"
                      value={rowEditForm.identity.name || ''}
                      onChange={(e) =>
                        setRowEditForm((prev) => ({
                          ...prev,
                          identity: { ...prev.identity, name: e.target.value },
                        }))
                      }
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label text-xs">Character Name</label>
                    <input
                      type="text"
                      className="form-control"
                      value={rowEditForm.identity.character_name || ''}
                      onChange={(e) =>
                        setRowEditForm((prev) => ({
                          ...prev,
                          identity: { ...prev.identity, character_name: e.target.value },
                        }))
                      }
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label text-xs">Series</label>
                    <input
                      type="text"
                      className="form-control"
                      value={rowEditForm.identity.series || ''}
                      onChange={(e) =>
                        setRowEditForm((prev) => ({
                          ...prev,
                          identity: { ...prev.identity, series: e.target.value },
                        }))
                      }
                    />
                  </div>
                  <div className="form-group">
                    <label className="form-label text-xs">Experience Level</label>
                    <input
                      type="text"
                      className="form-control"
                      value={rowEditForm.identity.experience_level || ''}
                      onChange={(e) =>
                        setRowEditForm((prev) => ({
                          ...prev,
                          identity: { ...prev.identity, experience_level: e.target.value },
                        }))
                      }
                    />
                  </div>
                </div>

                <h4 className="text-xs uppercase font-bold text-muted mt-4 mb-2">
                  Form Question Values
                </h4>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                  {form.fields.map((field) => (
                    <div key={field.id} className="form-group">
                      <label className="form-label text-xs">
                        {field.label || field.field_key}{' '}
                        {field.is_required && <span style={{ color: '#ef4444' }}>*</span>}
                      </label>
                      <input
                        type="text"
                        className="form-control"
                        value={rowEditForm.values[field.id] || ''}
                        onChange={(e) =>
                          setRowEditForm((prev) => ({
                            ...prev,
                            values: { ...prev.values, [field.id]: e.target.value },
                          }))
                        }
                      />
                    </div>
                  ))}
                </div>

                <div className="flex justify-end gap-2 mt-4 pt-3 border-top">
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setEditingRow(null)}
                  >
                    Cancel
                  </button>
                  <button type="submit" className="btn btn-primary">
                    Save & Re-validate
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
