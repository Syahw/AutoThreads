import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuthStore } from './stores/authStore';
import Layout from './components/Layout';
import AdminRoute from './components/admin/AdminRoute';
import Landing from './pages/Landing';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import ContentGenerator from './pages/ContentGenerator';
import Scheduler from './pages/Scheduler';
import Niches from './pages/Niches';
import Affiliates from './pages/Affiliates';
import Analytics from './pages/Analytics';
import Settings from './pages/Settings';
import AdminDashboard from './pages/admin/AdminDashboard';
import AdminUsers from './pages/admin/AdminUsers';
import AdminPublishing from './pages/admin/AdminPublishing';
import AdminLogs from './pages/admin/AdminLogs';
import AdminSettings from './pages/admin/AdminSettings';

function ProtectedRoute({ children }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  if (!isAuthenticated) return <Navigate to="/login" replace />;
  return children;
}

export default function App() {
  return (
    <Routes>
      <Route path="/landing" element={<Landing />} />
      <Route path="/login" element={<Login />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <Layout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="content" element={<ContentGenerator />} />
        <Route path="scheduler" element={<Scheduler />} />
        <Route path="niches" element={<Niches />} />
        <Route path="affiliates" element={<Affiliates />} />
        <Route path="analytics" element={<Analytics />} />
        <Route path="settings" element={<Settings />} />
        <Route path="admin" element={<AdminRoute><Navigate to="/admin/dashboard" replace /></AdminRoute>} />
        <Route path="admin/dashboard" element={<AdminRoute><AdminDashboard /></AdminRoute>} />
        <Route path="admin/users" element={<AdminRoute><AdminUsers /></AdminRoute>} />
        <Route path="admin/publishing" element={<AdminRoute><AdminPublishing /></AdminRoute>} />
        <Route path="admin/logs" element={<AdminRoute><AdminLogs /></AdminRoute>} />
        <Route path="admin/settings" element={<AdminRoute><AdminSettings /></AdminRoute>} />
        {/* Legacy redirects */}
        <Route path="admin/subscriptions" element={<AdminRoute><Navigate to="/admin/users" replace /></AdminRoute>} />
        <Route path="admin/queue" element={<AdminRoute><Navigate to="/admin/publishing" replace /></AdminRoute>} />
        <Route path="admin/worker" element={<AdminRoute><Navigate to="/admin/publishing" replace /></AdminRoute>} />
      </Route>
    </Routes>
  );
}

