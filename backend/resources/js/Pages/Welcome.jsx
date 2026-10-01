import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import GuestLayout from '../Layouts/GuestLayout.jsx';

export default function Welcome({ events = [] }) {
  const features = [
    {
      num: '01',
      title: 'Register Your Cosplay',
      desc: 'Submit your character, series, and experience level to be part of the official roster.',
    },
    {
      num: '02',
      title: 'Join Events & Workshops',
      desc: 'Sign up for photo-ops, contests, and workshops — all in one place.',
    },
    {
      num: '03',
      title: 'Show Off Your Craft',
      desc: 'Get recognised by judges, photographers, and fellow cosplayers across Egypt.',
    },
  ];

  return (
    <GuestLayout>
      <div className="container">

        {/* ── Hero ──────────────────────────────────────────────────────── */}
        <section className="landing-hero">
          <div className="hero-eyebrow">
            Egypt's Premier Anime Convention
          </div>

          <h1>
            Where Cosplay<br />
            Meets <span className="hero-highlight">Community</span>
          </h1>

          <p>
            Register your character, sign up for events, and be part
            of Egypt's largest cosplay gathering.
          </p>

          <div className="hero-ctas">
            <Link href="/register">
              <button className="btn btn-primary btn-lg">Get Started</button>
            </Link>
            <Link href="/login">
              <button className="btn btn-ghost btn-lg">Sign In</button>
            </Link>
          </div>
        </section>

        {/* ── Live Convention Events ─────────────────────────────────────── */}
        {events.length > 0 && (
          <section className="mb-5">
            <div className="section-header">
              <p className="features-label">Now Open for Registration</p>
              <h2>Convention Events</h2>
            </div>

            <div className="events-grid">
              {events.map((ev) => (
                <div key={ev.id} className="event-card">
                  <div className="flex justify-between items-center">
                    <h3 style={{ fontFamily: 'var(--font-display)', fontSize: '1.05rem', fontWeight: 600 }}>
                      {ev.name}
                    </h3>
                    <span className="badge badge-published">Open</span>
                  </div>

                  {ev.description && (
                    <p className="text-sm text-muted">{ev.description}</p>
                  )}

                  <div className="event-meta">
                    {ev.location && <div>📍 {ev.location}</div>}
                    {ev.starts_at && (
                      <div>
                        📅 {new Date(ev.starts_at).toLocaleDateString()}
                        {ev.ends_at ? ` → ${new Date(ev.ends_at).toLocaleDateString()}` : ''}
                      </div>
                    )}
                  </div>

                  <div style={{ marginTop: 'auto', paddingTop: '0.75rem', borderTop: '1px solid var(--color-border)' }}>
                    <Link href="/register">
                      <button className="btn btn-primary btn-sm w-full">Register to Participate</button>
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}

        {/* ── Features ──────────────────────────────────────────────────── */}
        <section className="landing-features">
          <p className="features-label">Everything you need</p>
          <div className="features-grid">
            {features.map((f) => (
              <div key={f.num} className="feature-card">
                <div className="feature-number">{f.num}</div>
                <h3 className="feature-title">{f.title}</h3>
                <p className="feature-desc">{f.desc}</p>
              </div>
            ))}
          </div>
        </section>

      </div>
    </GuestLayout>
  );
}
