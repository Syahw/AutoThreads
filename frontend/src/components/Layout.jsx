import { useState } from 'react';
import { Outlet, NavLink, useLocation } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import {
  LayoutDashboard, Sparkles, Calendar, Tag,
  Link2, BarChart3, Settings, LogOut, Menu, X, Zap,
  Shield, Users, ListOrdered, ScrollText, SlidersHorizontal,
} from 'lucide-react';
import clsx from 'clsx';
import ThemeToggle from './ThemeToggle';
import LanguageToggle from './LanguageToggle';
import { useTranslation } from '../i18n';

const navRoutes = [
  { to: '/', icon: LayoutDashboard, labelKey: 'nav.dashboard' },
  { to: '/content', icon: Sparkles, labelKey: 'nav.content' },
  { to: '/scheduler', icon: Calendar, labelKey: 'nav.scheduler' },
  { to: '/niches', icon: Tag, labelKey: 'nav.niches' },
  { to: '/affiliates', icon: Link2, labelKey: 'nav.affiliates' },
  { to: '/analytics', icon: BarChart3, labelKey: 'nav.analytics' },
  { to: '/settings', icon: Settings, labelKey: 'nav.settings' },
];

const adminNavRoutes = [
  { to: '/admin/dashboard', icon: Shield, labelKey: 'nav.adminHome' },
  { to: '/admin/users', icon: Users, labelKey: 'nav.adminUsers' },
  { to: '/admin/publishing', icon: ListOrdered, labelKey: 'nav.adminPublishing' },
  { to: '/admin/logs', icon: ScrollText, labelKey: 'nav.adminLogs' },
  { to: '/admin/settings', icon: SlidersHorizontal, labelKey: 'nav.adminSettings' },
];

export default function Layout() {
  const { user, logout, isImpersonating, stopImpersonating } = useAuthStore();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const location = useLocation();
  const { t } = useTranslation();

  const pageTitleKey = `layout.pageTitle.${location.pathname}`;
  const pageTitleTranslated = t(pageTitleKey);
  const pageTitle = pageTitleTranslated === pageTitleKey
    ? t('layout.fallbackTitle')
    : pageTitleTranslated;

  const isAdmin = user?.role === 'admin' && !isImpersonating;

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
              <p className="text-xs text-slate-400">{t('nav.tagline')}</p>
            </div>
          </div>
          <button
            type="button"
            onClick={() => setSidebarOpen(false)}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white lg:hidden"
            aria-label={t('layout.closeSidebar')}
          >
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label={t('layout.mainNav')}>
          <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            {t('nav.menu')}
          </p>
          {navRoutes.map(({ to, icon: Icon, labelKey }) => (
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
                  {t(labelKey)}
                </>
              )}
            </NavLink>
          ))}
          {isAdmin && (
            <>
              <p className="mb-2 mt-4 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                {t('nav.admin')}
              </p>
              {adminNavRoutes.map(({ to, icon: Icon, labelKey }) => (
                <NavLink
                  key={to}
                  to={to}
                  onClick={() => setSidebarOpen(false)}
                  className={({ isActive }) =>
                    clsx(
                      'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                      isActive
                        ? 'bg-amber-500/15 text-amber-100 shadow-inner'
                        : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100'
                    )
                  }
                >
                  {({ isActive }) => (
                    <>
                      {isActive && (
                        <span className="absolute left-0 top-1/2 h-8 w-1 -translate-y-1/2 rounded-r-full bg-amber-400" />
                      )}
                      <span
                        className={clsx(
                          'flex h-8 w-8 items-center justify-center rounded-lg transition-colors',
                          isActive ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-800/50 text-slate-400 group-hover:text-slate-200'
                        )}
                      >
                        <Icon size={18} />
                      </span>
                      {t(labelKey)}
                    </>
                  )}
                </NavLink>
              ))}
            </>
          )}
        </nav>

        <div className="border-t border-slate-800/80 p-4">
          <div className="rounded-xl bg-slate-800/40 p-3 ring-1 ring-slate-700/50">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-sm font-bold text-white">
                {user?.name?.[0]?.toUpperCase() ?? '?'}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-white">{user?.name}</p>
                <p className="truncate text-xs capitalize text-slate-400">
                  {user?.plan ?? 'free'} {t('layout.planSuffix')}
                </p>
              </div>
            </div>
            <button
              type="button"
              onClick={logout}
              className="mt-3 flex w-full items-center justify-center gap-2 rounded-lg py-2 text-xs font-medium text-slate-400 transition-colors hover:bg-slate-700/50 hover:text-red-300"
            >
              <LogOut size={14} />
              {t('layout.signOut')}
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
            aria-label={t('layout.openSidebar')}
          >
            <Menu size={20} />
          </button>
          <div className="flex-1 min-w-0">
            <p className="hidden text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 sm:block">
              {t('layout.workspace')}
            </p>
            <h2 className="truncate text-lg font-bold text-slate-900 dark:text-slate-50 sm:text-xl">{pageTitle}</h2>
          </div>
          <div className="flex items-center gap-2 sm:gap-3">
            <LanguageToggle size="sm" />
            <ThemeToggle compact />
            <span className="hidden rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/10 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-500/20 sm:inline">
              {t('layout.systemOnline')}
            </span>
          </div>
        </header>

        <main className="flex-1 overflow-auto bg-mesh dark:bg-mesh-dark">
          {isImpersonating && (
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100 sm:px-6 lg:px-8">
              <span>
                {t('layout.viewingAs')} <strong>{user?.name}</strong> ({user?.email})
              </span>
              <button type="button" onClick={stopImpersonating} className="btn-secondary !py-1.5 !text-xs">
                {t('layout.returnToAdmin')}
              </button>
            </div>
          )}
          <div className="page-shell p-4 sm:p-6 lg:p-8">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
