// ─── MOCK DATA ────────────────────────────────────────────────────────────────
// All data lives here while the frontend is in mock-data mode.
// When backend is connected, this file is no longer used.

export const mockUsers = [
  {
    id: 1,
    name: 'Layla Hassan',
    email: 'layla@example.com',
    password: 'password',
    createdAt: '2026-01-10T10:00:00Z',
  },
  {
    id: 2,
    name: 'Youssef Karim',
    email: 'youssef@example.com',
    password: 'password',
    createdAt: '2026-01-12T11:30:00Z',
  },
  {
    id: 3,
    name: 'Nour El-Din',
    email: 'nour@example.com',
    password: 'password',
    createdAt: '2026-02-01T09:15:00Z',
  },
];

export const mockCosplayers = [
  {
    id: 1,
    userId: 1,
    characterName: 'Nezuko Kamado',
    series: 'Demon Slayer',
    experienceLevel: 'intermediate',
    bio: 'Been cosplaying for 3 years. Love building props!',
    createdAt: '2026-01-10T10:05:00Z',
  },
  {
    id: 2,
    userId: 2,
    characterName: 'Levi Ackerman',
    series: 'Attack on Titan',
    experienceLevel: 'advanced',
    bio: 'Competitive cosplayer, multiple awards.',
    createdAt: '2026-01-12T11:35:00Z',
  },
  {
    id: 3,
    userId: 3,
    characterName: 'Zero Two',
    series: 'Darling in the FranXX',
    experienceLevel: 'beginner',
    bio: 'First time cosplaying, super excited!',
    createdAt: '2026-02-01T09:20:00Z',
  },
];

export const mockAdmins = [
  {
    id: 1,
    name: 'EGYCON Admin',
    email: 'admin@egycon.com',
    password: 'admin123',
    role: 'admin',
  },
];

export const mockEvents = [
  {
    id: 1,
    adminId: 1,
    name: 'EGYCON 2026',
    slug: 'egycon-2026',
    description: 'The main annual convention event.',
    startsAt: '2026-08-15T09:00:00Z',
    endsAt: '2026-08-17T21:00:00Z',
    location: 'Cairo International Convention Centre',
    status: 'published',
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: 2,
    adminId: 1,
    name: 'Cosplay Photography Workshop',
    slug: 'cosplay-photo-workshop',
    description: 'Learn professional cosplay photography techniques.',
    startsAt: '2026-08-16T13:00:00Z',
    endsAt: '2026-08-16T17:00:00Z',
    location: 'Studio Hall B',
    status: 'draft',
    createdAt: '2026-01-05T00:00:00Z',
  },
];

export const mockForms = [
  {
    id: 1,
    eventId: 1,
    name: 'Cosplay Contest Entry',
    description: 'Register your character for the main contest.',
    isActive: true,
    createdAt: '2026-01-02T00:00:00Z',
  },
  {
    id: 2,
    eventId: 1,
    name: 'Photo-Op Slot Request',
    description: 'Book your preferred photo session time.',
    isActive: true,
    createdAt: '2026-01-03T00:00:00Z',
  },
];

export const mockFormFields = [
  // Form 1 — Cosplay Contest Entry
  {
    id: 1,
    formId: 1,
    label: 'Costume Category',
    fieldKey: 'costume_category',
    fieldType: 'dropdown',
    options: { choices: ['Original', 'Anime', 'Game', 'Movie/TV', 'Other'] },
    validationRules: {},
    isRequired: true,
    sortOrder: 0,
    helpText: 'Pick the category that best fits your costume.',
  },
  {
    id: 2,
    formId: 1,
    label: 'Years of Cosplaying',
    fieldKey: 'years_cosplaying',
    fieldType: 'number',
    options: { min: 0, max: 30, step: 1 },
    validationRules: {},
    isRequired: true,
    sortOrder: 1,
    helpText: '',
  },
  {
    id: 3,
    formId: 1,
    label: 'Brief Description of Costume',
    fieldKey: 'costume_description',
    fieldType: 'textarea',
    options: { rows: 4, maxLength: 500 },
    validationRules: {},
    isRequired: false,
    sortOrder: 2,
    helpText: 'Tell us about the materials and techniques used.',
  },
  {
    id: 4,
    formId: 1,
    label: 'I agree to the contest rules',
    fieldKey: 'agree_rules',
    fieldType: 'checkbox',
    options: {},
    validationRules: {},
    isRequired: true,
    sortOrder: 3,
    helpText: '',
  },
  // Form 2 — Photo-Op Slot
  {
    id: 5,
    formId: 2,
    label: 'Preferred Time Slot',
    fieldKey: 'preferred_slot',
    fieldType: 'radio',
    options: { choices: ['Morning (9am–12pm)', 'Afternoon (1pm–4pm)', 'Evening (5pm–8pm)'] },
    validationRules: {},
    isRequired: true,
    sortOrder: 0,
    helpText: '',
  },
  {
    id: 6,
    formId: 2,
    label: 'Special Requirements',
    fieldKey: 'special_requirements',
    fieldType: 'text',
    options: { maxLength: 255 },
    validationRules: {},
    isRequired: false,
    sortOrder: 1,
    helpText: 'Any accessibility needs or special setup requests.',
  },
];

export const mockFormSubmissions = [
  {
    id: 1,
    formId: 1,
    cosplayerId: 1,
    submittedAt: '2026-03-01T14:00:00Z',
    values: [
      { fieldId: 1, value: 'Anime' },
      { fieldId: 2, value: '3' },
      { fieldId: 3, value: 'Hand-sewn kimono with LED effects.' },
      { fieldId: 4, value: '1' },
    ],
  },
  {
    id: 2,
    formId: 1,
    cosplayerId: 2,
    submittedAt: '2026-03-02T10:30:00Z',
    values: [
      { fieldId: 1, value: 'Anime' },
      { fieldId: 2, value: '7' },
      { fieldId: 3, value: '3D-printed ODM gear with working mechanism.' },
      { fieldId: 4, value: '1' },
    ],
  },
];

// ─── HELPERS ──────────────────────────────────────────────────────────────────

export function getCosplayerByUserId(userId) {
  return mockCosplayers.find((c) => c.userId === userId) || null;
}

export function getUserById(id) {
  return mockUsers.find((u) => u.id === id) || null;
}

export function getFormsByEventId(eventId) {
  return mockForms.filter((f) => f.eventId === eventId);
}

export function getFieldsByFormId(formId) {
  return mockFormFields
    .filter((f) => f.formId === formId)
    .sort((a, b) => a.sortOrder - b.sortOrder);
}

export function getSubmissionsByFormId(formId) {
  return mockFormSubmissions.filter((s) => s.formId === formId);
}

export function getEventById(id) {
  return mockEvents.find((e) => e.id === id) || null;
}

export function getFormById(id) {
  return mockForms.find((f) => f.id === id) || null;
}

// ─── STORE (mutable arrays for local add/update/delete during session) ────────
// We export these by reference so mutations persist within a session.
export const store = {
  users: [...mockUsers],
  cosplayers: [...mockCosplayers],
  admins: [...mockAdmins],
  events: [...mockEvents],
  forms: [...mockForms],
  formFields: [...mockFormFields],
  formSubmissions: [...mockFormSubmissions],
  nextId: {
    user: 4,
    cosplayer: 4,
    event: 3,
    form: 3,
    formField: 7,
    formSubmission: 3,
  },
};
