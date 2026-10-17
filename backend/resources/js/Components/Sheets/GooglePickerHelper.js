/**
 * Helper to dynamically load the Google Picker API and show a Spreadsheet picker.
 */
let gapiLoaded = false;

function loadGapiScript() {
  return new Promise((resolve, reject) => {
    if (gapiLoaded && window.gapi) {
      resolve(window.gapi);
      return;
    }

    if (window.gapi) {
      gapiLoaded = true;
      resolve(window.gapi);
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://apis.google.com/js/api.js';
    script.async = true;
    script.defer = true;
    script.onload = () => {
      gapiLoaded = true;
      resolve(window.gapi);
    };
    script.onerror = () => reject(new Error('Failed to load Google API script'));
    document.body.appendChild(script);
  });
}

export async function openSpreadsheetPicker({ onPick, onCancel }) {
  try {
    // 1. Fetch short-lived token and keys from backend
    const res = await fetch('/admin/google/picker-token');
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.error || 'Failed to fetch Google credentials.');
    }
    const { access_token, api_key, app_id } = await res.json();

    if (!api_key) {
      throw new Error('GOOGLE_PICKER_API_KEY is not configured in backend.');
    }

    // 2. Load Google API client
    const gapi = await loadGapiScript();

    // 3. Load picker module
    await new Promise((resolve) => gapi.load('picker', resolve));

    if (!window.google?.picker) {
      throw new Error('Google Picker library is unavailable.');
    }

    const view = new window.google.picker.DocsView(window.google.picker.ViewId.SPREADSHEETS)
      .setMimeTypes('application/vnd.google-apps.spreadsheet');

    let builder = new window.google.picker.PickerBuilder()
      .setOAuthToken(access_token)
      .setDeveloperKey(api_key)
      .addView(view)
      .setCallback((data) => {
        const action = data[window.google.picker.Response.ACTION];
        if (action === window.google.picker.Action.PICKED) {
          const doc = data[window.google.picker.Response.DOCUMENTS][0];
          onPick({
            id:   doc[window.google.picker.Document.ID],
            name: doc[window.google.picker.Document.NAME],
            url:  doc[window.google.picker.Document.URL],
          });
        } else if (action === window.google.picker.Action.CANCEL) {
          if (onCancel) onCancel();
        }
      });

    if (app_id) {
      builder = builder.setAppId(app_id);
    }

    const picker = builder.build();
    picker.setVisible(true);
  } catch (error) {
    console.error('Picker error:', error);
    throw error;
  }
}
