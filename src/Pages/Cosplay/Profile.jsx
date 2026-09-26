import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext.jsx';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.jsx';
import { api } from '../../services/api.js';

const expColors = {
  beginner:     'badge-draft',
  intermediate: 'badge-active',
  advanced:     'badge-published',
  professional: 'badge-published',
};

export default function Profile() {
  const { user, cosplayer, logout } = useAuth();
  const navigate = useNavigate();

  const [events, setEvents]   = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.getEvents(false)
      .then((data) => setEvents(data))
      .catch((err)  => console.error(err))
      .finally(()   => setLoading(false));
  }, []);

  const availableForms = events.flatMap((ev) =>
    (ev.forms || [])
      .filter((f) => f.is_active)
      .map((f) => ({ ...f, eventName: ev.name, eventId: ev.id }))
  );

  function handleLogout() { logout(); navigate('/'); }

  const charName = cosplayer?.character_name || cosplayer?.characterName;
  const series   = cosplayer?.series;
  const expLevel = cosplayer?.experience_level || cosplayer?.experienceLevel;
  const bio      = cosplayer?.bio;

  return (
    <AuthenticatedLayout>
      <div className="page-content">

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
                {user?.created_at || user?.createdAt
                  ? new Date(user.created_at || user.createdAt).toLocaleDateString()
                  : '—'}
              </span>
            </div>
          </div>

          {/* Character card */}
          <div className="card">
            <div className="card-header">
              <span className="card-title">Character</span>
              <Link to="/register-cosplay">
                <button className="btn btn-ghost btn-sm">Edit</button>
              </Link>
            </div>

            {cosplayer ? (
              <>
                <div className="profile-field">
                  <span className="profile-field-label">Character</span>
                  <span className="profile-field-value">{charName}</span>
                </div>
                <div className="profile-field">
                  <span className="profile-field-label">Series</span>
                  <span className="profile-field-value">{series}</span>
                </div>
                <div className="profile-field">
                  <span className="profile-field-label">Experience Level</span>
                  <span className="profile-field-value">
                    <span className={`badge ${expColors[expLevel] || 'badge-draft'}`}>
                      {expLevel}
                    </span>
                  </span>
                </div>
                {bio && (
                  <div className="profile-field">
                    <span className="profile-field-label">Bio</span>
                    <span className="profile-field-value text-sm">{bio}</span>
                  </div>
                )}
              </>
            ) : (
              <div className="empty-state" style={{ padding: '1.25rem 0' }}>
                <p className="text-sm text-muted mb-2">No character registered yet.</p>
                <Link to="/register-cosplay">
                  <button className="btn btn-primary btn-sm">Register Cosplay</button>
                </Link>
              </div>
            )}
          </div>
        </div>

        {/* ── Available event forms ──────────────────────────────────── */}
        {loading ? (
          <div className="card" style={{ textAlign: 'center', padding: '2rem' }}>
            <div className="loading-spinner" />
            <p className="text-muted text-sm mt-2">Loading available event forms…</p>
          </div>
        ) : availableForms.length > 0 ? (
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
                  <Link to={`/events/${f.eventId}/forms/${f.id}`}>
                    <button className="btn btn-primary btn-sm w-full" disabled={!cosplayer}>
                      Fill Form
                    </button>
                  </Link>
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
