import React, { useState } from 'react';

function slugify(text) {
  return text.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
}

// Types that support choices (choices[])
const CHOICE_TYPES = ['dropdown', 'radio', 'checkbox_group', 'multiselect'];
const NUMERIC_TYPES = ['number', 'range', 'rating'];
const TEXT_TYPES = ['text', 'textarea', 'email', 'url', 'password', 'phone'];

export default function FieldEditor({ field, onSave, onCancel }) {
  const [local, setLocal] = useState(() => ({
    label: field.label || '',
    fieldKey: field.fieldKey || '',
    helpText: field.helpText || '',
    isRequired: field.isRequired || false,
    options: { ...(field.options || {}) },
    // choices as editable array
    choicesText: (field.options?.choices || []).join('\n'),
  }));

  function handleChange(e) {
    const { name, value, type, checked } = e.target;
    setLocal((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
      // auto-generate fieldKey from label if fieldKey not manually edited
      ...(name === 'label' && !field.fieldKey ? { fieldKey: slugify(value) } : {}),
    }));
  }

  function handleOptionChange(key, value) {
    setLocal((prev) => ({ ...prev, options: { ...prev.options, [key]: value } }));
  }

  function handleSave(e) {
    e.preventDefault();
    const choices = CHOICE_TYPES.includes(field.fieldType)
      ? local.choicesText.split('\n').map((c) => c.trim()).filter(Boolean)
      : undefined;

    const options = { ...local.options };
    if (choices !== undefined) options.choices = choices;

    onSave({
      label: local.label,
      fieldKey: local.fieldKey || slugify(local.label),
      helpText: local.helpText,
      isRequired: local.isRequired,
      options,
    });
  }

  const isChoice = CHOICE_TYPES.includes(field.fieldType);
  const isNumeric = NUMERIC_TYPES.includes(field.fieldType);
  const isText = TEXT_TYPES.includes(field.fieldType);

  return (
    <form onSubmit={handleSave} style={{ display: 'flex', flexDirection: 'column', gap: '0.1rem' }}>
      <h3 style={{ fontSize: '0.9rem', marginBottom: '0.75rem', color: 'var(--color-text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
        Edit Field
      </h3>

      <div className="form-group">
        <label htmlFor="fe-label">Label <span className="required-mark">*</span></label>
        <input id="fe-label" type="text" name="label" value={local.label} onChange={handleChange} placeholder="Field label…" autoFocus />
      </div>

      <div className="form-group">
        <label htmlFor="fe-key">Field Key</label>
        <input id="fe-key" type="text" name="fieldKey" value={local.fieldKey} onChange={handleChange} placeholder="auto_generated" />
        <span className="form-help">Used as the data key in submissions.</span>
      </div>

      <div className="form-group">
        <label htmlFor="fe-help">Help Text</label>
        <input id="fe-help" type="text" name="helpText" value={local.helpText} onChange={handleChange} placeholder="Optional hint for the user…" />
      </div>

      <div className="form-group">
        <label className="check-label">
          <input type="checkbox" name="isRequired" checked={local.isRequired} onChange={handleChange} />
          Required field
        </label>
      </div>

      {/* Choice-based fields */}
      {isChoice && (
        <div className="form-group">
          <label htmlFor="fe-choices">Choices <span className="required-mark">*</span></label>
          <textarea
            id="fe-choices"
            value={local.choicesText}
            onChange={(e) => setLocal((prev) => ({ ...prev, choicesText: e.target.value }))}
            rows={5}
            placeholder={'Option A\nOption B\nOption C'}
          />
          <span className="form-help">One choice per line.</span>
        </div>
      )}

      {/* Numeric fields */}
      {isNumeric && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0.5rem' }}>
          <div className="form-group">
            <label htmlFor="fe-min">Min</label>
            <input id="fe-min" type="number" value={local.options.min ?? ''} onChange={(e) => handleOptionChange('min', e.target.value)} />
          </div>
          <div className="form-group">
            <label htmlFor="fe-max">Max</label>
            <input id="fe-max" type="number" value={local.options.max ?? ''} onChange={(e) => handleOptionChange('max', e.target.value)} />
          </div>
          <div className="form-group">
            <label htmlFor="fe-step">Step</label>
            <input id="fe-step" type="number" value={local.options.step ?? ''} onChange={(e) => handleOptionChange('step', e.target.value)} />
          </div>
        </div>
      )}

      {/* Text length fields */}
      {isText && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
          <div className="form-group">
            <label htmlFor="fe-minlen">Min Length</label>
            <input id="fe-minlen" type="number" value={local.options.min_length ?? ''} onChange={(e) => handleOptionChange('min_length', e.target.value)} />
          </div>
          <div className="form-group">
            <label htmlFor="fe-maxlen">Max Length</label>
            <input id="fe-maxlen" type="number" value={local.options.max_length ?? ''} onChange={(e) => handleOptionChange('max_length', e.target.value)} />
          </div>
        </div>
      )}

      {/* Rating */}
      {field.fieldType === 'rating' && (
        <div className="form-group">
          <label htmlFor="fe-stars">Max Stars</label>
          <input id="fe-stars" type="number" min={1} max={10} value={local.options.max_stars ?? 5} onChange={(e) => handleOptionChange('max_stars', Number(e.target.value))} />
        </div>
      )}

      {/* Toggle */}
      {field.fieldType === 'toggle' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
          <div className="form-group">
            <label htmlFor="fe-on">On Label</label>
            <input id="fe-on" type="text" value={local.options.on_label ?? 'Yes'} onChange={(e) => handleOptionChange('on_label', e.target.value)} />
          </div>
          <div className="form-group">
            <label htmlFor="fe-off">Off Label</label>
            <input id="fe-off" type="text" value={local.options.off_label ?? 'No'} onChange={(e) => handleOptionChange('off_label', e.target.value)} />
          </div>
        </div>
      )}

      {/* File / Image */}
      {(field.fieldType === 'file' || field.fieldType === 'image') && (
        <div className="form-group">
          <label htmlFor="fe-maxsize">Max Size (KB)</label>
          <input id="fe-maxsize" type="number" value={local.options.max_size_kb ?? ''} onChange={(e) => handleOptionChange('max_size_kb', e.target.value)} />
        </div>
      )}

      {/* Video Upload */}
      {field.fieldType === 'video' && (
        <>
          <div className="form-group">
            <label htmlFor="fe-video-maxsize">Max Size (MB)</label>
            <input
              id="fe-video-maxsize"
              type="number"
              min={1}
              value={local.options.max_size_mb ?? ''}
              onChange={(e) => handleOptionChange('max_size_mb', e.target.value)}
              placeholder="e.g. 100"
            />
            <span className="form-help">Leave blank for no limit.</span>
          </div>
          <div className="form-group">
            <label htmlFor="fe-video-formats">Accepted Formats</label>
            <input
              id="fe-video-formats"
              type="text"
              value={local.options.accepted_formats ?? ''}
              onChange={(e) => handleOptionChange('accepted_formats', e.target.value)}
              placeholder="video/mp4,video/webm,video/ogg"
            />
            <span className="form-help">
              Comma-separated MIME types. Leave blank to allow all common video formats.
            </span>
          </div>
        </>
      )}


      {/* Section header */}
      {field.fieldType === 'section' && (
        <div className="form-group">
          <label htmlFor="fe-section-text">Section Title</label>
          <input id="fe-section-text" type="text" value={local.options.text ?? ''} onChange={(e) => handleOptionChange('text', e.target.value)} placeholder="Section heading text…" />
        </div>
      )}

      {/* Terms */}
      {field.fieldType === 'terms' && (
        <div className="form-group">
          <label htmlFor="fe-terms-text">Terms Text</label>
          <textarea id="fe-terms-text" rows={4} value={local.options.terms_text ?? ''} onChange={(e) => handleOptionChange('terms_text', e.target.value)} placeholder="Paste your terms and conditions here…" />
        </div>
      )}

      {/* Hidden default */}
      {field.fieldType === 'hidden' && (
        <div className="form-group">
          <label htmlFor="fe-hidden-default">Default Value</label>
          <input id="fe-hidden-default" type="text" value={local.options.default_value ?? ''} onChange={(e) => handleOptionChange('default_value', e.target.value)} />
        </div>
      )}

      <div className="flex gap-1 mt-2" style={{ justifyContent: 'flex-end' }}>
        <button type="button" className="btn btn-ghost btn-sm" onClick={onCancel}>Cancel</button>
        <button type="submit" className="btn btn-primary btn-sm">Save Field</button>
      </div>
    </form>
  );
}
