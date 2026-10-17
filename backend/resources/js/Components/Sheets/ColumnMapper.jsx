import React from 'react';

function indexToLetter(idx) {
  let letter = '';
  idx += 1;
  while (idx > 0) {
    const modulo = (idx - 1) % 26;
    letter = String.fromCharCode(65 + modulo) + letter;
    idx = Math.floor((idx - modulo) / 26);
  }
  return letter;
}

const TRANSFORM_OPTIONS = [
  { value: 'auto',      label: 'Auto Detect' },
  { value: 'text',      label: 'Plain Text' },
  { value: 'hyperlink', label: 'Hyperlink (=HYPERLINK)' },
  { value: 'image',     label: 'Thumbnail (=IMAGE)' },
  { value: 'date',      label: 'Date / Time' },
  { value: 'boolean',   label: 'Checkbox (TRUE/FALSE)' },
];

export default function ColumnMapper({ mappings = [], onChange, fields = [], systemColumns = {} }) {
  function handleUpdate(index, updates) {
    const next = mappings.map((m, i) => (i === index ? { ...m, ...updates } : m));
    onChange(next);
  }

  function handleAdd() {
    const newColIndex = mappings.length;
    const next = [
      ...mappings,
      {
        source_type:  'field',
        source_key:   fields[0]?.field_key || '',
        column_index: newColIndex,
        header_label: `Column ${indexToLetter(newColIndex)}`,
        transform:    'auto',
        is_active:    true,
      },
    ];
    onChange(next);
  }

  function handleRemove(index) {
    const next = mappings
      .filter((_, i) => i !== index)
      .map((m, i) => ({ ...m, column_index: i }));
    onChange(next);
  }

  function handleMove(index, direction) {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= mappings.length) return;
    const next = [...mappings];
    const temp = next[index];
    next[index] = next[targetIndex];
    next[targetIndex] = temp;
    const reindexed = next.map((m, i) => ({ ...m, column_index: i }));
    onChange(reindexed);
  }

  return (
    <div className="column-mapper" style={{ marginTop: '1rem' }}>
      <div className="flex justify-between items-center mb-2">
        <h4 style={{ fontSize: '0.9rem', fontWeight: 600, color: 'var(--color-text)' }}>
          Field-to-Column Mappings ({mappings.filter((m) => m.is_active).length} active)
        </h4>
        <button type="button" className="btn btn-secondary btn-sm" onClick={handleAdd}>
          + Add Column
        </button>
      </div>

      <div className="table-wrapper" style={{ maxHeight: '380px', overflowY: 'auto' }}>
        <table style={{ width: '100%', fontSize: '0.82rem' }}>
          <thead>
            <tr>
              <th style={{ width: '45px' }}>Col</th>
              <th>Sheet Header Name</th>
              <th>Mapped Field</th>
              <th style={{ width: '130px' }}>Formatting</th>
              <th style={{ width: '60px', textAlign: 'center' }}>Active</th>
              <th style={{ width: '70px', textAlign: 'right' }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {mappings.map((m, idx) => (
              <tr key={idx} style={{ opacity: m.is_active ? 1 : 0.45 }}>
                <td style={{ fontWeight: 700, color: 'var(--color-primary)' }}>
                  {indexToLetter(idx)}
                </td>
                <td>
                  <input
                    type="text"
                    className="form-input"
                    style={{ fontSize: '0.8rem', padding: '0.3rem 0.5rem' }}
                    value={m.header_label}
                    onChange={(e) => handleUpdate(idx, { header_label: e.target.value })}
                  />
                </td>
                <td>
                  <select
                    className="form-input"
                    style={{ fontSize: '0.8rem', padding: '0.3rem 0.5rem' }}
                    value={`${m.source_type}:${m.source_key}`}
                    onChange={(e) => {
                      const [type, key] = e.target.value.split(':');
                      let suggestedHeader = m.header_label;
                      if (type === 'system' && systemColumns[key]) {
                        suggestedHeader = systemColumns[key].label;
                      } else if (type === 'field') {
                        const f = fields.find((item) => item.field_key === key);
                        if (f) suggestedHeader = f.label;
                      }
                      handleUpdate(idx, {
                        source_type:  type,
                        source_key:   key,
                        header_label: suggestedHeader,
                      });
                    }}
                  >
                    <optgroup label="System Columns">
                      {Object.entries(systemColumns).map(([key, meta]) => (
                        <option key={`system:${key}`} value={`system:${key}`}>
                          {meta.label} (System)
                        </option>
                      ))}
                    </optgroup>
                    <optgroup label="Dynamic Form Fields">
                      {fields.map((f) => (
                        <option key={`field:${f.field_key}`} value={`field:${f.field_key}`}>
                          {f.label} ({f.field_type})
                        </option>
                      ))}
                    </optgroup>
                  </select>
                </td>
                <td>
                  <select
                    className="form-input"
                    style={{ fontSize: '0.8rem', padding: '0.3rem 0.5rem' }}
                    value={m.transform || 'auto'}
                    onChange={(e) => handleUpdate(idx, { transform: e.target.value })}
                  >
                    {TRANSFORM_OPTIONS.map((opt) => (
                      <option key={opt.value} value={opt.value}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                </td>
                <td style={{ textAlign: 'center' }}>
                  <input
                    type="checkbox"
                    checked={Boolean(m.is_active)}
                    onChange={(e) => handleUpdate(idx, { is_active: e.target.checked })}
                  />
                </td>
                <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                  <button
                    type="button"
                    className="btn btn-ghost btn-sm"
                    style={{ padding: '0.15rem 0.35rem', fontSize: '0.75rem' }}
                    disabled={idx === 0}
                    onClick={() => handleMove(idx, -1)}
                    title="Move Left / Up"
                  >
                    ↑
                  </button>
                  <button
                    type="button"
                    className="btn btn-ghost btn-sm"
                    style={{ padding: '0.15rem 0.35rem', fontSize: '0.75rem' }}
                    disabled={idx === mappings.length - 1}
                    onClick={() => handleMove(idx, 1)}
                    title="Move Right / Down"
                  >
                    ↓
                  </button>
                  <button
                    type="button"
                    className="btn btn-ghost btn-sm"
                    style={{ padding: '0.15rem 0.35rem', fontSize: '0.75rem', color: 'var(--color-danger)' }}
                    onClick={() => handleRemove(idx)}
                    title="Remove column"
                  >
                    ✕
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
