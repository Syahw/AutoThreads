import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Users, Calendar, AlertTriangle, Activity, ArrowRight, BarChart3, Megaphone, Trash2,
  UsersRound, User, Crown,
} from 'lucide-react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import clsx from 'clsx';
import api from '../../services/api';
import PageHeader from '../../components/ui/PageHeader';
import StatCard from '../../components/ui/StatCard';
import StatusBadge from '../../components/admin/StatusBadge';

const TARGETS = [
  { value: 'all', label: 'All users', icon: UsersRound, description: 'Everyone on the platform' },
  { value: 'free', label: 'Free users', icon: User, description: 'Free plan only' },
  { value: 'paid', label: 'Paid users', icon: Crown, description: 'Starter, Pro & Enterprise' },
];

const TARGET_BADGE = {
  all: 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-950/50 dark:text-brand-300',
  free: 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300',
  paid: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/50 dark:text-amber-300',
};

function TargetAudiencePicker({ value, onChange }) {
  return (
    <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
      {TARGETS.map(({ value: id, label, icon: Icon, description }) => {
        const selected = value === id;
        return (
          <button
            key={id}
            type="button"
            onClick={() => onChange(id)}
            className={clsx(
              'flex flex-col items-start rounded-xl border p-3 text-left transition-all',
              selected
                ? 'border-brand-400 bg-brand-50/80 shadow-sm ring-2 ring-brand-500/25 dark:border-brand-500 dark:bg-brand-950/40 dark:ring-brand-500/30'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800/50 dark:hover:border-slate-500'
            )}
          >
            <span className={clsx('mb-2 flex h-8 w-8 items-center justify-center rounded-lg', selected ? 'bg-brand-500/15 text-brand-600 dark:text-brand-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400')}>
              <Icon size={16} />
            </span>
            <span className="text-subheading text-sm font-semibold">{label}</span>
            <span className="text-muted mt-0.5 text-xs">{description}</span>
          </button>
        );
      })}
    </div>
  );
}

function TargetBadge({ target }) {
  const key = target ?? 'all';
  const meta = TARGETS.find((t) => t.value === key) ?? TARGETS[0];
  const Icon = meta.icon;

  return (
    <span className={clsx('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', TARGET_BADGE[key] ?? TARGET_BADGE.all)}>
      <Icon size={12} />
      {meta.label}
    </span>
  );
}

