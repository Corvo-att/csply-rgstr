import React, { useState } from 'react';

function slugify(text) {
  return text.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
}

// Types that support choices (choices[])
const CHOICE_TYPES  = ['dropdown', 'radio', 'checkbox_group', 'multiselect'];
const NUMERIC_TYPES = ['number', 'range', 'rating'];
const TEXT_TYPES    = ['text', 'textarea', 'email', 'url', 'password', 'phone'];
const UPLOAD_TYPES  = ['file', 'image', 'video'];

const DEFAULT_LIMIT_MB = { image: 10, video: 500, file: 25 };

export default function FieldEditor({ field, onSave, onCancel, uploadLimitMb }) {
  const [local, setLocal] = useState(() => ({
    label: field.label || '',
    fieldKey: field.fieldKey || '',
    helpText: field.helpText || '',
    isRequired: field.isRequired || false,
    options: { ...(field.options || {}) },
    // choices as editable array
    choicesText: (field.options?.choices || []).join('\n'),
  }));
  const [error, setError] = useState('');

  const type      = field.fieldType;
  const isChoice  = CHOICE_TYPES.includes(type);
  const isNumeric = NUMERIC_TYPES.includes(type);
  const isText    = TEXT_TYPES.includes(type);
  const isUpload  = UPLOAD_TYPES.includes(type);
  const isLayout  = type === 'section' || type === 'instructions';

  function handleChange(e) {
    const { name, value, type: inputType, checked } = e.target;
    setLocal((prev) => ({
      ...prev,
      [name]: inputType === 'checkbox' ? checked : value,
      // auto-generate fieldKey from label if fieldKey not manually edited
      ...(name === 'label' && !field.fieldKey ? { fieldKey: slugify(value) } : {}),
    }));
  }

  function setOption(key, value) {
    setLocal((prev) => ({ ...prev, options: { ...prev.options, [key]: value } }));
  }

  function handleSave(e) {
    e.preventDefault();

    const options = { ...local.options };

    if (isChoice) {
      options.choices = local.choicesText.split('\n').map((c) => c.trim()).filter(Boolean);
      if (options.choices.length === 0) {
        setError('Add at least one choice (one per line).');
        return;
      }
    }

    // blank inputs mean "not set" - do not store empty strings
    Object.keys(options).forEach((k) => { if (options[k] === '' || options[k] === null) delete options[k]; });

    const limit = Number(options.max_size_mb);
    if (isUpload && uploadLimitMb && limit > uploadLimitMb) {
      setError(`The server only accepts uploads up to ${uploadLimitMb} MB (PHP limit). Lower the size or ask a developer to raise upload_max_filesize / post_max_size.`);
      return;
    }

    setError('');
    onSave({
      label: local.label,
      fieldKey: local.fieldKey || slugify(local.label),
      helpText: local.helpText,
      isRequired: local.isRequired,
      options,
    });
  }

  const sizeOverLimit = isUpload && uploadLimitMb && Number(local.options.max_size_mb) > uploadLimitMb;

  return (
    <form onSubmit={handleSave} style={{ display: 'flex', flexDirection: 'column', gap: '0.1rem' }}>
      <h3 style={{ fontSize: '0.9rem', marginBottom: '0.75rem', color: 'var(--color-text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
        Edit Field
      </h3>

      {error && <div className="alert alert-error">{error}</div>}

      <div className="form-group">
        <label htmlFor="fe-label">
          {type === 'instructions' ? 'Heading' : type === 'section' ? 'Section name' : 'Label'} <span className="required-mark">*</span>
        </label>
        <input id="fe-label" type="text" name="label" value={local.label} onChange={handleChange} placeholder="Field label…" autoFocus />
      </div>

      {!isLayout && (
        <>
          <div className="form-group">
            <label htmlFor="fe-key">Field Key</label>
            <input id="fe-key" type="text" name="fieldKey" value={local.fieldKey} onChange={handleChange} placeholder="auto_generated" />
            <span className="form-help">Used as the data key in exports and the voting API. Lowercase letters, numbers and _ only.</span>
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
        </>
      )}

      {/* Instructions block: plain text shown to cosplayers */}
      {type === 'instructions' && (
        <div className="form-group">
          <label htmlFor="fe-instr">Instruction text</label>
          <textarea
            id="fe-instr"
            rows={6}
            maxLength={5000}
            value={local.options.text ?? ''}
            onChange={(e) => setOption('text', e.target.value)}
            placeholder={'Stage time is 3 minutes.\nVideos must be MP4…'}
          />
          <span className="form-help">Plain text. Line breaks are kept. Cosplayers see this above the next field.</span>
        </div>
      )}

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
          {[['min', 'Min'], ['max', 'Max'], ['step', 'Step']].map(([key, text]) => (
            <div className="form-group" key={key}>
              <label htmlFor={`fe-${key}`}>{text}</label>
              <input id={`fe-${key}`} type="number" value={local.options[key] ?? ''} onChange={(e) => setOption(key, e.target.value)} />
            </div>
          ))}
        </div>
      )}

      {/* Text length fields */}
      {isText && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
          <div className="form-group">
            <label htmlFor="fe-minlen">Min Length</label>
            <input id="fe-minlen" type="number" min={0} value={local.options.min_length ?? ''} onChange={(e) => setOption('min_length', e.target.value)} />
          </div>
          <div className="form-group">
            <label htmlFor="fe-maxlen">Max Length</label>
            <input id="fe-maxlen" type="number" min={1} value={local.options.max_length ?? ''} onChange={(e) => setOption('max_length', e.target.value)} />
          </div>
        </div>
      )}

      {/* Rating */}
      {type === 'rating' && (
        <div className="form-group">
          <label htmlFor="fe-stars">Max Stars</label>
          <input id="fe-stars" type="number" min={1} max={10} value={local.options.max_stars ?? 5} onChange={(e) => setOption('max_stars', Number(e.target.value))} />
        </div>
      )}

      {/* Toggle */}
      {type === 'toggle' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
          <div className="form-group">
            <label htmlFor="fe-on">On Label</label>
            <input id="fe-on" type="text" value={local.options.on_label ?? 'Yes'} onChange={(e) => setOption('on_label', e.target.value)} />
          </div>
          <div className="form-group">
            <label htmlFor="fe-off">Off Label</label>
            <input id="fe-off" type="text" value={local.options.off_label ?? 'No'} onChange={(e) => setOption('off_label', e.target.value)} />
          </div>
        </div>
      )}

      {/* ── Uploads: file / image / video ─────────────────────────────── */}
      {isUpload && (
        <>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
            <div className="form-group">
              <label htmlFor="fe-maxsize-mb">Max Size (MB)</label>
              <input
                id="fe-maxsize-mb"
                type="number"
                min={0}
                step={0.1}
                value={local.options.max_size_mb ?? ''}
                onChange={(e) => setOption('max_size_mb', e.target.value)}
                placeholder={`default ${DEFAULT_LIMIT_MB[type]}`}
              />
            </div>
            <div className="form-group">
              <label htmlFor="fe-maxsize-kb">Max Size (KB) <span className="text-muted" style={{ fontWeight: 400 }}>(overrides MB)</span></label>
              <input
                id="fe-maxsize-kb"
                type="number"
                min={0}
                value={local.options.max_size_kb ?? ''}
                onChange={(e) => setOption('max_size_kb', e.target.value)}
                placeholder="e.g. 2048"
              />
            </div>
          </div>
          <span className="form-help" style={{ marginBottom: '0.5rem' }}>
            Blank = {DEFAULT_LIMIT_MB[type]} MB. Enforced on the server.
            {uploadLimitMb ? ` The server itself accepts at most ${uploadLimitMb} MB per submission.` : ''}
          </span>
          {sizeOverLimit && (
            <div className="alert alert-error">This is higher than the server limit ({uploadLimitMb} MB) - uploads that big will fail.</div>
          )}

          {type !== 'image' && (
            <div className="form-group">
              <label htmlFor="fe-accept">Accepted file types</label>
              <input
                id="fe-accept"
                type="text"
                value={local.options.accepted_formats ?? ''}
                onChange={(e) => setOption('accepted_formats', e.target.value)}
                placeholder={type === 'video' ? 'video/mp4,video/webm' : 'application/pdf,audio/mpeg'}
              />
              <span className="form-help">
                Comma-separated MIME types. Blank = all safe {type === 'video' ? 'video' : ''} types. Scripts, HTML and SVG are never accepted.
              </span>
            </div>
          )}

          {type === 'image' && (
            <div className="form-group">
              <label htmlFor="fe-maxdim">Max width / height (px)</label>
              <input id="fe-maxdim" type="number" min={200} value={local.options.max_dimension ?? ''} onChange={(e) => setOption('max_dimension', e.target.value)} placeholder="default 2400" />
              <span className="form-help">Images are automatically resized down to this and converted to WebP.</span>
            </div>
          )}

          {type === 'video' && (
            <>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
                <div className="form-group">
                  <label htmlFor="fe-maxdur">Max length (seconds)</label>
                  <input id="fe-maxdur" type="number" min={1} value={local.options.max_duration_seconds ?? ''} onChange={(e) => setOption('max_duration_seconds', e.target.value)} placeholder="e.g. 180" />
                </div>
                <div className="form-group">
                  <label htmlFor="fe-mindur">Min length (seconds)</label>
                  <input id="fe-mindur" type="number" min={1} value={local.options.min_duration_seconds ?? ''} onChange={(e) => setOption('min_duration_seconds', e.target.value)} placeholder="optional" />
                </div>
              </div>

              <div className="form-group">
                <label htmlFor="fe-overlen">If a video is longer than the max</label>
                <select id="fe-overlen" value={local.options.over_length_action ?? 'reject'} onChange={(e) => setOption('over_length_action', e.target.value)}>
                  <option value="reject">Reject it - the cosplayer must upload a shorter video</option>
                  <option value="trim">Trim it automatically to the max length</option>
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="fe-maxh">Maximum resolution</label>
                <select id="fe-maxh" value={local.options.max_height ?? '1080'} onChange={(e) => setOption('max_height', e.target.value)}>
                  <option value="2160">4K (2160p)</option>
                  <option value="1080">Full HD (1080p)</option>
                  <option value="720">HD (720p)</option>
                  <option value="480">SD (480p)</option>
                  <option value="0">Keep original resolution</option>
                </select>
              </div>

              <div className="form-group">
                <label className="check-label">
                  <input
                    type="checkbox"
                    checked={local.options.normalize !== false}
                    onChange={(e) => setOption('normalize', e.target.checked)}
                  />
                  Convert to a show-ready MP4 (H.264 + AAC, web-optimised)
                </label>
                <span className="form-help">
                  Done in the background after upload (needs ffmpeg and a running queue worker). Turn off to keep the original file untouched.
                </span>
              </div>
            </>
          )}
        </>
      )}

      {/* Section header */}
      {type === 'section' && (
        <div className="form-group">
          <label htmlFor="fe-section-text">Section Title (shown to cosplayers)</label>
          <input id="fe-section-text" type="text" value={local.options.text ?? ''} onChange={(e) => setOption('text', e.target.value)} placeholder="Defaults to the name above" />
        </div>
      )}

      {/* Terms */}
      {type === 'terms' && (
        <div className="form-group">
          <label htmlFor="fe-terms-text">Terms Text</label>
          <textarea id="fe-terms-text" rows={4} value={local.options.terms_text ?? ''} onChange={(e) => setOption('terms_text', e.target.value)} placeholder="Paste your terms and conditions here…" />
        </div>
      )}

      {/* Hidden default */}
      {type === 'hidden' && (
        <div className="form-group">
          <label htmlFor="fe-hidden-default">Default Value</label>
          <input id="fe-hidden-default" type="text" value={local.options.default_value ?? ''} onChange={(e) => setOption('default_value', e.target.value)} />
        </div>
      )}

      <div className="flex gap-1 mt-2" style={{ justifyContent: 'flex-end' }}>
        <button type="button" className="btn btn-ghost btn-sm" onClick={onCancel}>Cancel</button>
        <button type="submit" className="btn btn-primary btn-sm">Apply</button>
      </div>
    </form>
  );
}
