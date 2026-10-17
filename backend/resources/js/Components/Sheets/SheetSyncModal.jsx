import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import ColumnMapper from './ColumnMapper.jsx';
import { openSpreadsheetPicker } from './GooglePickerHelper.js';

export default function SheetSyncModal({ isOpen, onClose, form, event, initialLink }) {
  const [loading, setLoading]       = useState(false);
  const [data, setData]             = useState(null);
  const [mode, setMode]             = useState('create'); // 'create', 'new_tab', 'existing'
  const [spreadsheetId, setSpreadsheetId]       = useState('');
  const [spreadsheetTitle, setSpreadsheetTitle] = useState('');
  const [sheetTitle, setSheetTitle]             = useState('');
  const [mappings, setMappings]                 = useState([]);
  const [autoAddNew, setAutoAddNew]             = useState(true);
  const [submitting, setSubmitting]             = useState(false);
  const [pickerError, setPickerError]           = useState(null);

  // Fetch full integration status when modal opens
  useEffect(() => {
    if (isOpen) {
      loadData();
    }
  }, [isOpen]);

  async function loadData() {
    setLoading(true);
    try {
      const res = await fetch(`/admin/forms/${form.id}/sheet`);
      if (res.ok) {
        const json = await res.json();
        setData(json);
        if (json.link) {
          setMappings(json.mappings || []);
          setAutoAddNew(Boolean(json.link.auto_add_new_fields));
          setSpreadsheetId(json.link.spreadsheet_id);
          setSpreadsheetTitle(json.link.spreadsheet_title);
          setSheetTitle(json.link.sheet_title);
        } else {
          setMappings(json.default_mappings || []);
          setSpreadsheetTitle(event ? `${event.name} — ${form.name}` : `EGYCON — ${form.name}`);
          setSheetTitle(form.name);
        }
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }

  async function handlePickSpreadsheet() {
    setPickerError(null);
    try {
      await openSpreadsheetPicker({
        onPick: (picked) => {
          setSpreadsheetId(picked.id);
          setSpreadsheetTitle(picked.name);
        },
      });
    } catch (err) {
      setPickerError(err.message || 'Google Picker could not be opened. You can paste the ID or URL below.');
    }
  }

  async function handleDetectHeaders() {
    if (!spreadsheetId || !sheetTitle) {
      alert('Please select a spreadsheet and enter the tab name first.');
      return;
    }
    setLoading(true);
    try {
      const res = await fetch(`/admin/forms/${form.id}/sheet/headers`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          spreadsheet_id: spreadsheetId,
          sheet_title:    sheetTitle,
          header_row:     1,
        }),
      });
      const resJson = await res.json();
      if (res.ok && resJson.suggested_mappings) {
        setMappings(resJson.suggested_mappings);
      } else {
        alert(resJson.error || 'Failed to detect headers from tab.');
      }
    } catch (e) {
      alert('Error detecting headers: ' + e.message);
    } finally {
      setLoading(false);
    }
  }

  function handleSaveLink(e) {
    e.preventDefault();
    setSubmitting(true);

    router.post(`/admin/forms/${form.id}/sheet`, {
      mode,
      spreadsheet_id:      spreadsheetId,
      spreadsheet_title:   spreadsheetTitle,
      sheet_title:         sheetTitle,
      mappings,
      auto_add_new_fields: autoAddNew,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setSubmitting(false);
        loadData();
      },
      onError: () => setSubmitting(false),
    });
  }

  function handleUpdateMappings() {
    setSubmitting(true);
    router.put(`/admin/forms/${form.id}/sheet/mappings`, {
      mappings,
      auto_add_new_fields:  autoAddNew,
      update_sheet_headers: true,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setSubmitting(false);
        loadData();
      },
      onError: () => setSubmitting(false),
    });
  }

  function handleToggleSync() {
    router.patch(`/admin/forms/${form.id}/sheet/toggle`, {}, {
      preserveScroll: true,
      onSuccess: loadData,
    });
  }

  function handleUnlink() {
    if (!confirm('Are you sure you want to unlink this Google Sheet? The spreadsheet and its rows will remain safe in Google Drive.')) {
      return;
    }
    router.delete(`/admin/forms/${form.id}/sheet`, {
      preserveScroll: true,
      onSuccess: loadData,
    });
  }

  if (!isOpen) return null;

  const link = data?.link || initialLink;

  return (
    <div style={{
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      backgroundColor: 'rgba(6, 11, 23, 0.75)',
      backdropFilter: 'blur(4px)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 9999,
      padding: '1.5rem',
    }}>
      <div style={{
        background: 'var(--color-surface)',
        border: '1px solid var(--color-border-2)',
        borderRadius: 'var(--radius)',
        width: '100%',
        maxWidth: '820px',
        maxHeight: '90vh',
        display: 'flex',
        flexDirection: 'column',
        boxShadow: 'var(--shadow-lg)',
      }}>
        {/* Header */}
        <div style={{
          padding: '1.25rem 1.5rem',
          borderBottom: '1px solid var(--color-border)',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
        }}>
          <div>
            <h3 style={{ fontSize: '1.2rem', fontWeight: 700, color: 'var(--color-text)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <span>📊 Google Sheets Integration</span>
              {link && (
                <span className={`badge ${link.sync_enabled ? 'badge-published' : 'badge-draft'}`} style={{ fontSize: '0.75rem' }}>
                  {link.sync_enabled ? '● Live Sync' : '○ Paused'}
                </span>
              )}
            </h3>
            <p className="text-sm text-muted">
              Synchronize submissions to Google Sheets in real-time.
            </p>
          </div>
          <button type="button" className="btn btn-ghost btn-sm" onClick={onClose} style={{ fontSize: '1.2rem', padding: '0.25rem 0.6rem' }}>
            ✕
          </button>
        </div>

        {/* Body */}
        <div style={{ padding: '1.5rem', overflowY: 'auto', flex: 1 }}>
          {loading && !data ? (
            <div style={{ textAlign: 'center', padding: '3rem 0', color: 'var(--color-text-muted)' }}>
              Loading integration status...
            </div>
          ) : !data?.connected ? (
            /* Not connected to Google */
            <div style={{
              textAlign: 'center',
              padding: '3rem 1.5rem',
              background: 'var(--color-surface-2)',
              borderRadius: 'var(--radius-sm)',
              border: '1px solid var(--color-border)',
            }}>
              <div style={{ fontSize: '2.5rem', marginBottom: '1rem' }}>🔐</div>
              <h4 style={{ fontSize: '1.1rem', fontWeight: 600, marginBottom: '0.5rem' }}>Connect Google Account</h4>
              <p className="text-sm text-muted" style={{ maxWidth: '460px', margin: '0 auto 1.5rem' }}>
                To link Google Sheets with dynamic forms, connect your Google account with Sheets & Drive permissions.
              </p>
              <a href={`/admin/google/connect?return_to=${encodeURIComponent(window.location.pathname)}`} className="btn btn-primary">
                Connect Google Account
              </a>
            </div>
          ) : link ? (
            /* Already Linked View */
            <div>
              <div style={{
                background: 'var(--color-surface-2)',
                padding: '1rem 1.25rem',
                borderRadius: 'var(--radius-sm)',
                border: '1px solid var(--color-border)',
                marginBottom: '1.5rem',
              }}>
                <div className="flex justify-between items-center mb-2">
                  <div>
                    <span style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--color-text-dim)', letterSpacing: '0.05em' }}>
                      Linked Spreadsheet
                    </span>
                    <h4 style={{ fontSize: '1.1rem', fontWeight: 700, color: 'var(--color-text)' }}>
                      {link.spreadsheet_title}
                    </h4>
                    <p className="text-sm text-muted">
                      Tab: <strong>{link.sheet_title}</strong> (GID: {link.sheet_gid})
                    </p>
                  </div>
                  <div className="flex gap-1 items-center">
                    <a
                      href={link.spreadsheet_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="btn btn-secondary btn-sm"
                    >
                      Open in Sheets ↗
                    </a>
                    <button
                      type="button"
                      className={`btn btn-sm ${link.sync_enabled ? 'btn-ghost' : 'btn-primary'}`}
                      onClick={handleToggleSync}
                    >
                      {link.sync_enabled ? 'Pause Sync' : 'Resume Sync'}
                    </button>
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      onClick={() => {
                        if (confirm('Sync all existing submissions for this form to the Google Sheet?')) {
                          router.post(`/admin/forms/${form.id}/sheet/resync`, {}, {
                            preserveScroll: true,
                            onSuccess: loadData,
                          });
                        }
                      }}
                      title="Backfill existing submissions to the sheet"
                    >
                      ↻ Backfill / Resync
                    </button>
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      style={{ color: 'var(--color-danger)' }}
                      onClick={handleUnlink}
                    >
                      Unlink
                    </button>
                  </div>
                </div>

                {link.last_synced_at && (
                  <p style={{ fontSize: '0.78rem', color: 'var(--color-text-dim)' }}>
                    Last row synced: {new Date(link.last_synced_at).toLocaleString()}
                  </p>
                )}
                {link.last_error && (
                  <p style={{ fontSize: '0.8rem', color: 'var(--color-danger)', marginTop: '0.35rem' }}>
                    Sync warning: {link.last_error}
                  </p>
                )}
              </div>

              {/* Column Mapping Section */}
              <div className="card" style={{ padding: '1.25rem', background: 'var(--color-surface)' }}>
                <div className="flex justify-between items-center mb-1">
                  <h4 style={{ fontSize: '0.95rem', fontWeight: 600 }}>Active Column Mappings</h4>
                  <label className="flex items-center gap-1 text-sm text-muted" style={{ cursor: 'pointer' }}>
                    <input
                      type="checkbox"
                      checked={autoAddNew}
                      onChange={(e) => setAutoAddNew(e.target.checked)}
                    />
                    Auto-add newly created form fields as columns
                  </label>
                </div>
                <ColumnMapper
                  mappings={mappings}
                  onChange={setMappings}
                  fields={data?.fields || []}
                  systemColumns={data?.system_columns || {}}
                />
                <div style={{ marginTop: '1.25rem', textAlign: 'right' }}>
                  <button
                    type="button"
                    className="btn btn-primary btn-sm"
                    onClick={handleUpdateMappings}
                    disabled={submitting}
                  >
                    {submitting ? 'Saving...' : 'Save & Update Sheet Headers'}
                  </button>
                </div>
              </div>
            </div>
          ) : (
            /* Unlinked View: 3-step setup */
            <form onSubmit={handleSaveLink}>
              <div className="mb-3">
                <label className="form-label" style={{ fontWeight: 600, marginBottom: '0.5rem', display: 'block' }}>
                  Choose How to Connect Google Sheets:
                </label>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '0.75rem' }}>
                  {/* Mode 1 */}
                  <div
                    onClick={() => setMode('create')}
                    style={{
                      padding: '1rem',
                      borderRadius: 'var(--radius-sm)',
                      background: mode === 'create' ? 'var(--color-surface-2)' : 'transparent',
                      border: `2px solid ${mode === 'create' ? 'var(--color-primary)' : 'var(--color-border)'}`,
                      cursor: 'pointer',
                      transition: 'var(--transition)',
                    }}
                  >
                    <div style={{ fontSize: '1.5rem', marginBottom: '0.5rem' }}>✨</div>
                    <strong style={{ fontSize: '0.9rem', display: 'block', marginBottom: '0.25rem' }}>
                      Create New Spreadsheet
                    </strong>
                    <p style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)' }}>
                      Automatically generates and styles a new spreadsheet for this form.
                    </p>
                  </div>

                  {/* Mode 2 */}
                  <div
                    onClick={() => setMode('new_tab')}
                    style={{
                      padding: '1rem',
                      borderRadius: 'var(--radius-sm)',
                      background: mode === 'new_tab' ? 'var(--color-surface-2)' : 'transparent',
                      border: `2px solid ${mode === 'new_tab' ? 'var(--color-primary)' : 'var(--color-border)'}`,
                      cursor: 'pointer',
                      transition: 'var(--transition)',
                    }}
                  >
                    <div style={{ fontSize: '1.5rem', marginBottom: '0.5rem' }}>➕</div>
                    <strong style={{ fontSize: '0.9rem', display: 'block', marginBottom: '0.25rem' }}>
                      Add New Tab
                    </strong>
                    <p style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)' }}>
                      Appends a new tab to an existing event workbook.
                    </p>
                  </div>

                  {/* Mode 3 */}
                  <div
                    onClick={() => setMode('existing')}
                    style={{
                      padding: '1rem',
                      borderRadius: 'var(--radius-sm)',
                      background: mode === 'existing' ? 'var(--color-surface-2)' : 'transparent',
                      border: `2px solid ${mode === 'existing' ? 'var(--color-primary)' : 'var(--color-border)'}`,
                      cursor: 'pointer',
                      transition: 'var(--transition)',
                    }}
                  >
                    <div style={{ fontSize: '1.5rem', marginBottom: '0.5rem' }}>🔗</div>
                    <strong style={{ fontSize: '0.9rem', display: 'block', marginBottom: '0.25rem' }}>
                      Use Existing Tab
                    </strong>
                    <p style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)' }}>
                      Link to an already created worksheet tab and match headers.
                    </p>
                  </div>
                </div>
              </div>

              {/* Mode-specific inputs */}
              <div style={{
                background: 'var(--color-surface-2)',
                padding: '1.25rem',
                borderRadius: 'var(--radius-sm)',
                border: '1px solid var(--color-border)',
                marginBottom: '1.25rem',
              }}>
                {mode === 'create' ? (
                  <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '1rem' }}>
                    <div>
                      <label className="form-label text-sm">Spreadsheet File Name</label>
                      <input
                        type="text"
                        className="form-input"
                        value={spreadsheetTitle}
                        onChange={(e) => setSpreadsheetTitle(e.target.value)}
                        required
                      />
                    </div>
                    <div>
                      <label className="form-label text-sm">Tab Name</label>
                      <input
                        type="text"
                        className="form-input"
                        value={sheetTitle}
                        onChange={(e) => setSheetTitle(e.target.value)}
                        required
                      />
                    </div>
                  </div>
                ) : (
                  <div>
                    <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'flex-end', marginBottom: '1rem' }}>
                      <div style={{ flex: 1 }}>
                        <label className="form-label text-sm">Spreadsheet ID or URL</label>
                        <input
                          type="text"
                          className="form-input"
                          placeholder="e.g. 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms or paste link"
                          value={spreadsheetId}
                          onChange={(e) => {
                            const val = e.target.value;
                            const match = val.match(/\/d\/([a-zA-Z0-9-_]+)/);
                            setSpreadsheetId(match ? match[1] : val);
                          }}
                          required
                        />
                      </div>
                      <button
                        type="button"
                        className="btn btn-secondary"
                        style={{ height: '40px' }}
                        onClick={handlePickSpreadsheet}
                      >
                        Browse Drive...
                      </button>
                    </div>

                    {pickerError && (
                      <p style={{ fontSize: '0.78rem', color: 'var(--color-warning)', marginBottom: '0.75rem' }}>
                        {pickerError}
                      </p>
                    )}

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                      <div>
                        <label className="form-label text-sm">Tab / Worksheet Name</label>
                        <input
                          type="text"
                          className="form-input"
                          value={sheetTitle}
                          onChange={(e) => setSheetTitle(e.target.value)}
                          placeholder="e.g. Submissions"
                          required
                        />
                      </div>
                      {mode === 'existing' && (
                        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                          <button
                            type="button"
                            className="btn btn-secondary w-full"
                            style={{ height: '40px' }}
                            onClick={handleDetectHeaders}
                          >
                            Auto-Detect Headers
                          </button>
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>

              {/* Column Mapping Preview */}
              <div className="card" style={{ padding: '1.25rem', background: 'var(--color-surface)' }}>
                <ColumnMapper
                  mappings={mappings}
                  onChange={setMappings}
                  fields={data?.fields || []}
                  systemColumns={data?.system_columns || {}}
                />
              </div>

              {/* Submit */}
              <div style={{ marginTop: '1.5rem', display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
                <button type="button" className="btn btn-ghost" onClick={onClose}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary" disabled={submitting}>
                  {submitting ? 'Connecting...' : 'Connect & Link Sheet'}
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
