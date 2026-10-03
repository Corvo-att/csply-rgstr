import React from 'react';

// All supported field types grouped by category.
// (The "types" must match App\Models\FormField::TYPES on the server - unknown types are rejected on save.)
export const FIELD_TYPES = [
  {
    category: 'Text',
    types: [
      { type: 'text',     label: 'Text',          icon: 'Tt' },
      { type: 'textarea', label: 'Textarea',      icon: 'Tx' },
      { type: 'email',    label: 'Email',         icon: '@' },
      { type: 'url',      label: 'URL',           icon: '://' },
      { type: 'password', label: 'Password',      icon: '**' },
      { type: 'phone',    label: 'Phone / Tel',   icon: '+#' },
    ],
  },
  {
    category: 'Numeric',
    types: [
      { type: 'number', label: 'Number',         icon: '123' },
      { type: 'range',  label: 'Range / Slider', icon: '—o' },
      { type: 'rating', label: 'Rating (Stars)', icon: '★★' },
    ],
  },
  {
    category: 'Date & Time',
    types: [
      { type: 'date',           label: 'Date',        icon: 'dd' },
      { type: 'time',           label: 'Time',        icon: 'hh' },
      { type: 'datetime-local', label: 'Date & Time', icon: 'dt' },
    ],
  },
  {
    category: 'Choice',
    types: [
      { type: 'dropdown',       label: 'Dropdown',          icon: '▾' },
      { type: 'radio',          label: 'Radio Group',       icon: '◎' },
      { type: 'checkbox',       label: 'Checkbox (single)', icon: '☐' },
      { type: 'checkbox_group', label: 'Checkbox Group',    icon: '☐☐' },
      { type: 'multiselect',    label: 'Multi-Select',      icon: '▾+' },
      { type: 'toggle',         label: 'Toggle / Switch',   icon: '○●' },
    ],
  },
  {
    category: 'Media',
    types: [
      { type: 'file',  label: 'File Upload',  icon: '↑' },
      { type: 'image', label: 'Image Upload', icon: '⬚' },
      { type: 'video', label: 'Video Upload', icon: '▶' },
      { type: 'color', label: 'Color Picker', icon: '▣' },
    ],
  },
  {
    category: 'Special',
    types: [
      { type: 'address',      label: 'Address (composite)', icon: '⊞' },
      { type: 'hidden',       label: 'Hidden Field',        icon: '…' },
      { type: 'section',      label: 'Section Header',      icon: '—' },
      { type: 'instructions', label: 'Instructions Block',  icon: 'i' },
      { type: 'terms',        label: 'Terms & Conditions',  icon: '§' },
    ],
  },
];

export function getFieldMeta(type) {
  for (const group of FIELD_TYPES) {
    const found = group.types.find((t) => t.type === type);
    if (found) return found;
  }
  return { type, label: type, icon: '?' };
}

export default function FieldPalette({ onAddField }) {
  return (
    <div>
      <p className="text-sm text-muted mb-1" style={{ padding: '0 0.25rem' }}>
        Click a type to add it to the form.
      </p>
      {FIELD_TYPES.map((group) => (
        <div key={group.category}>
          <p className="palette-section-title">{group.category}</p>
          {group.types.map((ft) => (
            <button
              key={ft.type}
              type="button"
              className="palette-btn"
              onClick={() => onAddField(ft.type)}
              title={`Add ${ft.label} field`}
            >
              <span
                className="palette-btn-icon"
                style={{
                  fontFamily: 'var(--font-display)',
                  fontSize: '0.7rem',
                  fontWeight: 700,
                  color: 'var(--color-primary)',
                  letterSpacing: '0.02em',
                }}
              >
                {ft.icon}
              </span>
              {ft.label}
            </button>
          ))}
        </div>
      ))}
    </div>
  );
}
