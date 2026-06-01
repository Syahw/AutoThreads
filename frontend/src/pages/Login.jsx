import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

export default function Login() {
  const [mode, setMode] = useState('login'); // 'login' | 'register'
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const login = useAuthStore((s) => s.login);
  const register = useAuthStore((s) => s.register);
  const navigate = useNavigate();

  const isRegister = mode === 'register';

  const toggleMode = () => {
    setError('');
    setMode((m) => (m === 'login' ? 'register' : 'login'));
  };  

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      if (isRegister) {
        await register(name, email, password);
      } else {
        await login(email, password);
      }
      navigate('/');
    } catch (err) {
      const data = err.response?.data;
      const message =
        data?.message ||
        (Array.isArray(data?.messages) ? data.messages.join(', ') : null) ||
        (isRegister ? 'Registration failed' : 'Login failed');
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
      <div className="w-full max-w-sm">
        <div className="text-center mb-8">
          <h1 className="text-2xl font-bold text-gray-900">AutoThreads</h1>
          <p className="text-gray-500 mt-1">
            {isRegister ? 'Create your account' : 'Sign in to your account'}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
          {error && (
            <div className="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg" role="alert">
              {error}
            </div>
          )}

          {isRegister && (
            <div className="mb-4">
              <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                Name
              </label>
              <input
                id="name"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
                minLength={2}
              />
            </div>
          )}

          <div className="mb-4">
            <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
              Email
            </label>
            <input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              required
            />
          </div>

          <div className="mb-6">
            <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
              Password
            </label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              required
              minLength={isRegister ? 8 : undefined}
            />
            {isRegister && (
              <p className="mt-1 text-xs text-gray-400">At least 8 characters.</p>
            )}
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full py-2 px-4 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors"
          >
            {loading
              ? isRegister
                ? 'Creating account...'
                : 'Signing in...'
              : isRegister
                ? 'Create account'
                : 'Sign in'}
          </button>

          <p className="mt-4 text-center text-sm text-gray-500">
            {isRegister ? 'Already have an account?' : "Don't have an account?"}{' '}
            <button
              type="button"
              onClick={toggleMode}
              className="text-blue-600 font-medium hover:underline"
            >
              {isRegister ? 'Sign in' : 'Sign up'}
            </button>
          </p>
        </form>
      </div>
    </div>
  );
}