export default function AdminDashboard() {
  const queryClient = useQueryClient();
  const [form, setForm] = useState({ title: '', message: '', target: 'all', active: true });

  const { data, isLoading } = useQuery({
    queryKey: ['admin-dashboard'],
    queryFn: () => api.get('/admin/dashboard').then((r) => r.data.data),
    refetchInterval: 60000,
  });

  const saveAnnouncement = useMutation({
    mutationFn: (body) => api.post('/admin/announcements', body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-dashboard'] });
      setForm({ title: '', message: '', target: 'all', active: true });
    },
  });

  const deleteAnnouncement = useMutation({
    mutationFn: (id) => api.delete(`/admin/announcements/${encodeURIComponent(id)}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-dashboard'] }),
  });

  const handleDeleteAnnouncement = (announcement) => {
    if (!window.confirm(`Delete "${announcement.title}"? This cannot be undone.`)) return;
    deleteAnnouncement.mutate(announcement.id);
  };

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        {[1, 2, 3, 4].map((i) => <div key={i} className="card skeleton h-28" />)}
      </div>
    );
  }

  const worker = data?.worker ?? {};
  const analytics = data?.analytics ?? {};
  const announcements = data?.announcements ?? [];
  const chartData = analytics.posts_per_day ?? [];

  const quickLinks = [
    { to: '/admin/users', label: 'Users & subscriptions', desc: 'Accounts, plans, impersonate' },
    { to: '/admin/publishing', label: 'Publishing monitor', desc: 'Queue + worker health' },
    { to: '/admin/logs', label: 'System logs', desc: 'Errors and worker output' },
    { to: '/admin/settings', label: 'Platform settings', desc: 'Limits, features, scheduler' },
  ];

  return (
    <div>
      <PageHeader
        title="Admin home"
        description="Platform overview, analytics, announcements, and quick access to admin tools."
      />

      <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Total users" value={data?.users_total ?? 0} icon={Users} accent="brand" />
        <StatCard label="Queue pending" value={data?.queue_pending ?? 0} icon={Calendar} accent="amber" />
        <StatCard label="Queue failed" value={data?.queue_failed ?? 0} icon={AlertTriangle} accent="violet" />
        <StatCard label="Published today" value={data?.published_today ?? 0} icon={Activity} accent="emerald" />
      </div>

      {/* Platform analytics */}
      <div className="mb-8">
        <div className="mb-4 flex items-center gap-2">
          <BarChart3 size={18} className="text-brand-500" />
          <h2 className="text-heading text-lg font-semibold">Platform analytics</h2>
        </div>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="card p-6 lg:col-span-2">
            <h3 className="text-subheading mb-4 font-medium">Posts published (7 days)</h3>
            <div className="h-56">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-slate-200 dark:stroke-slate-700" />
                  <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                  <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                  <Tooltip />
                  <Bar dataKey="count" fill="#8b5cf6" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>
          <div className="space-y-4">
            <div className="card p-5">
              <h3 className="text-subheading mb-3 font-medium">Publishing rates (7 days)</h3>
              <div className="space-y-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted">Success rate</span>
                  <span className="font-semibold text-emerald-600">{analytics.success_rate ?? 0}%</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted">Failure rate</span>
                  <span className="font-semibold text-red-600">{analytics.failure_rate ?? 0}%</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted">Published</span>
                  <span className="text-subheading font-medium">{analytics.published_week ?? 0}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted">Failed</span>
                  <span className="text-subheading font-medium">{analytics.failed_week ?? 0}</span>
                </div>
              </div>
            </div>
            <div className="card p-5">
              <h3 className="text-subheading mb-3 font-medium">Most active users</h3>
              {(analytics.top_users ?? []).length === 0 ? (
                <p className="text-muted text-sm">No published posts this week.</p>
              ) : (
                <ul className="space-y-2 text-sm">
                  {analytics.top_users.map((u) => (
                    <li key={u.id} className="flex justify-between">
                      <span className="text-subheading truncate pr-2">{u.name}</span>
                      <span className="text-muted shrink-0">{u.post_count} posts</span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Announcements */}
      <div className="mb-8">
        <div className="mb-4 flex items-center gap-2">
          <Megaphone size={18} className="text-brand-500" />
          <h2 className="text-heading text-lg font-semibold">Announcements</h2>
        </div>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div className="card p-6">
            <h3 className="text-subheading mb-4 font-medium">Send notice</h3>
            <div className="space-y-4">
              <label className="form-group block">
                <span className="form-label">Title</span>
                <input
                  className="input-field"
                  placeholder="e.g. New feature released"
                  value={form.title}
                  onChange={(e) => setForm({ ...form, title: e.target.value })}
                />
              </label>
              <label className="form-group block">
                <span className="form-label">Message</span>
                <textarea
                  className="input-field"
                  placeholder="Message for users…"
                  value={form.message}
                  onChange={(e) => setForm({ ...form, message: e.target.value })}
                />
              </label>
              <div className="form-group">
                <span className="form-label">Target audience</span>
                <TargetAudiencePicker
                  value={form.target}
                  onChange={(target) => setForm({ ...form, target })}
                />
              </div>
              <label className="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" className="checkbox-field" checked={form.active} onChange={(e) => setForm({ ...form, active: e.target.checked })} />
                Active (visible to users)
              </label>
              <button
                type="button"
                className="btn-primary"
                disabled={saveAnnouncement.isPending}
                onClick={() => saveAnnouncement.mutate(form)}
              >
                {saveAnnouncement.isPending ? 'Publishing…' : 'Publish announcement'}
              </button>
            </div>
          </div>
          <div className="card p-6">
            <h3 className="text-subheading mb-4 font-medium">Active announcements</h3>
            {announcements.length === 0 ? (
              <p className="text-muted text-sm">No announcements yet.</p>
            ) : (
              <ul className="space-y-3">
                {announcements.map((a) => (
                  <li key={a.id} className="rounded-xl border border-slate-100 p-4 dark:border-slate-700">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <p className="text-subheading font-medium">{a.title}</p>
                        <p className="text-muted mt-1 text-sm">{a.message}</p>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                          <StatusBadge status={a.active ? 'active' : 'expired'} label={a.active ? 'Active' : 'Inactive'} />
                          <TargetBadge target={a.target} />
                        </div>
                      </div>
                      <button
                        type="button"
                        className="btn-icon text-red-500 hover:border-red-200 hover:bg-red-50 dark:hover:border-red-900 dark:hover:bg-red-950/40"
                        title="Delete announcement"
                        disabled={deleteAnnouncement.isPending}
                        onClick={() => handleDeleteAnnouncement(a)}
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>

      <div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="card p-6">
          <h2 className="text-heading mb-4 text-lg font-semibold">Worker status</h2>
          <div className="space-y-3 text-sm">
            <div className="flex items-center justify-between">
              <span className="text-muted">Health</span>
              <StatusBadge status={worker.health} label={worker.health_label} />
            </div>
            <div className="flex items-center justify-between">
              <span className="text-muted">Last run</span>
              <span className="text-subheading font-medium">{worker.last_run ?? 'Never'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-muted">Published today</span>
              <span className="text-subheading font-medium">{worker.published_today ?? 0}</span>
            </div>
          </div>
          <Link to="/admin/publishing" className="btn-secondary mt-4 inline-flex text-sm">
            Open publishing monitor
          </Link>
        </div>

        <div className="card p-6">
          <h2 className="text-heading mb-4 text-lg font-semibold">AI usage (7 days)</h2>
          <div className="space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-muted">Tokens</span>
              <span className="text-subheading font-medium">{(data?.ai_tokens_week ?? 0).toLocaleString()}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted">Est. cost</span>
              <span className="text-subheading font-medium">RM{(data?.ai_cost_week ?? 0).toFixed(2)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted">Generated today</span>
              <span className="text-subheading font-medium">{data?.posts_generated_today ?? 0}</span>
            </div>
          </div>
        </div>
      </div>

      <div className="card p-6">
        <h2 className="text-heading mb-5 text-lg font-semibold">Quick links</h2>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {quickLinks.map(({ to, label, desc }) => (
            <Link
              key={to}
              to={to}
              className="group flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-4 transition-all hover:border-brand-200 hover:bg-brand-50/50 dark:border-slate-700 dark:bg-slate-800/40"
            >
              <div>
                <p className="text-subheading font-medium">{label}</p>
                <p className="text-muted mt-0.5 text-xs">{desc}</p>
              </div>
              <ArrowRight size={18} className="text-slate-300 group-hover:text-brand-500" />
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
