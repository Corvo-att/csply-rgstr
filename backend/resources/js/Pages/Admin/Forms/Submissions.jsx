import React, { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout.jsx';
import Pagination from '../../../Components/Pagination.jsx';

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
          <div className="flex gap-1">
            {canManage && (
              <Link href={`/admin/events/${form.event_id}/forms/${form.id}/builder`}><button className="btn btn-ghost btn-sm">Builder</button></Link>
            )}
            <a href={`/admin/forms/${form.id}/submissions/export${exportQuery ? `?${exportQuery}` : ''}`}>
              <button className="btn btn-secondary" disabled={total === 0}>Export CSV</button>
            </a>
          </div>
        </div>

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
      </div>
    </AdminLayout>
  );
}
