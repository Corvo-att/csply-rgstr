import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext.jsx';
import { ProtectedRoute, GuestRoute, AdminRoute, AdminGuestRoute } from './components/RouteGuards.jsx';

// ── Public / Cosplayer pages ──────────────────────────────────────────────────
import Welcome        from './Pages/Welcome.jsx';
import Login          from './Pages/Auth/Login.jsx';
import Register       from './Pages/Auth/Register.jsx';
import CosplayRegister from './Pages/Cosplay/Register.jsx';
import Profile        from './Pages/Cosplay/Profile.jsx';
import Fill           from './Pages/Forms/Fill.jsx';

// ── Admin pages ───────────────────────────────────────────────────────────────
import AdminLogin     from './Pages/Admin/Login.jsx';
import Dashboard      from './Pages/Admin/Dashboard.jsx';
import EventsIndex    from './Pages/Admin/Events/Index.jsx';
import EventCreate    from './Pages/Admin/Events/Create.jsx';
import EventForms     from './Pages/Admin/Events/Forms.jsx';
import Builder        from './Pages/Admin/Forms/Builder.jsx';
import Submissions    from './Pages/Admin/Forms/Submissions.jsx';

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          {/* ── Public ───────────────────────────────────────────────────── */}
          <Route path="/" element={<Welcome />} />

          {/* ── Guest-only (cosplayer) ────────────────────────────────────── */}
          <Route path="/login"    element={<GuestRoute><Login /></GuestRoute>} />
          <Route path="/register" element={<GuestRoute><Register /></GuestRoute>} />

          {/* ── Authenticated (cosplayer) ─────────────────────────────────── */}
          <Route path="/register-cosplay" element={<ProtectedRoute><CosplayRegister /></ProtectedRoute>} />
          <Route path="/profile-page"     element={<ProtectedRoute><Profile /></ProtectedRoute>} />
          <Route path="/events/:eventId/forms/:formId" element={<ProtectedRoute><Fill /></ProtectedRoute>} />

          {/* ── Admin ─────────────────────────────────────────────────────── */}
          <Route path="/admin/login" element={<AdminGuestRoute><AdminLogin /></AdminGuestRoute>} />
          <Route path="/admin/dashboard" element={<AdminRoute><Dashboard /></AdminRoute>} />
          <Route path="/admin/events" element={<AdminRoute><EventsIndex /></AdminRoute>} />
          <Route path="/admin/events/create" element={<AdminRoute><EventCreate /></AdminRoute>} />
          <Route path="/admin/events/:eventId/forms" element={<AdminRoute><EventForms /></AdminRoute>} />
          <Route path="/admin/events/:eventId/forms/:formId/builder" element={<AdminRoute><Builder /></AdminRoute>} />
          <Route path="/admin/forms/:formId/submissions" element={<AdminRoute><Submissions /></AdminRoute>} />

          {/* ── Fallback ──────────────────────────────────────────────────── */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
