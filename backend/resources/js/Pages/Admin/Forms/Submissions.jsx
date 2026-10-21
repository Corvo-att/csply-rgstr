import React, { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';
import Pagination from '../../../Components/Pagination.jsx';
import SheetSyncModal from '../../../Components/Sheets/SheetSyncModal.jsx';

const MEDIA_TYPES = ['file', 'image', 'video'];
const LAYOUT_ONLY = ['section', 'instructions'];

function isUrl(val) {
  try { return Boolean(new URL(val)); } catch { return false; }
}
const isImage = (url) => /\.(png|jpe?g|gif|webp|bmp)(\?.*)?$/i.test(url);
const isVideo = (url) => /\.(mp4|webm|ogg|mov|avi|mkv)(\?.*)?$/i.test(url);

function MediaThumb({ url, processing }) {
  if (!url || !isUrl(url)) return <span className="text-muted">—</span>;

  if (isVideo(url)) {
    const label = processing?.status && processing.status !== 'ready' ? `video (${processing.status})` : 'video';
    return (
      <a href={url} target="_blank" rel="noreferrer" title={url} style={{ display: 'inline-flex', alignItems: 'center', gap: '0.3rem', fontSize: '0.82rem' }}>
        <span style={{ fontSize: '1rem' }}>▶</span> {label}
      </a>
    );
  }
  if (isImage(url)) {
    return (
      <a href={url} target="_blank" rel="noreferrer">
        <img src={url} alt="submission" loading="lazy" style={{ width: 48, height: 48, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--color-border)', verticalAlign: 'middle' }} />
      </a>
    );
  }
  return <a href={url} target="_blank" rel="noreferrer" className="text-sm">📎 file</a>;
}

export default function Submissions({ form, fields = [], submissions, filters = {}, counts = {} }) {
  const { auth, flash } = usePage().props;
  const canManage = auth?.adminUser?.can_manage;

  const [q, setQ]           = useState(filters.q || '');
  const [status, setStatus] = useState(filters.status || '');

  const columns = fields.filter((f) => !LAYOUT_ONLY.includes(f.field_type));
  const rows    = submissions.data;

  function applyFilters(next = {}) {
    const query = { q, status, ...next };
    Object.keys(query).forEach((k) => { if (!query[k]) delete query[k]; });
    router.get(`/admin/forms/${form.id}/submissions`, query, { preserveState: true, replace: true });
  }

  const exportQuery = new URLSearchParams(Object.entries({ q: filters.q, status: filters.status }).filter(([, v]) => v)).toString();
  const total = Object.values(counts).reduce((a, b) => a + Number(b), 0);

  const [showImportModal, setShowImportModal] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [sheetsModalOpen, setSheetsModalOpen] = useState(false);
  const [importSource, setImportSource] = useState('file'); // 'file' | 'google_sheet'
  const [gsSpreadsheetInput, setGsSpreadsheetInput] = useState('');
  const [gsSheetName, setGsSheetName] = useState('');
  const [gsError, setGsError] = useState('');

  const handleUpload = (e) => {
    e.preventDefault();
    if (!importFile) return;

    setUploading(true);
    const formData = new FormData();
    formData.append('file', importFile);

    router.post(`/admin/forms/${form.id}/import`, formData, {
      onFinish: () => setUploading(false),
    });
  };

  const handleGoogleSheetImport = (e) => {
    e.preventDefault();
    setGsError('');
    const raw = gsSpreadsheetInput.trim();
    if (!raw) {
      setGsError('Spreadsheet ID or URL is required');
      return;
    }
    const match = raw.match(/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/);
    const spreadsheetId = match ? match[1] : raw;

    setUploading(true);
    router.post(
      `/admin/forms/${form.id}/import/google-sheet`,
      {
        spreadsheet_id: spreadsheetId,
        sheet_name: gsSheetName.trim() || null,
      },
      {
        onError: (errs) => {
          setUploading(false);
          setGsError(errs.google || errs.spreadsheet_id || errs.sheet_name || 'Failed to connect to Google Sheet.');
        },
        onFinish: () => setUploading(false),
      }
    );
  };

  return (
    <AdminLayout>
      <div className="page-content">
        {flash?.success && <div className="alert alert-success mb-3">{flash.success}</div>}

        <div className="page-header flex justify-between items-center" style={{ flexWrap: 'wrap', gap: '0.75rem' }}>
          <div>
            <p className="text-sm text-muted mb-1">
              <Link href="/admin/events">Events</Link>
              {form?.event_id && <> / <Link href={`/admin/events/${form.event_id}`}>Event</Link></>}
              {' / '}{form.name}
            </p>
            <h1 className="page-title">Entries</h1>
            <p className="page-subtitle">
              {form.name} — {total} total ·{' '}
              {counts.pending || 0} pending · {counts.approved || 0} approved · {counts.rejected || 0} rejected
            </p>
          </div>
          <div className="flex gap-1" style={{ flexWrap: 'wrap' }}>
            {canManage && (
              <Link href={`/admin/events/${form.event_id}/forms/${form.id}/builder`}><button className="btn btn-ghost btn-sm">Builder</button></Link>
            )}
            <Link href={`/admin/forms/${form.id}/judging`}>
              <button className="btn btn-secondary btn-sm">⚖️ Judging Sheets</button>
            </Link>
            {canManage && (
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => setSheetsModalOpen(true)}
                title="Google Sheets Realtime Sync, Backfill & Pull"
              >
                📊 Google Sheets
              </button>
            )}
            {canManage && (
              <button
                className="btn btn-secondary btn-sm"
                onClick={() => setShowImportModal(true)}
              >
                📥 Import
              </button>
            )}
            <a href={`/admin/forms/${form.id}/submissions/export${exportQuery ? `?${exportQuery}` : ''}`}>
              <button className="btn btn-secondary btn-sm" disabled={total === 0}>Export CSV</button>
            </a>
          </div>
        </div>

        {/* Import Modal */}
        {showImportModal && (
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
            <div className="card" style={{ width: '100%', maxWidth: 500, padding: '1.75rem' }}>
              <div className="flex justify-between items-center mb-3">
                <h3 style={{ margin: 0, fontSize: '1.2rem', fontWeight: 600 }}>
                  Import Submissions
                </h3>
                <button
                  type="button"
                  className="btn btn-ghost btn-sm"
                  onClick={() => setShowImportModal(false)}
                >
                  ✕
                </button>
              </div>

              {/* Tabs */}
              <div className="flex gap-2 mb-3" style={{ borderBottom: '1px solid var(--color-border)', paddingBottom: '0.5rem' }}>
                <button
                  type="button"
                  className={`btn btn-sm ${importSource === 'file' ? 'btn-primary' : 'btn-ghost'}`}
                  onClick={() => { setImportSource('file'); setGsError(''); }}
                >
                  📁 Excel / CSV File
                </button>
                <button
                  type="button"
                  className={`btn btn-sm ${importSource === 'google_sheet' ? 'btn-primary' : 'btn-ghost'}`}
                  onClick={() => { setImportSource('google_sheet'); setGsError(''); }}
                >
                  📊 Google Sheet
                </button>
              </div>

              {importSource === 'file' ? (
                <>
                  <p className="text-muted text-sm mb-3">
                    Upload an <code>.xlsx</code>, <code>.xls</code>, or <code>.csv</code> file. You will be able to review, map columns, and fix validation issues before importing.
                  </p>

                  <div className="mb-4 p-3" style={{ background: 'var(--color-bg)', borderRadius: 8, border: '1px solid var(--color-border)' }}>
                    <span className="text-xs text-muted block mb-1">Need a template?</span>
                    <a
                      href={`/admin/forms/${form.id}/import/template`}
                      className="text-sm font-semibold"
                      style={{ color: 'var(--color-primary)' }}
                    >
                      📄 Download pre-formatted Excel template
                    </a>
                  </div>

                  <form onSubmit={handleUpload}>
                    <div className="form-group mb-4">
                      <label className="form-label text-sm font-medium">Select Spreadsheet File</label>
                      <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        className="form-control"
                        onChange={(e) => setImportFile(e.target.files[0] || null)}
                        required
                      />
                    </div>

                    <div className="flex justify-end gap-2">
                      <button
                        type="button"
                        className="btn btn-secondary"
                        onClick={() => setShowImportModal(false)}
                        disabled={uploading}
                      >
                        Cancel
                      </button>
                      <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={!importFile || uploading}
                      >
                        {uploading ? 'Uploading & Parsing...' : 'Next: Map Columns →'}
                      </button>
                    </div>
                  </form>
                </>
              ) : (
                <>
                  <p className="text-muted text-sm mb-3">
                    Connect directly to a live Google Sheet. We'll read the rows into an import batch so you can map columns and review entries before committing to the database.
                  </p>

                  {gsError && <div className="alert alert-danger mb-3 text-sm">{gsError}</div>}

                  <form onSubmit={handleGoogleSheetImport}>
                    <div className="form-group mb-3">
                      <label className="form-label text-sm font-medium">Google Spreadsheet URL or ID <span className="required-mark">*</span></label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="https://docs.google.com/spreadsheets/d/... or ID"
                        value={gsSpreadsheetInput}
                        onChange={(e) => setGsSpreadsheetInput(e.target.value)}
                        required
                      />
                      <span className="text-xs text-muted block mt-1">Paste the full Google Sheets URL or spreadsheet ID.</span>
                    </div>

                    <div className="form-group mb-4">
                      <label className="form-label text-sm font-medium">Sheet Tab Name (optional)</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="e.g. Sheet1, Form Responses"
                        value={gsSheetName}
                        onChange={(e) => setGsSheetName(e.target.value)}
                      />
                      <span className="text-xs text-muted block mt-1">Leave empty to use the first tab.</span>
                    </div>

                    <div className="flex justify-end gap-2">
                      <button
                        type="button"
                        className="btn btn-secondary"
                        onClick={() => setShowImportModal(false)}
                        disabled={uploading}
                      >
                        Cancel
                      </button>
                      <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={!gsSpreadsheetInput.trim() || uploading}
                      >
                        {uploading ? 'Connecting & Reading Sheet...' : 'Next: Map Columns →'}
                      </button>
                    </div>
                  </form>
                </>
              )}
            </div>
          </div>
        )}

        <form className="toolbar" onSubmit={(e) => { e.preventDefault(); applyFilters(); }}>
          <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, character, series or entry #" aria-label="Search entries" />
          <select value={status} onChange={(e) => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }} aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
          <button type="submit" className="btn btn-ghost btn-sm">Search</button>
          {(filters.q || filters.status) && (
            <button type="button" className="btn btn-ghost btn-sm" onClick={() => { setQ(''); setStatus(''); router.get(`/admin/forms/${form.id}/submissions`); }}>Clear</button>
          )}
        </form>

        {rows.length === 0 ? (
          <div className="empty-state card">
            <div className="empty-state-icon" style={{ fontSize: '1rem' }}>—</div>
            <h3>{filters.q || filters.status ? 'No entries match your filters' : 'No submissions yet'}</h3>
            <p>Entries appear here once cosplayers fill out this form.</p>
          </div>
        ) : (
          <>
            <div className="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Cosplayer</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    {columns.map((f) => <th key={f.id}>{f.label}</th>)}
                    <th style={{ width: 80 }}>Detail</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((sub) => (
                    <tr key={sub.id}>
                      <td><strong>{sub.entry_number}</strong></td>
                      <td>
                        <strong>{sub.cosplayer.name}</strong>
                        <p className="text-sm text-muted">{sub.cosplayer.character_name} — {sub.cosplayer.series}</p>
                      </td>
                      <td><span className={`badge badge-${sub.status}`}>{sub.status}</span></td>
                      <td className="text-muted">{sub.submitted_at ? new Date(sub.submitted_at).toLocaleString() : '—'}</td>
                      {columns.map((f) => {
                        const raw = sub.values?.[f.id];
                        let display;

                        if (MEDIA_TYPES.includes(f.field_type) && raw) display = <MediaThumb url={raw} processing={sub.processing?.[f.id]} />;
                        else if (raw === '1') display = 'Yes';
                        else if (raw === '0') display = 'No';
                        else display = raw ?? <span className="text-muted">—</span>;

                        return <td key={f.id} style={{ maxWidth: 260, overflowWrap: 'anywhere' }}>{display}</td>;
                      })}
                      <td>
                        <Link href={`/admin/forms/${form.id}/submissions/${sub.id}`}><button className="btn btn-ghost btn-sm">View</button></Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination links={submissions.links} />
            <p className="text-sm text-muted" style={{ textAlign: 'center', marginTop: '0.5rem' }}>
              Showing {submissions.from}–{submissions.to} of {submissions.total}
            </p>
          </>
        )}

        <SheetSyncModal
          isOpen={sheetsModalOpen}
          onClose={() => setSheetsModalOpen(false)}
          form={form}
          event={form.event || null}
        />
      </div>
    </AdminLayout>
  );
}
