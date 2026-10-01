import React from 'react';
import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.jsx';

const experienceLevels = ['beginner', 'intermediate', 'advanced', 'professional'];

export default function CosplayRegister({ cosplayer }) {
  const { data, setData, post, processing, errors, wasSuccessful } = useForm({
    character_name:   cosplayer?.character_name   || '',
    series:           cosplayer?.series           || '',
    experience_level: cosplayer?.experience_level || 'beginner',
    bio:              cosplayer?.bio              || '',
  });

  function handleSubmit(e) {
    e.preventDefault();
    post('/register-cosplay');
  }

  return (
    <AuthenticatedLayout>
      <div className="page-content" style={{ maxWidth: 600 }}>

        <div className="page-header">
          <h1 className="page-title">Cosplay Registration</h1>
          <p className="page-subtitle">Tell us about your character for EGYCON.</p>
        </div>

        {wasSuccessful && (
          <div className="alert alert-success">
            Profile saved! Redirecting…
          </div>
        )}

        <div className="card fade-up">
          <form onSubmit={handleSubmit} noValidate>

            <div className="form-group">
              <label htmlFor="character-name">
                Character Name <span className="required-mark">*</span>
              </label>
              <input
                id="character-name"
                type="text"
                name="character_name"
                value={data.character_name}
                onChange={(e) => setData('character_name', e.target.value)}
                placeholder="e.g. Nezuko Kamado"
              />
              {errors.character_name && (
                <span className="form-error">{errors.character_name}</span>
              )}
            </div>

            <div className="form-group">
              <label htmlFor="series">
                Series / Source <span className="required-mark">*</span>
              </label>
              <input
                id="series"
                type="text"
                name="series"
                value={data.series}
                onChange={(e) => setData('series', e.target.value)}
                placeholder="e.g. Demon Slayer"
              />
              {errors.series && (
                <span className="form-error">{errors.series}</span>
              )}
            </div>

            <div className="form-group">
              <label htmlFor="experience-level">Experience Level</label>
              <select
                id="experience-level"
                name="experience_level"
                value={data.experience_level}
                onChange={(e) => setData('experience_level', e.target.value)}
              >
                {experienceLevels.map((lvl) => (
                  <option key={lvl} value={lvl}>
                    {lvl.charAt(0).toUpperCase() + lvl.slice(1)}
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="bio">Bio / About your Costume</label>
              <textarea
                id="bio"
                name="bio"
                value={data.bio}
                onChange={(e) => setData('bio', e.target.value)}
                placeholder="Tell us about your costume, props, techniques…"
                rows={4}
              />
            </div>

            <div className="form-actions" style={{ marginTop: 0, borderTop: 'none', paddingTop: 0, justifyContent: 'flex-end' }}>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={processing}
              >
                {processing ? 'Saving…' : cosplayer ? 'Update Profile' : 'Complete Registration'}
              </button>
            </div>

          </form>
        </div>

      </div>
    </AuthenticatedLayout>
  );
}
