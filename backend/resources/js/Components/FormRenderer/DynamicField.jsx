import React, { useState } from 'react';

// Renders one form field based on field.fieldType
export default function DynamicField({ field, value, onChange, error }) {
  const { fieldType, label, helpText, isRequired, options = {} } = field;

  const id = `field-${field.id}`;

  // Video upload state (must be declared unconditionally per React rules)
  const [videoPreviewUrl, setVideoPreviewUrl] = useState(null);

  const labelEl = (
    <label htmlFor={id}>
      {label}
      {isRequired && <span className="required-mark"> *</span>}
    </label>
  );

  const helpEl = helpText ? <span className="form-help">{helpText}</span> : null;
  const errorEl = error ? <span className="form-error">{error}</span> : null;

  // ── Section Header (layout only, no input) ─────────────────────────────────
  if (fieldType === 'section') {
    return (
      <div className="section-header-field">
        <h3>{options.text || label}</h3>
      </div>
    );
  }

  // ── Static Instructions ────────────────────────────────────────────────────
  if (fieldType === 'instructions') {
    return (
      <div className="alert alert-info" style={{ marginBottom: '0.5rem' }}>
        <span dangerouslySetInnerHTML={{ __html: options.html || label }} />
      </div>
    );
  }

  // ── Hidden ─────────────────────────────────────────────────────────────────
  if (fieldType === 'hidden') {
    return <input type="hidden" id={id} value={options.default_value || value || ''} onChange={() => {}} />;
  }

  // ── Textarea ───────────────────────────────────────────────────────────────
  if (fieldType === 'textarea') {
    return (
      <div className="form-group">
        {labelEl}
        <textarea
          id={id}
          value={value || ''}
          onChange={(e) => onChange(e.target.value)}
          rows={options.rows || 4}
          maxLength={options.max_length || undefined}
          placeholder={helpText || ''}
        />
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Dropdown (single select) ───────────────────────────────────────────────
  if (fieldType === 'dropdown') {
    const choices = options.choices || [];
    return (
      <div className="form-group">
        {labelEl}
        <select id={id} value={value || ''} onChange={(e) => onChange(e.target.value)}>
          <option value="">— Select —</option>
          {choices.map((c) => <option key={c} value={c}>{c}</option>)}
        </select>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Multi-select ───────────────────────────────────────────────────────────
  if (fieldType === 'multiselect') {
    const choices = options.choices || [];
    const selected = Array.isArray(value) ? value : [];
    function toggle(choice) {
      onChange(selected.includes(choice) ? selected.filter((c) => c !== choice) : [...selected, choice]);
    }
    return (
      <div className="form-group">
        {labelEl}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
          {choices.map((c) => (
            <label key={c} className="check-label">
              <input type="checkbox" checked={selected.includes(c)} onChange={() => toggle(c)} />
              {c}
            </label>
          ))}
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Radio group ────────────────────────────────────────────────────────────
  if (fieldType === 'radio') {
    const choices = options.choices || [];
    return (
      <div className="form-group">
        {labelEl}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
          {choices.map((c) => (
            <label key={c} className="check-label">
              <input type="radio" name={id} value={c} checked={value === c} onChange={() => onChange(c)} />
              {c}
            </label>
          ))}
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Checkbox (single boolean) ──────────────────────────────────────────────
  if (fieldType === 'checkbox') {
    return (
      <div className="form-group">
        <label className="check-label" htmlFor={id}>
          <input type="checkbox" id={id} checked={value === '1' || value === true} onChange={(e) => onChange(e.target.checked ? '1' : '0')} />
          {label}{isRequired && <span className="required-mark"> *</span>}
        </label>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Checkbox Group ────────────────────────────────────────────────────────
  if (fieldType === 'checkbox_group') {
    const choices = options.choices || [];
    const selected = Array.isArray(value) ? value : [];
    function toggle(choice) {
      onChange(selected.includes(choice) ? selected.filter((c) => c !== choice) : [...selected, choice]);
    }
    return (
      <div className="form-group">
        {labelEl}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
          {choices.map((c) => (
            <label key={c} className="check-label">
              <input type="checkbox" checked={selected.includes(c)} onChange={() => toggle(c)} />
              {c}
            </label>
          ))}
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Toggle / Switch ────────────────────────────────────────────────────────
  if (fieldType === 'toggle') {
    const isOn = value === '1' || value === true;
    const onLabel = options.on_label || 'Yes';
    const offLabel = options.off_label || 'No';
    return (
      <div className="form-group">
        {labelEl}
        <div
          className="toggle-switch"
          onClick={() => onChange(isOn ? '0' : '1')}
          role="switch"
          aria-checked={isOn}
          tabIndex={0}
          onKeyDown={(e) => e.key === ' ' && onChange(isOn ? '0' : '1')}
        >
          <div className={`toggle-track${isOn ? ' on' : ''}`}>
            <div className="toggle-thumb" />
          </div>
          <span>{isOn ? onLabel : offLabel}</span>
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Range / Slider ────────────────────────────────────────────────────────
  if (fieldType === 'range') {
    return (
      <div className="form-group">
        {labelEl}
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          <input
            id={id}
            type="range"
            min={options.min ?? 0}
            max={options.max ?? 100}
            step={options.step ?? 1}
            value={value ?? options.min ?? 0}
            onChange={(e) => onChange(e.target.value)}
            style={{ flex: 1 }}
          />
          <span style={{ minWidth: 32, textAlign: 'right' }}>{value ?? options.min ?? 0}</span>
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Rating (stars) ────────────────────────────────────────────────────────
  if (fieldType === 'rating') {
    const max = options.max_stars || 5;
    return (
      <div className="form-group">
        {labelEl}
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          {Array.from({ length: max }, (_, i) => i + 1).map((star) => (
            <button
              key={star}
              type="button"
              onClick={() => onChange(String(star))}
              style={{
                background: 'none',
                border: 'none',
                fontSize: '1.5rem',
                cursor: 'pointer',
                color: Number(value) >= star ? '#f0a500' : 'var(--color-border)',
              }}
            >
              ★
            </button>
          ))}
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Terms & Conditions ────────────────────────────────────────────────────
  if (fieldType === 'terms') {
    return (
      <div className="form-group">
        {options.terms_text && (
          <div className="card" style={{ padding: '0.75rem', maxHeight: 150, overflowY: 'auto', marginBottom: '0.5rem', fontSize: '0.85rem', color: 'var(--color-text-muted)' }}>
            {options.terms_text}
          </div>
        )}
        <label className="check-label" htmlFor={id}>
          <input type="checkbox" id={id} checked={value === '1'} onChange={(e) => onChange(e.target.checked ? '1' : '0')} />
          I agree to the terms and conditions{isRequired && <span className="required-mark"> *</span>}
        </label>
        {errorEl}
      </div>
    );
  }

  // ── Color Picker ──────────────────────────────────────────────────────────
  if (fieldType === 'color') {
    return (
      <div className="form-group">
        {labelEl}
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          <input id={id} type="color" value={value || '#6c63ff'} onChange={(e) => onChange(e.target.value)} style={{ width: 48, height: 36, padding: 0 }} />
          <span className="text-sm text-muted">{value || '#6c63ff'}</span>
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── File / Image upload ───────────────────────────────────────────────────
  if (fieldType === 'file' || fieldType === 'image') {
    // Derive the effective byte limit (KB takes priority over MB if both set)
    const maxBytes = options.max_size_kb
      ? Number(options.max_size_kb) * 1024
      : options.max_size_mb
        ? Number(options.max_size_mb) * 1024 * 1024
        : null;

    const limitLabel = options.max_size_kb
      ? `Max ${options.max_size_kb} KB`
      : options.max_size_mb
        ? `Max ${options.max_size_mb} MB`
        : null;

    function handleFileChange(e) {
      const file = e.target.files?.[0];
      if (!file) { onChange(null); return; }
      if (maxBytes && file.size > maxBytes) {
        alert(`File exceeds the maximum allowed size (${limitLabel}).`);
        e.target.value = '';
        onChange(null);
        return;
      }
      onChange(file);
    }

    // Build accept string
    const accept = fieldType === 'image'
      ? 'image/*'
      : options.accepted_formats || undefined;

    return (
      <div className="form-group">
        {labelEl}
        <input
          id={id}
          type="file"
          accept={accept}
          onChange={handleFileChange}
        />
        {limitLabel && (
          <span className="form-help">{limitLabel}{helpText ? `. ${helpText}` : ''}</span>
        )}
        {!limitLabel && helpEl}
        {errorEl}
      </div>
    );
  }

  // ── Video Upload ──────────────────────────────────────────────────────────
  if (fieldType === 'video') {
    function handleVideoChange(e) {
      const file = e.target.files?.[0];
      if (!file) { setVideoPreviewUrl(null); onChange(null); return; }

      // Client-side size guard
      if (options.max_size_mb && file.size > options.max_size_mb * 1024 * 1024) {
        alert(`Video exceeds the maximum allowed size of ${options.max_size_mb} MB.`);
        e.target.value = '';
        setVideoPreviewUrl(null);
        onChange(null);
        return;
      }

      const url = URL.createObjectURL(file);
      setVideoPreviewUrl(url);
      onChange(file);
    }

    // Accepted MIME types
    const accepted = options.accepted_formats
      ? options.accepted_formats   // e.g. "video/mp4,video/webm"
      : 'video/mp4,video/webm,video/ogg,video/quicktime,video/x-msvideo';

    return (
      <div className="form-group">
        {labelEl}

        {/* Drop-zone style wrapper */}
        <label
          htmlFor={id}
          style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: '0.5rem',
            border: '1px dashed var(--color-border-2)',
            borderRadius: 'var(--radius)',
            padding: '1.5rem 1rem',
            cursor: 'pointer',
            transition: 'border-color var(--transition)',
            background: 'var(--color-surface-2)',
          }}
          onDragOver={(e) => { e.preventDefault(); e.currentTarget.style.borderColor = 'var(--color-primary)'; }}
          onDragLeave={(e) => { e.currentTarget.style.borderColor = ''; }}
          onDrop={(e) => {
            e.preventDefault();
            e.currentTarget.style.borderColor = '';
            const file = e.dataTransfer.files?.[0];
            if (file) {
              const fakeEvent = { target: { files: [file], value: '' } };
              handleVideoChange(fakeEvent);
            }
          }}
        >
          {/* Upload icon */}
          <span style={{
            width: 40, height: 40, borderRadius: '50%',
            background: 'rgba(26,155,138,0.1)',
            border: '1px solid rgba(26,155,138,0.2)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontFamily: 'var(--font-display)', fontSize: '1.1rem',
            color: 'var(--color-primary)',
          }}>&#9654;</span>

          <span style={{ fontSize: '0.86rem', color: 'var(--color-text-muted)' }}>
            {videoPreviewUrl ? 'Replace video' : 'Click or drag & drop a video file'}
          </span>

          {options.max_size_mb && (
            <span className="text-xs text-muted">Max {options.max_size_mb} MB</span>
          )}

          {/* Accepted format pills */}
          {options.accepted_formats && (
            <div style={{ display: 'flex', gap: '0.3rem', flexWrap: 'wrap', justifyContent: 'center' }}>
              {options.accepted_formats.split(',').map((f) => (
                <span key={f} style={{
                  fontSize: '0.7rem', padding: '0.1rem 0.5rem',
                  border: '1px solid var(--color-border-2)',
                  borderRadius: 999, color: 'var(--color-text-dim)',
                }}>
                  {f.replace('video/', '')}
                </span>
              ))}
            </div>
          )}

          <input
            id={id}
            type="file"
            accept={accepted}
            onChange={handleVideoChange}
            style={{ display: 'none' }}
          />
        </label>

        {/* Preview player */}
        {videoPreviewUrl && (
          <video
            src={videoPreviewUrl}
            controls
            style={{
              marginTop: '0.75rem',
              width: '100%',
              maxHeight: 240,
              borderRadius: 'var(--radius)',
              border: '1px solid var(--color-border)',
              background: '#000',
            }}
          />
        )}

        {helpText && <span className="form-help">{helpText}</span>}
        {errorEl}
      </div>
    );
  }

  // ── Signature Pad (stub — shows textarea) ────────────────────────────────
  if (fieldType === 'signature') {
    return (
      <div className="form-group">
        {labelEl}
        <div style={{ border: '1px dashed var(--color-border)', borderRadius: 'var(--radius)', padding: '1.5rem', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: '0.9rem' }}>
          Signature pad (available in full version)
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Rich Text (stub) ──────────────────────────────────────────────────────
  if (fieldType === 'richtext') {
    return (
      <div className="form-group">
        {labelEl}
        <textarea id={id} value={value || ''} onChange={(e) => onChange(e.target.value)} rows={5} placeholder="Enter rich text (WYSIWYG editor loads in full version)" />
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Address (composite) ───────────────────────────────────────────────────
  if (fieldType === 'address') {
    const addr = typeof value === 'object' && value !== null ? value : {};
    function setAddr(key, val) {
      onChange({ ...addr, [key]: val });
    }
    return (
      <div className="form-group">
        {labelEl}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
          {['street', 'city', 'state', 'zip', 'country'].map((sub) => (
            <input key={sub} type="text" placeholder={sub.charAt(0).toUpperCase() + sub.slice(1)} value={addr[sub] || ''} onChange={(e) => setAddr(sub, e.target.value)} />
          ))}
        </div>
        {helpEl}{errorEl}
      </div>
    );
  }

  // ── Default: native HTML inputs (text, email, number, date, time, url, tel, password, datetime-local) ──
  const inputTypeMap = {
    text: 'text', email: 'email', number: 'number', date: 'date', time: 'time',
    url: 'url', phone: 'tel', password: 'password', 'datetime-local': 'datetime-local',
  };

  return (
    <div className="form-group">
      {labelEl}
      <input
        id={id}
        type={inputTypeMap[fieldType] || 'text'}
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
        min={options.min ?? undefined}
        max={options.max ?? undefined}
        step={options.step ?? undefined}
        minLength={options.min_length ?? undefined}
        maxLength={options.max_length ?? undefined}
        placeholder={helpText || ''}
      />
      {helpEl}{errorEl}
    </div>
  );
}
