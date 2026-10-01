import './bootstrap';
import '../css/app.css';

import React from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    // Resolves page components from resources/js/Pages/
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },

    // Page title
    title: (title) => title ? `${title} — EGYCON` : 'EGYCON',
});
