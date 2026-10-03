import React from 'react';
import UploadField from './UploadField.jsx';

// Renders one form field based on field.fieldType
export default function DynamicField({ field, value, onChange, error }) {
  const { fieldType, label, helpText, isRequired, options = {} } = field;

  const id = `field-${field.id}`;

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
  // Plain text only (line breaks kept). Never render admin-entered text as HTML.
  if (fieldType === 'instructions') {
    const body = options.text || options.html || '';
    return (
      <div className="alert alert-info" style={{ marginBottom: '0.5rem' }}>
        {label && <strong style={{ display: 'block', marginBottom: body ? '0.25rem' : 0 }}>{label}</strong>}
        {body && <span style={{ whiteSpace: 'pre-wrap' }}>{body}</span>}
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

  // ── File / Image / Video upload ───────────────────────────────────────────
  if (fieldType === 'file' || fieldType === 'image' || fieldType === 'video') {
    return <UploadField field={field} value={value} onChange={onChange} error={error} />;
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
