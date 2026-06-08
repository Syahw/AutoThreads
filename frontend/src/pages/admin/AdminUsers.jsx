import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Search, UserCog, Ban, PauseCircle, RotateCcw, LogIn, CreditCard, Clock, AlertCircle, Gift, CheckCircle } from 'lucide-react';
import api from '../../services/api';
import { useAuthStore } from '../../stores/authStore';
import PageHeader from '../../components/ui/PageHeader';
import StatCard from '../../components/ui/StatCard';
import StatusBadge from '../../components/admin/StatusBadge';
import AdminTabs from '../../components/admin/AdminTabs';

const PLANS = ['free', 'starter', 'pro', 'enterprise'];
const TABS = [
  { id: 'users', label: 'Users' },
  { id: 'subscriptions', label: 'Subscriptions' },
];

function UsersTab() {
  const queryClient = useQueryClient();
  const impersonate = useAuthStore((s) => s.impersonate);
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);

  const { data: users = [], isLoading } = useQuery({
    queryKey: ['admin-users', search],
    queryFn: () => api.get('/admin/users', { params: { search: search || undefined } }).then((r) => r.data.data),
  });

  const { data: userDetail } = useQuery({
    queryKey: ['admin-user', selected],
    queryFn: () => api.get(`/admin/users/${selected}`).then((r) => r.data.data),
    enabled: !!selected,
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['admin-users'] });
    queryClient.invalidateQueries({ queryKey: ['admin-subscriptions'] });
    if (selected) queryClient.invalidateQueries({ queryKey: ['admin-user', selected] });
  };

  const isInactive = (user) => user?.status === 'suspended' || user?.status === 'banned' || user?.is_active === false;

  const handleActivate = (user) => {
    if (!window.confirm(`Reactivate ${user.name}? They will be able to log in again.`)) return;
    action.mutate({ id: user.id, type: 'activate' });
  };

  const action = useMutation({
    mutationFn: ({ id, type }) => {
      const map = {
        activate: `/admin/users/${id}/activate`,
        suspend: `/admin/users/${id}/suspend`,
        ban: `/admin/users/${id}/ban`,
        reset: `/admin/users/${id}/reset-quota`,
      };
      return api.post(map[type]);
    },
    onSuccess: invalidate,
  });

  const handleImpersonate = async (id) => {
    if (!window.confirm('Log in as this user? You can return to your admin account from the banner.')) return;
    await impersonate(id);
    window.location.href = '/';
  };

  return (
    <>
      <div className="card mb-6 p-4">
        <div className="relative max-w-md">
          <Search size={16} className="text-muted absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="search"
            placeholder="Search name or email…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="input-field pl-10"
          />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div className="card overflow-hidden xl:col-span-2">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-800/50">
                <tr>
                  <th className="px-4 py-3">User</th>
                  <th className="px-4 py-3">Plan</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Joined</th>
                  <th className="px-4 py-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {isLoading ? (
                  <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">Loading…</td></tr>
                ) : users.length === 0 ? (
                  <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">No users found</td></tr>
                ) : (
                  users.map((u) => (
                    <tr key={u.id} className="border-b border-slate-100 hover:bg-slate-50/50 dark:border-slate-800 dark:hover:bg-slate-800/30">
                      <td className="px-4 py-3">
                        <button type="button" onClick={() => setSelected(u.id)} className="text-left">
                          <p className="text-subheading font-medium">{u.name}</p>
                          <p className="text-muted text-xs">{u.email}</p>
                        </button>
                      </td>
                      <td className="px-4 py-3 capitalize">{u.plan}</td>
                      <td className="px-4 py-3"><StatusBadge status={u.status} /></td>
                      <td className="px-4 py-3 text-muted">{u.joined}</td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-1">
                          <button type="button" title="View" onClick={() => setSelected(u.id)} className="btn-icon"><UserCog size={14} /></button>
                          {isInactive(u) && (
                            <button type="button" title="Activate user" onClick={() => handleActivate(u)} className="btn-icon-success"><CheckCircle size={14} /></button>
                          )}
                          {u.status === 'active' && (
                            <>
                              <button type="button" title="Suspend" onClick={() => action.mutate({ id: u.id, type: 'suspend' })} className="btn-icon"><PauseCircle size={14} /></button>
                              <button type="button" title="Ban" onClick={() => action.mutate({ id: u.id, type: 'ban' })} className="btn-icon text-red-500"><Ban size={14} /></button>
                            </>
                          )}
                          <button type="button" title="Reset quota" onClick={() => action.mutate({ id: u.id, type: 'reset' })} className="btn-icon"><RotateCcw size={14} /></button>
                          <button type="button" title="Impersonate" onClick={() => handleImpersonate(u.id)} className="btn-icon"><LogIn size={14} /></button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        <div className="card p-5">
          <h3 className="text-heading mb-4 font-semibold">User detail</h3>
          {!selected ? (
            <p className="text-muted text-sm">Select a user to view Threads accounts and stats.</p>
          ) : !userDetail ? (
            <p className="text-muted text-sm">Loading…</p>
          ) : (
            <div className="space-y-4 text-sm">
              <div>
                <p className="text-heading font-medium">{userDetail.name}</p>
                <p className="text-muted">{userDetail.email}</p>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                  <StatusBadge status={userDetail.status} />
                  <span className="text-muted capitalize">{userDetail.plan} · {userDetail.role}</span>
                </div>
                {isInactive(userDetail) && (
                  <button
                    type="button"
                    onClick={() => handleActivate(userDetail)}
                    className="btn-success mt-3 w-full !py-2 !text-xs"
                  >
                    <CheckCircle size={14} /> Activate user
                  </button>
                )}
              </div>
              <div className="grid grid-cols-3 gap-2 text-center">
                {['generated_posts', 'scheduled_posts', 'published_posts'].map((key, i) => (
                  <div key={key} className="rounded-lg bg-slate-50 p-2 dark:bg-slate-800/50">
                    <p className="text-heading text-lg font-bold">{userDetail.stats?.[key] ?? 0}</p>
                    <p className="text-muted text-xs">{['Generated', 'Scheduled', 'Published'][i]}</p>
                  </div>
                ))}
              </div>
              <div>
                <p className="text-subheading mb-2 font-medium">Threads accounts</p>
                {(userDetail.threads_accounts ?? []).length === 0 ? (
                  <p className="text-muted text-xs">None connected</p>
                ) : (
                  <ul className="space-y-1">
                    {userDetail.threads_accounts.map((a) => (
                      <li key={a.id} className="flex justify-between rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/50">
                        <span>@{a.username}</span>
                        <StatusBadge status={a.is_active ? 'active' : 'suspended'} label={a.is_active ? 'Active' : 'Inactive'} />
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  );
}

function SubscriptionsTab() {
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ['admin-subscriptions'],
    queryFn: () => api.get('/admin/subscriptions').then((r) => r.data),
  });

  const update = useMutation({
    mutationFn: ({ userId, body }) => api.put(`/admin/subscriptions/${userId}`, body),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-subscriptions'] }),
  });

  const rows = data?.data ?? [];
  const summary = data?.summary ?? {};

  return (
    <>
      <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Active" value={summary.active ?? 0} icon={CreditCard} accent="emerald" />
        <StatCard label="Trial" value={summary.trial ?? 0} icon={Clock} accent="brand" />
        <StatCard label="Expired" value={summary.expired ?? 0} icon={AlertCircle} accent="amber" />
        <StatCard label="Failed payments" value={summary.payment_failed ?? 0} icon={Gift} accent="violet" />
      </div>

      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-800/50">
              <tr>
                <th className="px-4 py-3">User</th>
                <th className="px-4 py-3">Plan</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Expires</th>
                <th className="px-4 py-3">Bonus</th>
                <th className="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">Loading…</td></tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.user_id} className="border-b border-slate-100 dark:border-slate-800">
                    <td className="px-4 py-3">
                      <p className="text-subheading font-medium">{row.name}</p>
                      <p className="text-muted text-xs">{row.email}</p>
                    </td>
                    <td className="px-4 py-3">
                      <select
                        value={row.plan}
                        onChange={(e) => update.mutate({ userId: row.user_id, body: { plan: e.target.value } })}
                        className="select-field input-sm capitalize"
                      >
                        {PLANS.map((p) => <option key={p} value={p}>{p}</option>)}
                      </select>
                    </td>
                    <td className="px-4 py-3"><StatusBadge status={row.status} /></td>
                    <td className="px-4 py-3 text-muted text-xs">
                      {row.subscription_expires_at ? new Date(row.subscription_expires_at).toLocaleDateString() : '—'}
                    </td>
                    <td className="px-4 py-3">{row.bonus_credits ?? 0}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-1">
                        <button type="button" className="btn-secondary text-xs py-1" onClick={() => update.mutate({ userId: row.user_id, body: { extend_days: 30 } })}>
                          +30 days
                        </button>
                        <button
                          type="button"
                          className="btn-secondary text-xs py-1"
                          onClick={() => {
                            const val = window.prompt('Bonus credits to grant:', '10');
                            if (val) update.mutate({ userId: row.user_id, body: { bonus_credits: parseInt(val, 10) } });
                          }}
                        >
                          Grant credits
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}

export default function AdminUsers() {
  const [tab, setTab] = useState('users');

  return (
    <div>
      <PageHeader
        title="Users & subscriptions"
        description="Manage accounts, plans, trials, and billing actions."
      />
      <AdminTabs tabs={TABS} active={tab} onChange={setTab} />
      {tab === 'users' ? <UsersTab /> : <SubscriptionsTab />}
    </div>
  );
}
