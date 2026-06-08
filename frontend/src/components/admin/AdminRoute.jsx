import { Navigate } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';

export default function AdminRoute({ children }) {
  const user = useAuthStore((s) => s.user);
  const isImpersonating = useAuthStore((s) => s.isImpersonating);

  if (isImpersonating || user?.role !== 'admin') {
    return <Navigate to="/" replace />;
  }

  return children;
}
