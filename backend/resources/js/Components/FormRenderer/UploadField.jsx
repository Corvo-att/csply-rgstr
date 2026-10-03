import React, { useEffect, useRef, useState } from 'react';

const fmtSize = (kb) => (kb >= 1024 ? `${+(kb / 1024).toFixed(1)} MB` : `${kb} KB`);

function fmtTime(seconds) {
  const s = Math.round(seconds);
  return s >= 60 ? `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')} min` : `${s} sec`;
}

/** Ask the browser how long a video is. Resolves null if it cannot tell (the server checks again anyway). */
function readDuration(url) {
  return new Promise((resolve) => {
    const probe = document.createElement('video');
    const done = (value) => { probe.removeAttribute('src'); resolve(value); };
    probe.preload = 'metadata';
    probe.onloadedmetadata = () => done(Number.isFinite(probe.duration) ? probe.duration : null);
    probe.onerror = () => done(null);
    setTimeout(() => done(null), 8000);
    probe.src = url;
  });
}

/**
 * File / image / video picker.
 *
 * Everything checked here (size, type, video length) is ALSO enforced by the server -
 * these checks only exist so people find out immediately instead of after a long upload.
 * `field.max_kb` and `field.accepted_mimes` come from the server so both sides agree.
 */
export default function UploadField({ field, value, onChange, error }) {
  const { fieldType, label, helpText, isRequired, options = {}, max_kb: maxKb, accepted_mimes: acceptedMimes } = field;
  const id = `field-${field.id}`;
  const isVideo = fieldType === 'video';
  const isImage = fieldType === 'image';

  const inputRef = useRef(null);
  const [localError, setLocalError] = useState('');
  const [notice, setNotice] = useState('');
  const [previewUrl, setPreviewUrl] = useState(null);
  const [dragging, setDragging] = useState(false);

  // free the temporary preview URL when it changes or the field goes away
  useEffect(() => () => { if (previewUrl) URL.revokeObjectURL(previewUrl); }, [previewUrl]);

  const maxDuration = Number(options.max_duration_seconds) || 0;
  const minDuration = Number(options.min_duration_seconds) || 0;
  const trims = options.over_length_action === 'trim';

  function reject(message) {
    setLocalError(message);
    setNotice('');
    setPreviewUrl(null);
    onChange(null);
    if (inputRef.current) inputRef.current.value = '';
  }

  async function handleFile(file) {
    if (!file) { reject(''); return; }

    if (maxKb && file.size > maxKb * 1024) {
      reject(`This file is ${fmtSize(Math.round(file.size / 1024))}. The maximum is ${fmtSize(maxKb)}.`);
      return;
    }
    if (acceptedMimes?.length && file.type && !acceptedMimes.includes(file.type)) {
      reject('This file type is not accepted.');
      return;
    }

    setLocalError('');
    setNotice('');

    if (isVideo) {
      const url = URL.createObjectURL(file);
      const duration = await readDuration(url);

      if (duration !== null) {
        if (maxDuration && duration > maxDuration + 0.5) {
          if (!trims) {
            URL.revokeObjectURL(url);
            reject(`Your video is ${fmtTime(duration)} long. The maximum is ${fmtTime(maxDuration)}.`);
            return;
          }
          setNotice(`Your video is ${fmtTime(duration)} long. It will be trimmed to ${fmtTime(maxDuration)} automatically.`);
        }
        if (minDuration && duration < minDuration) {
          URL.revokeObjectURL(url);
          reject(`Your video is too short. The minimum is ${fmtTime(minDuration)}.`);
          return;
        }
      }
      setPreviewUrl(url);
    } else if (isImage) {
      setPreviewUrl(URL.createObjectURL(file));
    }

    onChange(file);
  }

  const requirements = [
    maxKb ? `Max ${fmtSize(maxKb)}` : null,
    isVideo && maxDuration ? `${trims ? 'Trimmed to' : 'Max length'} ${fmtTime(maxDuration)}` : null,
    isVideo && minDuration ? `Min length ${fmtTime(minDuration)}` : null,
    acceptedMimes?.length ? acceptedMimes.map((m) => m.split('/')[1]).join(', ') : null,
  ].filter(Boolean);

  const shownError = localError || error;
  const file = value instanceof File ? value : null;

  return (
    <div className="form-group">
      <label htmlFor={id}>
        {label}
        {isRequired && <span className="required-mark"> *</span>}
      </label>

      <label
        htmlFor={id}
        className={`upload-zone${dragging ? ' dragging' : ''}`}
        onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
        onDragLeave={() => setDragging(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDragging(false);
          handleFile(e.dataTransfer.files?.[0]);
        }}
      >
        <span className="upload-zone-icon">{isVideo ? '▶' : isImage ? '▣' : '↑'}</span>
        <span className="upload-zone-text">
          {file ? `${file.name} (${fmtSize(Math.max(1, Math.round(file.size / 1024)))})` : 'Tap to choose a file, or drag & drop it here'}
        </span>
        {requirements.length > 0 && <span className="upload-zone-meta">{requirements.join(' · ')}</span>}
        <input
          ref={inputRef}
          id={id}
          type="file"
          accept={acceptedMimes?.length ? acceptedMimes.join(',') : undefined}
          onChange={(e) => handleFile(e.target.files?.[0])}
          style={{ display: 'none' }}
        />
      </label>

      {file && (
        <button type="button" className="btn btn-ghost btn-sm" style={{ marginTop: '0.4rem' }} onClick={() => reject('')}>
          Remove file
        </button>
      )}

      {previewUrl && isVideo && <video src={previewUrl} controls className="upload-preview" />}
      {previewUrl && isImage && <img src={previewUrl} alt="Preview" className="upload-preview" />}

      {notice && <span className="form-help" style={{ color: 'var(--color-warning)' }}>{notice}</span>}
      {helpText && <span className="form-help">{helpText}</span>}
      {shownError && <span className="form-error">{shownError}</span>}
    </div>
  );
}
