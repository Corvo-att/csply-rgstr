import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext.jsx';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.jsx';

const experienceLevels = ['beginner', 'intermediate', 'advanced', 'professional'];

export default function CosplayRegister() {
  const { cosplayer, saveCosplayer } = useAuth();
  const navigate = useNavigate();

  const [form, setForm] = useState({
    characterName:  cosplayer?.characterName || '',
    series:         cosplayer?.series        || '',
    experienceLevel: cosplayer?.experienceLevel || 'beginner',
    bio:            cosplayer?.bio           || '',
  });
  const [errors, setErrors]         = useState({});
  const [success, setSuccess]       = useState(false);
  const [submitting, setSubmitting] = useState(false);

  function validate() {
    const e = {};
    if (!form.characterName.trim()) e.characterName = 'Character name is required.';
    if (!form.series.trim())        e.series        = 'Series / source is required.';
    return e;
  }

  function handleChange(e) {
    setForm((prev)   => ({ ...prev, [e.target.name]: e.target.value }));
    setErrors((prev) => ({ ...prev, [e.target.name]: undefined }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length) { setErrors(errs); return; }
    setSubmitting(true);
    await saveCosplayer({
      character_name:   form.characterName,
      series:           form.series,
      experience_level: form.experienceLevel,
      bio:              form.bio,
    });
    setSubmitting(false);
    setSuccess(true);
    setTimeout(() => navigate('/profile-page'), 1200);
  }

  return (
    <AuthenticatedLayout>
      <div className="page-content" style={{ maxWidth: 600 }}>

        <div className="page-header">
          <h1 className="page-title">Cosplay Registration</h1>
          <p className="page-subtitle">Tell us about your character for EGYCON.</p>
        </div>

        {success && (
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
                name="characterName"
                value={form.characterName}
                onChange={handleChange}
                placeholder="e.g. Nezuko Kamado"
              />
              {errors.characterName && (
                <span className="form-error">{errors.characterName}</span>
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
                value={form.series}
                onChange={handleChange}
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
                name="experienceLevel"
                value={form.experienceLevel}
                onChange={handleChange}
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
                value={form.bio}
                onChange={handleChange}
                placeholder="Tell us about your costume, props, techniques…"
                rows={4}
              />
            </div>

            <div className="form-actions" style={{ marginTop: 0, borderTop: 'none', paddingTop: 0, justifyContent: 'flex-end' }}>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={submitting}
              >
                {submitting ? 'Saving…' : cosplayer ? 'Update Profile' : 'Complete Registration'}
              </button>
            </div>

          </form>
        </div>

      </div>
    </AuthenticatedLayout>
  );
}
