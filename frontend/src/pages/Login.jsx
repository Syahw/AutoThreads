import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import { Zap, Sparkles } from 'lucide-react';
import ThemeToggle from '../components/ThemeToggle';

export default function Login() {
  const [mode, setMode] = useState('login');
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
    <div className="relative flex min-h-screen overflow-hidden">
      <div className="hidden w-1/2 bg-sidebar-gradient lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div className="flex items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-gradient shadow-glow">
            <Zap size={24} className="text-white" fill="currentColor" />
          </div>
          <span className="text-2xl font-bold text-white">AutoThreads</span>
        </div>
        <div>
          <h2 className="text-3xl font-bold leading-tight text-white">
            Automate Threads content that sounds human.
          </h2>
          <p className="mt-4 max-w-md text-slate-400">
            Generate Bahasa Malaysia threads, approve with one click, and publish chained replies to Meta Threads.
          </p>
          <ul className="mt-8 space-y-3 text-sm text-slate-300">
            <li className="flex items-center gap-2">
              <Sparkles size={16} className="text-brand-400" /> AI-powered thread generation
            </li>
            <li className="flex items-center gap-2">
              <Sparkles size={16} className="text-brand-400" /> Affiliate-aware CTAs
            </li>
            <li className="flex items-center gap-2">
              <Sparkles size={16} className="text-brand-400" /> Direct publish to Threads
            </li>
          </ul>
        </div>
        <p className="text-xs text-slate-500">© AutoThreads · AI Content Automation</p>
      </div>

      <div className="relative flex flex-1 items-center justify-center bg-mesh p-6 dark:bg-mesh-dark">
        <div className="absolute right-4 top-4 sm:right-6 sm:top-6">
          <ThemeToggle />
        </div>
        <div className="w-full max-w-md">
          <div className="mb-8 text-center lg:hidden">
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-gradient">
              <Zap size={22} className="text-white" fill="currentColor" />
            </div>
            <h1 className="text-heading text-2xl font-bold">AutoThreads</h1>
          </div>

          <div className="card p-8 shadow-card-hover">
            <h2 className="text-heading text-xl font-bold">
              {isRegister ? 'Create account' : 'Sign in'}
            </h2>
            <p className="text-muted mt-1 text-sm">
              {isRegister ? 'Start automating your Threads content' : 'Welcome back — enter your credentials'}
            </p>

            <form onSubmit={handleSubmit} className="mt-6 space-y-4">
              {error && (
                <div className="alert-error" role="alert">
                  {error}
                </div>
              )}

              {isRegister && (
                <div>
                  <label htmlFor="name" className="text-label mb-1.5 block text-sm font-medium">Name</label>
                  <input
                    id="name"
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    className="input-field"
                    required
                    minLength={2}
                  />
                </div>
              )}

              <div>
                <label htmlFor="email" className="text-label mb-1.5 block text-sm font-medium">Email</label>
                <input
                  id="email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="input-field"
                  required
                />
              </div>

              <div>
                <label htmlFor="password" className="text-label mb-1.5 block text-sm font-medium">Password</label>
                <input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="input-field"
                  required
                  minLength={isRegister ? 8 : undefined}
                />
                {isRegister && (
                  <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">At least 8 characters</p>
                )}
              </div>

              <button type="submit" disabled={loading} className="btn-primary w-full">
                {loading
                  ? isRegister ? 'Creating account...' : 'Signing in...'
                  : isRegister ? 'Create account' : 'Sign in'}
              </button>
            </form>

            <p className="text-muted mt-6 text-center text-sm">
              {isRegister ? 'Already have an account?' : "Don't have an account?"}{' '}
              <button type="button" onClick={toggleMode} className="font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                {isRegister ? 'Sign in' : 'Sign up'}
              </button>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
