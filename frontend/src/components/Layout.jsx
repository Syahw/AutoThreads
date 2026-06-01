import { useState } from 'react';
import { Outlet, NavLink } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import {
  LayoutDashboard, Sparkles, Calendar, Tag,
  Link2, BarChart3, Settings, LogOut, Menu, X,
} from 'lucide-react';
import clsx from 'clsx';

const navItems = [
  { to: '/', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/content', icon: Sparkles, label: 'Content' },
  { to: '/scheduler', icon: Calendar, label: 'Scheduler' },
  { to: '/niches', icon: Tag, label: 'Niches' },
  { to: '/affiliates', icon: Link2, label: 'Affiliates' },
  { to: '/analytics', icon: BarChart3, label: 'Analytics' },
  { to: '/settings', icon: Settings, label: 'Settings' },
];

export default function Layout() {
  const { user, logout } = useAuthStore();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="flex h-screen bg-gray-50">
      {/* Mobile overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={clsx(
          'fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col transition-transform duration-200 ease-in-out lg:static lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        )}
      >
        <div className="p-6 border-b border-gray-100 flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold text-gray-900">AutoThreads</h1>
            <p className="text-sm text-gray-500 mt-1">AI Content Automation</p>
          </div>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden p-1 text-gray-500 hover:text-gray-700"
            aria-label="Close sidebar"
          >
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 p-4 space-y-1 overflow-y-auto" aria-label="Main navigation">
          {navItems.map(({ to, icon: Icon, label }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              onClick={() => setSidebarOpen(false)}
              className={({ isActive }) =>
                clsx(
                  'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-blue-50 text-blue-700'
                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                )
              }
            >
              <Icon size={18} />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="p-4 border-t border-gray-100">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
              <span className="text-sm font-medium text-blue-700">
                {user?.name?.[0]?.toUpperCase()}
              </span>
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-gray-900 truncate">{user?.name}</p>
              <p className="text-xs text-gray-500 truncate">{user?.plan} plan</p>
            </div>
          </div>
          <button
            onClick={logout}
            className="flex items-center gap-2 text-sm text-gray-500 hover:text-red-600 transition-colors"
          >
            <LogOut size={16} />
            Sign out
          </button>
        </div>
      </aside>

      {/* Main content */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Mobile header */}
        <header className="lg:hidden flex items-center gap-3 p-4 bg-white border-b border-gray-200">
          <button
            onClick={() => setSidebarOpen(true)}
            className="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg"
            aria-label="Open sidebar"
          >
            <Menu size={20} />
          </button>
          <h1 className="text-lg font-bold text-gray-900">AutoThreads</h1>
        </header>

        <main className="flex-1 overflow-auto">
          <div className="p-4 sm:p-6 lg:p-8">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
