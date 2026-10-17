import React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.jsx';

const expColors = {
  beginner:     'badge-draft',
  intermediate: 'badge-active',
  advanced:     'badge-published',
  professional: 'badge-published',
};

export default function Profile({ user, cosplayer, events = [] }) {
  const { flash } = usePage().props;

  // Flatten published events → active forms for this cosplayer
  const availableForms = events.flatMap((ev) =>
    (ev.forms || [])
      .filter((f) => f.is_active)
      .map((f) => ({ ...f, eventName: ev.name, eventId: ev.id }))
  );

  function handleLogout() {
    router.post('/logout');
  }

  return (
    <AuthenticatedLayout>
      <div className="page-content">

        {/* ── Flash messages ──────────────────────────────────────────── */}
        {flash?.success && (
          <div className="alert alert-success mb-3">{flash.success}</div>
        )}
        {flash?.error && (
          <div className="alert alert-error mb-3">{flash.error}</div>
        )}

        {/* ── Page header ────────────────────────────────────────────── */}
        <div className="page-header flex justify-between items-center" style={{ flexWrap: 'wrap', gap: '0.75rem' }}>
          <div>
            <h1 className="page-title">My Profile</h1>
            <p className="page-subtitle">Your EGYCON account and cosplay details.</p>
          </div>
          <button className="btn btn-ghost btn-sm" onClick={handleLogout}>Sign Out</button>
        </div>

        {/* ── Info cards grid ────────────────────────────────────────── */}
        <div className="profile-grid mb-3">

          {/* Account card */}
          <div className="card">
            <div className="card-header">
              <span className="card-title">Account</span>
            </div>
            <div className="profile-field">
              <span className="profile-field-label">Name</span>
              <span className="profile-field-value">{user?.name}</span>
            </div>
            <div className="profile-field">
              <span className="profile-field-label">Email</span>
              <span className="profile-field-value">{user?.email}</span>
            </div>
            <div className="profile-field">
              <span className="profile-field-label">Member Since</span>
              <span className="profile-field-value">
                {user?.created_at ? new Date(user.created_at).toLocaleDateString() : '—'}
              </span>
            </div>
          </div>

          {/* Character card */}
          <div className="card">
            <div className="card-header">
              <span className="card-title">Character</span>
              <Link href="/register-cosplay">
                <button className="btn btn-ghost btn-sm">Edit</button>
              </Link>
            </div>

            {cosplayer ? (
              <>
                <div className="profile-field">
                  <span className="profile-field-label">Character</span>
                  <span className="profile-field-value">{cosplayer.character_name}</span>
                </div>
                <div className="profile-field">
                  <span className="profile-field-label">Series</span>
                  <span className="profile-field-value">{cosplayer.series}</span>
                </div>
                <div className="profile-field">
                  <span className="profile-field-label">Experience Level</span>
                  <span className="profile-field-value">
                    <span className={`badge ${expColors[cosplayer.experience_level] || 'badge-draft'}`}>
                      {cosplayer.experience_level}
                    </span>
                  </span>
                </div>
                {cosplayer.bio && (
                  <div className="profile-field">
                    <span className="profile-field-label">Bio</span>
                    <span className="profile-field-value text-sm">{cosplayer.bio}</span>
                  </div>
                )}
              </>
            ) : (
              <div className="empty-state" style={{ padding: '1.25rem 0' }}>
                <p className="text-sm text-muted mb-2">No character registered yet.</p>
                <Link href="/register-cosplay">
                  <button className="btn btn-primary btn-sm">Register Cosplay</button>
                </Link>
              </div>
            )}
          </div>
        </div>

        {/* ── Available event forms ──────────────────────────────────── */}
        {availableForms.length > 0 ? (
          <div className="card">
            <div className="card-header" style={{ marginBottom: '1rem' }}>
              <span className="card-title">Available Event Forms</span>
            </div>

            {!cosplayer && (
              <div className="alert alert-info mb-3">
                Complete your cosplay registration before submitting event forms.
              </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px,1fr))', gap: '0.85rem' }}>
              {availableForms.map((f) => (
                <div key={f.id} className="card" style={{ padding: '1rem' }}>
                  <p className="text-xs text-muted mb-1">{f.eventName}</p>
                  <p style={{ fontFamily: 'var(--font-display)', fontWeight: 600, marginBottom: '0.4rem', fontSize: '0.9375rem' }}>
                    {f.name}
                  </p>
                  {f.description && (
                    <p className="text-sm text-muted mb-2">{f.description}</p>
                  )}
                  {f.submission ? (
                    <div>
                      <span className={`badge badge-${f.submission.status}`}>
                        {f.submission.status === 'pending' ? 'Submitted — under review' : f.submission.status}
                      </span>
                      <p className="text-sm text-muted mt-1">Your entry number: <strong>#{f.submission.entry_number}</strong></p>
                    </div>
                  ) : f.closed_reason ? (
                    <p className="text-sm text-muted">{f.closed_reason}</p>
                  ) : (
                    <>
                      {f.closes_at && (
                        <p className="text-xs text-muted mb-1">Closes {new Date(f.closes_at).toLocaleString()}</p>
                      )}
                      <Link href={`/events/${f.eventId}/forms/${f.id}`}>
                        <button className="btn btn-primary btn-sm w-full" disabled={!cosplayer}>
                          Fill Form
                        </button>
                      </Link>
                    </>
                  )}
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="card" style={{ textAlign: 'center', padding: '2rem' }}>
            <p className="text-muted text-sm">No active forms available right now.</p>
          </div>
        )}

      </div>
    </AuthenticatedLayout>
  );
}
