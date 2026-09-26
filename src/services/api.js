const API_BASE = 'http://127.0.0.1:8000/api';

async function request(endpoint, options = {}) {
  const url = `${API_BASE}${endpoint}`;
  const config = {
    headers: {
      'Accept': 'application/json',
      ...(!options.isFormData && { 'Content-Type': 'application/json' }),
      ...options.headers,
    },
    ...options,
  };

  if (options.body && !options.isFormData && typeof options.body === 'object') {
    config.body = JSON.stringify(options.body);
  }

  try {
    const res = await fetch(url, config);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.message || `Request failed with status ${res.status}`);
    }
    return data;
  } catch (err) {
    console.error(`API Error on ${endpoint}:`, err);
    throw err;
  }
}

export const api = {
  // ── Auth ─────────────────────────────────────────────────────────────────
  async register(name, email, password) {
    return request('/auth/register', {
      method: 'POST',
      body: { name, email, password },
    });
  },

  async login(email, password) {
    return request('/auth/login', {
      method: 'POST',
      body: { email, password },
    });
  },

  async adminLogin(email, password) {
    return request('/admin/login', {
      method: 'POST',
      body: { email, password },
    });
  },

  async saveCosplayerProfile(data) {
    return request('/cosplayers/profile', {
      method: 'POST',
      body: data,
    });
  },

  async getCosplayerProfile(userId) {
    return request(`/cosplayers/profile?user_id=${userId}`);
  },

  // ── Events ───────────────────────────────────────────────────────────────
  async getEvents(all = false) {
    return request(`/events${all ? '?all=1' : ''}`);
  },

  async getEvent(id) {
    return request(`/events/${id}`);
  },

  async createEvent(data) {
    return request('/events', {
      method: 'POST',
      body: data,
    });
  },

  async updateEventStatus(id, status) {
    return request(`/events/${id}/status`, {
      method: 'PATCH',
      body: { status },
    });
  },

  async deleteEvent(id) {
    return request(`/events/${id}`, {
      method: 'DELETE',
    });
  },

  // ── Forms ────────────────────────────────────────────────────────────────
  async getForms(eventId) {
    return request(`/events/${eventId}/forms`);
  },

  async createForm(eventId, data) {
    return request(`/events/${eventId}/forms`, {
      method: 'POST',
      body: data,
    });
  },

  async getForm(id) {
    return request(`/forms/${id}`);
  },

  async toggleFormStatus(id) {
    return request(`/forms/${id}/status`, {
      method: 'PATCH',
    });
  },

  async deleteForm(id) {
    return request(`/forms/${id}`, {
      method: 'DELETE',
    });
  },

  async saveFormFields(formId, fields) {
    return request(`/forms/${formId}/fields`, {
      method: 'PUT',
      body: { fields },
    });
  },

  // ── Submissions & Dashboard ──────────────────────────────────────────────
  async submitForm(formId, data) {
    return request(`/forms/${formId}/submit`, {
      method: 'POST',
      body: data,
    });
  },

  async getSubmissions(formId) {
    return request(`/forms/${formId}/submissions`);
  },

  async getDashboard() {
    return request('/admin/dashboard');
  },

  // ── Media Uploads (Videos, Images, Files) ─────────────────────────────────
  async uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    return request('/upload', {
      method: 'POST',
      body: formData,
      isFormData: true,
    });
  },
};
