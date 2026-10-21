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

  // Sheet Pull & Sync Controls state
  const [pullScanning, setPullScanning]         = useState(false);
  const [pullBatch, setPullBatch]               = useState(null);
  const [pullApplying, setPullApplying]         = useState(false);
  const [selectedSubmissions, setSelectedSubmissions] = useState(new Set());
  const [pullMessage, setPullMessage]           = useState('');
  const [syncingNow, setSyncingNow]             = useState(false);
  const [showResyncModal, setShowResyncModal]   = useState(false);

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

  async function handleSyncNow() {
    setSyncingNow(true);
    try {
      await fetch(`/admin/forms/${form.id}/sheet/sync-now`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json',
        },
      });
      await loadData();
    } catch (e) {
      alert('Error triggering sync: ' + e.message);
    } finally {
      setSyncingNow(false);
    }
  }

  function handleTriggerResync(mode) {
    setShowResyncModal(false);
    router.post(`/admin/forms/${form.id}/sheet/resync`, { mode }, {
      preserveScroll: true,
      onSuccess: loadData,
    });
  }

  async function handleScanPull() {
    setPullScanning(true);
    setPullMessage('');
    try {
      const res = await fetch(`/admin/forms/${form.id}/sheet/pull`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json',
        },
      });
      const resJson = await res.json();
      if (!res.ok || !resJson.success) {
        alert(resJson.error || 'Failed to scan Google Sheet for edits.');
        return;
      }
      setPullBatch(resJson.batch);
      if (resJson.batch.changes?.length) {
        setSelectedSubmissions(new Set(resJson.batch.changes.map((c) => c.submission_id)));
      } else {
        alert('Everything is up to date! No changes detected in Google Sheets.');
      }
    } catch (e) {
      alert('Error scanning sheet: ' + e.message);
    } finally {
      setPullScanning(false);
    }
  }

  async function handleApplyPull() {
    if (!pullBatch) return;
    if (selectedSubmissions.size === 0) {
      alert('Please select at least one submission to update.');
      return;
    }
    setPullApplying(true);
    try {
      const res = await fetch(`/admin/forms/${form.id}/sheet/pull/${pullBatch.id}/apply`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          selected_submission_ids: Array.from(selectedSubmissions),
        }),
      });
      const resJson = await res.json();
      if (!res.ok || !resJson.success) {
        alert(resJson.error || 'Failed to apply sheet changes.');
        return;
      }
      setPullMessage(resJson.message);
      setPullBatch(null);
      await loadData();
    } catch (e) {
      alert('Error applying sheet changes: ' + e.message);
    } finally {
      setPullApplying(false);
    }
  }

  function toggleSubmissionSelection(subId) {
    const next = new Set(selectedSubmissions);
    if (next.has(subId)) next.delete(subId);
    else next.add(subId);
    setSelectedSubmissions(next);
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
              {/* Linked Spreadsheet Card */}
              <div style={{
                background: 'var(--color-surface-2)',
                padding: '1.25rem',
                borderRadius: 'var(--radius)',
                border: '1px solid var(--color-border)',
                marginBottom: '1.25rem',
              }}>
                <div className="flex justify-between items-center mb-3" style={{ flexWrap: 'wrap', gap: '0.75rem' }}>
                  <div>
                    <span style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--color-text-dim)', letterSpacing: '0.05em', fontWeight: 600 }}>
                      Linked Spreadsheet
                    </span>
                    <h4 style={{ fontSize: '1.15rem', fontWeight: 700, color: 'var(--color-text)', margin: '0.15rem 0' }}>
                      {link.spreadsheet_title}
                    </h4>
                    <p className="text-sm text-muted">
                      Tab: <strong>{link.sheet_title}</strong> (GID: {link.sheet_gid})
                    </p>
                  </div>
                  <div className="flex gap-1 items-center" style={{ flexWrap: 'wrap' }}>
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
                      className="btn btn-secondary btn-sm"
                      onClick={() => setShowResyncModal(true)}
                      title="Sync missing rows, verify, or rebuild"
                    >
                      ↻ Resync / Rebuild
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

                {/* Resync Options Dialog */}
                {showResyncModal && (
                  <div style={{
                    background: 'var(--color-surface)',
                    border: '1px solid var(--color-border)',
                    borderRadius: 'var(--radius-sm)',
                    padding: '1rem',
                    marginBottom: '1rem',
                  }}>
                    <div className="flex justify-between items-center mb-2">
                      <strong style={{ fontSize: '0.9rem' }}>Choose Resynchronization Mode:</strong>
                      <button type="button" className="btn btn-ghost btn-xs" onClick={() => setShowResyncModal(false)}>✕</button>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '0.5rem', marginBottom: '0.5rem' }}>
                      <button
                        type="button"
                        className="btn btn-secondary btn-sm text-left"
                        onClick={() => handleTriggerResync('missing')}
                        style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', padding: '0.6rem 0.8rem' }}
                      >
                        <strong>Sync Missing</strong>
                        <span className="text-xs text-muted">Queues rows that never made it to the sheet.</span>
                      </button>
                      <button
                        type="button"
                        className="btn btn-secondary btn-sm text-left"
                        onClick={() => handleTriggerResync('verify')}
                        style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', padding: '0.6rem 0.8rem' }}
                      >
                        <strong>Verify & Repair</strong>
                        <span className="text-xs text-muted">Checks all rows, skips identical, repairs changed.</span>
                      </button>
                      <button
                        type="button"
                        className="btn btn-danger btn-sm text-left"
                        onClick={() => handleTriggerResync('rebuild')}
                        style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', padding: '0.6rem 0.8rem' }}
                      >
                        <strong>Rebuild Sheet</strong>
                        <span className="text-xs text-muted">Clears sheet data rows and writes fresh from DB.</span>
                      </button>
                    </div>
                  </div>
                )}

                {/* Real-time Sync Metrics */}
                {data?.sync_stats && (
                  <div style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))',
                    gap: '0.75rem',
                    marginTop: '0.75rem',
                    paddingTop: '0.75rem',
                    borderTop: '1px solid var(--color-border)',
                  }}>
                    <div style={{ background: 'var(--color-surface)', padding: '0.6rem 0.8rem', borderRadius: 6, border: '1px solid var(--color-border)' }}>
                      <span className="text-xs text-muted block">Database Entries</span>
                      <strong style={{ fontSize: '1.1rem' }}>{data.sync_stats.total_submissions}</strong>
                    </div>
                    <div style={{ background: 'var(--color-surface)', padding: '0.6rem 0.8rem', borderRadius: 6, border: '1px solid var(--color-border)' }}>
                      <span className="text-xs text-muted block">Synced in Sheet</span>
                      <strong style={{ fontSize: '1.1rem', color: 'var(--color-success)' }}>{data.sync_stats.synced}</strong>
                    </div>
                    <div style={{ background: 'var(--color-surface)', padding: '0.6rem 0.8rem', borderRadius: 6, border: '1px solid var(--color-border)' }}>
                      <span className="text-xs text-muted block">Pending Flush</span>
                      <strong style={{ fontSize: '1.1rem', color: data.sync_stats.pending > 0 ? 'var(--color-warning)' : 'inherit' }}>
                        {data.sync_stats.pending}
                      </strong>
                    </div>
                    <div style={{ background: 'var(--color-surface)', padding: '0.6rem 0.8rem', borderRadius: 6, border: '1px solid var(--color-border)' }}>
                      <span className="text-xs text-muted block">Failed / Retry</span>
                      <strong style={{ fontSize: '1.1rem', color: data.sync_stats.failed > 0 ? 'var(--color-danger)' : 'inherit' }}>
                        {data.sync_stats.failed}
                      </strong>
                    </div>
                  </div>
                )}

                <div className="flex justify-between items-center mt-3 pt-2" style={{ borderTop: '1px solid var(--color-border)' }}>
                  <div>
                    {link.last_synced_at && (
                      <span style={{ fontSize: '0.78rem', color: 'var(--color-text-dim)' }}>
                        Last synced: {new Date(link.last_synced_at).toLocaleString()}
                      </span>
                    )}
                    {link.last_error && (
                      <p style={{ fontSize: '0.8rem', color: 'var(--color-danger)', margin: '0.2rem 0 0' }}>
                        Warning: {link.last_error}
                      </p>
                    )}
                  </div>
                  {data?.sync_stats?.pending > 0 && (
                    <button
                      type="button"
                      className="btn btn-primary btn-xs"
                      onClick={handleSyncNow}
                      disabled={syncingNow}
                    >
                      {syncingNow ? 'Flushing…' : 'Flush Pending Now'}
                    </button>
                  )}
                </div>
              </div>

              {/* Sheet Pull Section (Sheet -> Database Edits) */}
              <div style={{
                background: 'var(--color-surface-2)',
                padding: '1.25rem',
                borderRadius: 'var(--radius)',
                border: '1px solid var(--color-border)',
                marginBottom: '1.25rem',
              }}>
                <div className="flex justify-between items-center mb-2" style={{ flexWrap: 'wrap', gap: '0.5rem' }}>
                  <div>
                    <h4 style={{ fontSize: '1rem', fontWeight: 700, margin: 0, display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                      <span>📥 Pull Edits from Sheet</span>
                    </h4>
                    <p className="text-xs text-muted" style={{ margin: '0.2rem 0 0' }}>
                      Review changes made directly in Google Sheets and safely update the database with revision history.
                    </p>
                  </div>
                  <button
                    type="button"
                    className="btn btn-secondary btn-sm"
                    onClick={handleScanPull}
                    disabled={pullScanning}
                  >
                    {pullScanning ? 'Scanning Google Sheet…' : '🔍 Scan Sheet for Edits'}
                  </button>
                </div>

                {pullMessage && (
                  <div className="alert alert-success mt-2 mb-2 text-sm">{pullMessage}</div>
                )}

                {/* Pull Review Diff Preview */}
                {pullBatch && pullBatch.changes && (
                  <div style={{
                    marginTop: '1rem',
                    background: 'var(--color-surface)',
                    border: '1px solid var(--color-border)',
                    borderRadius: 'var(--radius-sm)',
                    padding: '1rem',
                  }}>
                    <div className="flex justify-between items-center mb-3">
                      <div>
                        <strong style={{ fontSize: '0.95rem' }}>
                          {pullBatch.changes.length} submission(s) have cell changes in Google Sheets
                        </strong>
                        <p className="text-xs text-muted">
                          {pullBatch.stats?.total_field_changes} field(s) edited. Uncheck any submissions you don't want to import.
                        </p>
                      </div>
                      <div className="flex gap-1 items-center">
                        <button
                          type="button"
                          className="btn btn-ghost btn-xs"
                          onClick={() => setPullBatch(null)}
                        >
                          Dismiss
                        </button>
                        <button
                          type="button"
                          className="btn btn-primary btn-sm"
                          onClick={handleApplyPull}
                          disabled={pullApplying || selectedSubmissions.size === 0}
                        >
                          {pullApplying ? 'Applying…' : `Apply ${selectedSubmissions.size} Edits to DB`}
                        </button>
                      </div>
                    </div>

                    <div style={{ maxHeight: 320, overflowY: 'auto' }}>
                      <table className="table" style={{ fontSize: '0.82rem', width: '100%' }}>
                        <thead>
                          <tr>
                            <th style={{ width: 36 }}></th>
                            <th>Entry</th>
                            <th>Field</th>
                            <th>Current (Database)</th>
                            <th></th>
                            <th>Edited (Google Sheet)</th>
                          </tr>
                        </thead>
                        <tbody>
                          {pullBatch.changes.map((item) => (
                            <React.Fragment key={item.submission_id}>
                              {item.diffs.map((diff, dIdx) => (
                                <tr key={`${item.submission_id}-${dIdx}`}>
                                  {dIdx === 0 ? (
                                    <td rowSpan={item.diffs.length} style={{ verticalAlign: 'middle' }}>
                                      <input
                                        type="checkbox"
                                        checked={selectedSubmissions.has(item.submission_id)}
                                        onChange={() => toggleSubmissionSelection(item.submission_id)}
                                      />
                                    </td>
                                  ) : null}
                                  {dIdx === 0 ? (
                                    <td rowSpan={item.diffs.length} style={{ verticalAlign: 'middle', fontWeight: 600 }}>
                                      #{item.entry_number} {item.cosplayer_name}
                                      <div className="text-xs text-muted">Row {item.sheet_row}</div>
                                    </td>
                                  ) : null}
                                  <td style={{ fontWeight: 500 }}>{diff.label}</td>
                                  <td style={{ color: 'var(--color-danger)', maxWidth: 160, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                    {diff.old || <span className="text-muted">(empty)</span>}
                                  </td>
                                  <td style={{ width: 24, textAlign: 'center', color: 'var(--color-text-dim)' }}>→</td>
                                  <td style={{ color: 'var(--color-success)', fontWeight: 600, maxWidth: 160, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                    {diff.new || <span className="text-muted">(cleared)</span>}
                                  </td>
                                </tr>
                              ))}
                            </React.Fragment>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
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
