import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RefreshCw, XCircle, Zap, Play, Activity, Clock, AlertTriangle, Timer } from 'lucide-react';
import api from '../../services/api';
import PageHeader from '../../components/ui/PageHeader';
import StatCard from '../../components/ui/StatCard';
import StatusBadge from '../../components/admin/StatusBadge';
import AdminTabs from '../../components/admin/AdminTabs';

const STATUS_FILTERS = [
  { value: '', label: 'All' },
  { value: 'queued', label: 'Pending / Retrying' },
  { value: 'processing', label: 'Publishing' },
  { value: 'posted', label: 'Published' },
  { value: 'failed', label: 'Failed' },
];

const TABS = [
  { id: 'queue', label: 'Content queue' },
  { id: 'worker', label: 'Publish worker' },
];

function QueueTab() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState('');

  const { data: posts = [], isLoading, refetch } = useQuery({
    queryKey: ['admin-queue', status],
    queryFn: () => api.get('/admin/queue', { params: { status: status || undefined, limit: 100 } }).then((r) => r.data.data),
    refetchInterval: 30000,
  });

  const mutate = useMutation({
    mutationFn: ({ id, action }) => api.post(`/admin/queue/${id}/${action}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-queue'] }),
  });

  return (
    <>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-2">
          {STATUS_FILTERS.map(({ value, label }) => (
            <button
              key={value || 'all'}
              type="button"
              onClick={() => setStatus(value)}
              className={status === value ? 'btn-primary text-xs py-1.5' : 'btn-secondary text-xs py-1.5'}
            >
              {label}
            </button>
          ))}
        </div>
        <button type="button" onClick={() => refetch()} className="btn-secondary inline-flex items-center gap-2 text-xs">
          <RefreshCw size={14} /> Refresh
        </button>
      </div>

      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-800/50">
              <tr>
                <th className="px-4 py-3">ID</th>
                <th className="px-4 py-3">User</th>
                <th className="px-4 py-3">Scheduled</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Threads</th>
                <th className="px-4 py-3">Hook</th>
                <th className="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">Loading…</td></tr>
              ) : posts.length === 0 ? (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No posts in queue</td></tr>
              ) : (
                posts.map((p) => (
                  <tr key={p.id} className="border-b border-slate-100 dark:border-slate-800">
                    <td className="px-4 py-3 font-mono text-xs">#{p.id}</td>
                    <td className="px-4 py-3">
                      <p className="text-subheading font-medium">{p.user_name}</p>
                      <p className="text-muted text-xs">{p.user_email}</p>
                    </td>
                    <td className="px-4 py-3 text-muted whitespace-nowrap">{p.scheduled_at}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={p.display_status} label={p.display_status} />
                      {p.retry_count > 0 && <p className="text-muted mt-1 text-xs">Retries: {p.retry_count}</p>}
                    </td>
                    <td className="px-4 py-3">@{p.threads_account ?? '—'}</td>
                    <td className="px-4 py-3 max-w-[200px] truncate text-muted" title={p.hook}>{p.hook ?? '—'}</td>
                    <td className="px-4 py-3">
                      {['queued', 'failed', 'processing'].includes(p.status) && (
                        <div className="flex flex-wrap gap-1">
                          <button type="button" title="Retry now" className="btn-icon" onClick={() => mutate.mutate({ id: p.id, action: 'retry' })}><RefreshCw size={14} /></button>
                          <button type="button" title="Force publish" className="btn-icon text-brand-500" onClick={() => mutate.mutate({ id: p.id, action: 'force-publish' })}><Zap size={14} /></button>
                          <button type="button" title="Cancel" className="btn-icon text-red-500" onClick={() => mutate.mutate({ id: p.id, action: 'cancel' })}><XCircle size={14} /></button>
                        </div>
                      )}
                      {p.last_error && (
                        <p className="text-red-600 mt-1 max-w-[180px] truncate text-xs dark:text-red-400" title={p.last_error}>{p.last_error}</p>
                      )}
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

function WorkerTab() {
  const queryClient = useQueryClient();

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['admin-worker'],
    queryFn: () => api.get('/admin/worker').then((r) => r.data.data),
    refetchInterval: 30000,
  });

  const runWorker = useMutation({
    mutationFn: () => api.post('/admin/worker/run'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-worker'] });
      queryClient.invalidateQueries({ queryKey: ['admin-queue'] });
    },
  });

  const avgSec = data?.avg_processing_ms ? (data.avg_processing_ms / 1000).toFixed(1) : '0';

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        {[1, 2, 3, 4].map((i) => <div key={i} className="card skeleton h-28" />)}
      </div>
    );
  }

  return (
    <>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <StatusBadge status={data?.health} label={data?.health_label} />
          <span className="text-muted text-sm">Last run: {data?.last_run ?? 'Never'}</span>
        </div>
        <div className="flex gap-2">
          <button type="button" onClick={() => refetch()} className="btn-secondary inline-flex items-center gap-2 text-xs">
            <RefreshCw size={14} /> Refresh
          </button>
          <button type="button" onClick={() => runWorker.mutate()} disabled={runWorker.isPending} className="btn-primary inline-flex items-center gap-2 text-xs">
            <Play size={14} /> Run worker now
          </button>
        </div>
      </div>

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Last worker run" value={data?.last_run?.slice(0, 16) ?? '—'} icon={Clock} accent="brand" />
        <StatCard label="Published today" value={data?.published_today ?? 0} icon={Activity} accent="emerald" />
        <StatCard label="Failed today" value={data?.failed_today ?? 0} icon={AlertTriangle} accent="violet" />
        <StatCard label="Avg processing" value={`${avgSec}s`} icon={Timer} accent="amber" />
      </div>

      {runWorker.data && (
        <div className="card mb-6 p-4">
          <h3 className="text-heading mb-2 font-semibold">Last manual run</h3>
          <pre className="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">
            {JSON.stringify(runWorker.data.data?.data ?? runWorker.data.data, null, 2)}
          </pre>
        </div>
      )}

      <div className="card p-4">
        <h3 className="text-heading mb-3 font-semibold">Recent worker log</h3>
        <div className="max-h-96 overflow-y-auto font-mono text-xs">
          {(data?.log_lines ?? []).length === 0 ? (
            <p className="text-muted">No log lines yet. Ensure publish_posts.php cron is configured.</p>
          ) : (
            data.log_lines.map((line, i) => (
              <div key={i} className="border-b border-slate-100 py-1 text-slate-600 dark:border-slate-800 dark:text-slate-400">{line}</div>
            ))
          )}
        </div>
        {data?.log_path && <p className="text-muted mt-3 text-xs">Log file: {data.log_path}</p>}
      </div>
    </>
  );
}

export default function AdminPublishing() {
  const [tab, setTab] = useState('queue');

  return (
    <div>
      <PageHeader
        title="Publishing monitor"
        description="Content queue and publish worker — switch tabs to debug failed posts."
      />
      <AdminTabs tabs={TABS} active={tab} onChange={setTab} />
      {tab === 'queue' ? <QueueTab /> : <WorkerTab />}
    </div>
  );
}
