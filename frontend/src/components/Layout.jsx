import { useState } from 'react';
import { Outlet, NavLink, useLocation } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import {
  LayoutDashboard, Sparkles, Calendar, Tag,
  Link2, BarChart3, Settings, LogOut, Menu, X, Zap,
} from 'lucide-react';
import clsx from 'clsx';
import ThemeToggle from './ThemeToggle';

const navItems = [
  { to: '/', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/content', icon: Sparkles, label: 'Content' },
  { to: '/scheduler', icon: Calendar, label: 'Scheduler' },
  { to: '/niches', icon: Tag, label: 'Niches' },
  { to: '/affiliates', icon: Link2, label: 'Affiliates' },
  { to: '/analytics', icon: BarChart3, label: 'Analytics' },
  { to: '/settings', icon: Settings, label: 'Settings' },
];

const pageTitles = {
  '/': 'Dashboard',
  '/content': 'Content Generator',
  '/scheduler': 'Scheduler',
  '/niches': 'Niches',
  '/affiliates': 'Affiliate Links',
  '/analytics': 'Analytics',
  '/settings': 'Settings',
};

export default function Layout() {
  const { user, logout } = useAuthStore();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const location = useLocation();
  const pageTitle = pageTitles[location.pathname] || 'AutoThreads';

  return (
    <div className="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950">
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
          onClick={() => setSidebarOpen(false)}
          aria-hidden
        />
      )}

      <aside
        className={clsx(
          'fixed inset-y-0 left-0 z-50 flex w-[270px] flex-col bg-sidebar-gradient border-r border-slate-800/50 transition-transform duration-300 ease-out lg:static lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        )}
      >
        <div className="flex items-center justify-between gap-3 border-b border-slate-800/80 px-5 py-5">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient shadow-glow">
              <Zap size={20} className="text-white" fill="currentColor" />
            </div>
            <div>
              <h1 className="text-lg font-bold tracking-tight text-white">AutoThreads</h1>
              <p className="text-xs text-slate-400">AI · Threads · Affiliate</p>
            </div>
          </div>
          <button
            type="button"
            onClick={() => setSidebarOpen(false)}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white lg:hidden"
            aria-label="Close sidebar"
          >
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main navigation">
          <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Menu
          </p>
          {navItems.map(({ to, icon: Icon, label }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              onClick={() => setSidebarOpen(false)}
              className={({ isActive }) =>
                clsx(
                  'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                  isActive
                    ? 'bg-white/10 text-white shadow-inner'
                    : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100'
                )
              }
            >
              {({ isActive }) => (
                <>
                  {isActive && (
                    <span className="absolute left-0 top-1/2 h-8 w-1 -translate-y-1/2 rounded-r-full bg-brand-gradient" />
                  )}
                  <span
                    className={clsx(
                      'flex h-8 w-8 items-center justify-center rounded-lg transition-colors',
                      isActive ? 'bg-brand-500/20 text-brand-300' : 'bg-slate-800/50 text-slate-400 group-hover:text-slate-200'
                    )}
                  >
                    <Icon size={18} />
                  </span>
                  {label}
                </>
              )}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-slate-800/80 p-4">
          <div className="rounded-xl bg-slate-800/40 p-3 ring-1 ring-slate-700/50">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-sm font-bold text-white">
                {user?.name?.[0]?.toUpperCase() ?? '?'}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-white">{user?.name}</p>
                <p className="truncate text-xs capitalize text-slate-400">{user?.plan ?? 'free'} plan</p>
              </div>
            </div>
            <button
              type="button"
              onClick={logout}
              className="mt-3 flex w-full items-center justify-center gap-2 rounded-lg py-2 text-xs font-medium text-slate-400 transition-colors hover:bg-slate-700/50 hover:text-red-300"
            >
              <LogOut size={14} />
              Sign out
            </button>
          </div>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex items-center gap-4 border-b border-slate-200/80 bg-white/80 px-4 py-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/80 sm:px-6 lg:px-8">
          <button
            type="button"
            onClick={() => setSidebarOpen(true)}
            className="rounded-xl border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
            aria-label="Open sidebar"
          >
            <Menu size={20} />
          </button>
          <div className="flex-1 min-w-0">
            <p className="hidden text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 sm:block">
              Workspace
            </p>
            <h2 className="truncate text-lg font-bold text-slate-900 dark:text-slate-50 sm:text-xl">{pageTitle}</h2>
          </div>
          <div className="flex items-center gap-2 sm:gap-3">
            <ThemeToggle compact />
            <span className="hidden rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/10 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-500/20 sm:inline">
              System online
            </span>
          </div>
        </header>

        <main className="flex-1 overflow-auto bg-mesh dark:bg-mesh-dark">
          <div className="page-shell p-4 sm:p-6 lg:p-8">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
