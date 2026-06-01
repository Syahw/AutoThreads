import axios from 'axios';

// Must match the `name` used by the zustand persist store (authStore.js).
const AUTH_STORAGE_KEY = 'autothreads-auth';

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Read the persisted JWT that the zustand auth store wrote to localStorage.
// zustand's persist middleware stores state as { state: {...}, version: n }.
function getStoredToken() {
  try {
    const stored = localStorage.getItem(AUTH_STORAGE_KEY);
    return stored ? JSON.parse(stored)?.state?.token ?? null : null;
  } catch {
    return null;
  }
}

// Request interceptor: attach the bearer token on every request.
// This is what keeps the session alive across full page reloads — the
// in-memory default header is reset on refresh, but the token is still
// persisted in localStorage, so we re-attach it here on each request.
api.interceptors.request.use((config) => {
  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor for auth errors.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const url = error.config?.url ?? '';
    const isAuthEndpoint = url.includes('/auth/');

  
    if (status === 401 && !isAuthEndpoint) {
      localStorage.removeItem(AUTH_STORAGE_KEY);
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
