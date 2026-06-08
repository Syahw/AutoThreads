import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../services/api';
import {
  Calendar, Clock, Loader2, X, Terminal, Play, RefreshCw, AlertCircle, CheckCircle2,
} from 'lucide-react';
import PageHeader from '../components/ui/PageHeader';
import EmptyState from '../components/ui/EmptyState';
import StatusBadge from '../components/ui/StatusBadge';
import { formatScheduledAt } from '../utils/schedule';

export default function Scheduler() {
  const queryClient = useQueryClient();
  const [runResult, setRunResult] = useState(null);
  const [showLog, setShowLog] = useState(true);

  const { data: settings } = useQuery({
    queryKey: ['scheduler-settings'],
    queryFn: () => api.get('/scheduler/settings').then((r) => r.data.data),
  });

  const { data: diagnostics, refetch: refetchDiagnostics } = useQuery({
    queryKey: ['scheduler-diagnostics'],
    queryFn: () => api.get('/scheduler/diagnostics').then((r) => r.data.data),
    refetchInterval: 30000,
  });

  const { data: workerLog, refetch: refetchLog } = useQuery({
    queryKey: ['scheduler-worker-log'],
    queryFn: () => api.get('/scheduler/worker-log?lines=60').then((r) => r.data.data),
    refetchInterval: showLog ? 15000 : false,
  });

  const { data: scheduled, isLoading } = useQuery({
    queryKey: ['scheduler'],
    queryFn: () => api.get('/scheduler?status=queued').then((r) => r.data.data),
  });

  const { data: failedPosts } = useQuery({
    queryKey: ['scheduler-failed'],
    queryFn: () => api.get('/scheduler?status=failed').then((r) => r.data.data),
  });

  const cancelMutation = useMutation({
    mutationFn: (id) => api.put(`/scheduler/${id}/cancel`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['scheduler'] });
      queryClient.invalidateQueries({ queryKey: ['content'] });
      queryClient.invalidateQueries({ queryKey: ['scheduler-diagnostics'] });
    },
  });

  const runNowMutation = useMutation({
    mutationFn: () => api.post('/scheduler/run-now'),
    onSuccess: (res) => {
      setRunResult({ ok: true, data: res.data.data });
      queryClient.invalidateQueries({ queryKey: ['scheduler'] });
      queryClient.invalidateQueries({ queryKey: ['scheduler-failed'] });
      queryClient.invalidateQueries({ queryKey: ['content'] });
      refetchDiagnostics();
      refetchLog();
    },
    onError: (err) => {
      setRunResult({
        ok: false,
        message: err.response?.data?.message || err.message || 'Run failed',
      });
      refetchLog();
    },
  });

  const cronOk = diagnostics?.cron_running_recently;

  return (
    <div>
      <PageHeader
        title="Scheduler"
        description="Posts publish when scheduled time passes and the publish worker runs every minute."
        action={
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => {
                refetchDiagnostics();
                refetchLog();
              }}
              className="btn-secondary"
            >
              <RefreshCw size={16} /> Refresh status
            </button>
            <button
              type="button"
              onClick={() => runNowMutation.mutate()}
              disabled={runNowMutation.isPending || (diagnostics?.due_now_count ?? 0) === 0}
              className="btn-primary"
              title="Publish due posts now (same as cron, without waiting for Task Scheduler)"
            >
              {runNowMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Play size={16} />}
              Run due posts now
            </button>
            <Link to="/content" className="btn-secondary">
              <Calendar size={16} /> Content
            </Link>
          </div>
        }
      />

      {/* Worker status */}
      <div className="card mb-6 p-6">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Terminal size={18} className="text-brand-600 dark:text-brand-400" />
            <h2 className="text-heading text-lg font-semibold">Publish worker status</h2>
          </div>
          <span
            className={`badge ${cronOk ? 'badge-approved' : 'badge-scheduled'}`}
          >
            {cronOk ? 'Cron ran in last 3 min' : 'Cron not detected recently'}
          </span>
        </div>

        <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <div className="panel-muted p-3">
            <dt className="text-muted text-xs">Server time</dt>
            <dd className="text-heading font-semibold">{diagnostics?.server_now ?? '—'}</dd>
            <dd className="text-muted text-xs">{diagnostics?.timezone}</dd>
          </div>
          <div className="panel-muted p-3">
            <dt className="text-muted text-xs">Queued</dt>
            <dd className="text-heading font-semibold">{diagnostics?.queued_count ?? 0}</dd>
          </div>
          <div className="panel-muted p-3">
            <dt className="text-muted text-xs">Due right now</dt>
            <dd className={`font-semibold ${(diagnostics?.due_now_count ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-heading'}`}>
              {diagnostics?.due_now_count ?? 0}
            </dd>
          </div>
          <div className="panel-muted p-3">
            <dt className="text-muted text-xs">Failed / stuck</dt>
            <dd className="text-heading font-semibold">
              {diagnostics?.failed_count ?? 0} failed · {diagnostics?.processing_count ?? 0} processing
            </dd>
          </div>
        </dl>

        {!cronOk && (
          <div className="alert-error mt-4 flex items-start gap-2">
            <AlertCircle size={18} className="shrink-0" />
            <div className="text-sm">
              <p className="font-medium">Task Scheduler may not be running or PHP path is wrong.</p>
              <p className="mt-1 opacity-90">
                Point the task at <code className="code-inline">backend\cron\run_publish.bat</code> and check{' '}
                <code className="code-inline">backend\storage\logs\cron-publish.log</code> below.
              </p>
            </div>
          </div>
        )}

        {runResult && (
          <div className={`mt-4 flex items-start gap-2 rounded-xl border px-4 py-3 text-sm ${runResult.ok ? 'alert-success' : 'alert-error'}`}>
            {runResult.ok ? <CheckCircle2 size={18} /> : <AlertCircle size={18} />}
            <div>
              {runResult.ok ? (
                <>
                  <p className="font-medium">
                    Processed {runResult.data?.processed ?? 0} · Published {runResult.data?.published ?? 0} · Failed {runResult.data?.failed ?? 0}
                  </p>
                  {runResult.data?.details?.map((d) => (
                    <p key={d.scheduled_post_id} className="mt-1 text-xs opacity-90">
                      #{d.scheduled_post_id}: {d.status} — {d.message}
                    </p>
                  ))}
                </>
              ) : (
                <p>{runResult.message}</p>
              )}
            </div>
          </div>
        )}

        {/* <p className="text-muted mt-4 text-xs"> */}
        {/* Log file: <code className="code-inline">{workerLog?.path || 'backend/storage/logs/cron-publish.log'}</code> */}
        {/* </p> */}
        <button
          type="button"
          onClick={() => setShowLog((v) => !v)}
          className="text-brand-600 dark:text-brand-400 mt-2 text-xs font-semibold hover:underline"
        >
          {showLog ? 'Hide' : 'Show'} cron log
        </button>
        {showLog && (
          <pre className="mt-3 max-h-64 overflow-auto rounded-xl border border-slate-200 bg-slate-900 p-4 text-xs leading-relaxed text-slate-100 dark:border-slate-700">
            {workerLog?.lines?.length
              ? workerLog.lines.join('\n')
              : '(empty — run run_publish.bat once or wait for Task Scheduler)'}
          </pre>
        )}
      </div>

      <div className="card mb-6 p-6">
        <h2 className="text-heading mb-2 text-lg font-semibold">Windows Task Scheduler</h2>
        <p className="text-muted mb-3 text-sm">
          Program: <code className="code-inline">C:\wamp64\bin\php\php8.x.x\php.exe</code> is NOT needed if you run the batch file:
        </p>
        <pre className="overflow-x-auto rounded-xl border border-slate-200 bg-slate-900 p-4 text-xs text-slate-100 dark:border-slate-700">
          {`Action: Start a program
Program: C:\\wamp64\\www\\AutoThreads\\backend\\cron\\run_publish.bat
Start in: C:\\wamp64\\www\\AutoThreads\\backend
Trigger: Every 1 minute`}
        </pre>
        <p className="text-muted mt-3 text-xs">
          Test manually: open CMD → <code className="code-inline">cd backend</code> → <code className="code-inline">cron\\run_publish.bat</code>
        </p>
      </div>

      <div className="card overflow-hidden mb-6">
        <div className="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
          <h2 className="text-heading text-lg font-semibold">Queued posts</h2>
          <p className="text-muted text-sm">Waiting for worker when scheduled time ≤ server time.</p>
        </div>

        {isLoading ? (
          <div className="space-y-3 p-6">
            {[1, 2].map((i) => (
              <div key={i} className="skeleton h-16 rounded-xl" />
            ))}
          </div>
        ) : scheduled?.length === 0 ? (
          <EmptyState
            icon={Calendar}
            title="No posts scheduled"
            description="Approve content, pick date/time on Content Generator, then Schedule."
            action={
              <Link to="/content" className="btn-primary">
                Go to content
              </Link>
            }
          />
        ) : (
          <ul className="divide-y divide-slate-100 dark:divide-slate-800">
            {scheduled.map((item) => (
              <li
                key={item.id}
                className="row-hover flex flex-wrap items-center justify-between gap-4 px-6 py-4"
              >
                <div className="min-w-0 flex-1">
                  <p className="text-heading truncate font-medium">
                    {item.generated_post?.hook || item.generatedPost?.hook || 'Scheduled thread'}
                  </p>
                  <div className="text-muted mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                    <span className="flex items-center gap-1">
                      <Clock size={12} />
                      {formatScheduledAt(item, settings?.timezone)}
                    </span>
                    {(item.threads_account?.username || item.threadsAccount?.username) && (
                      <span>@{item.threads_account?.username || item.threadsAccount?.username}</span>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <StatusBadge status={item.status} />
                  <button
                    type="button"
                    onClick={() => cancelMutation.mutate(item.id)}
                    disabled={cancelMutation.isPending}
                    className="btn-danger !py-2 !text-xs"
                  >
                    <X size={14} /> Cancel
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>

      {failedPosts?.length > 0 && (
        <div className="card overflow-hidden">
          <div className="border-b border-red-200 bg-red-50/50 px-6 py-4 dark:border-red-900/50 dark:bg-red-950/30">
            <h2 className="text-lg font-semibold text-red-800 dark:text-red-300">Failed schedules</h2>
            <p className="text-sm text-red-700 dark:text-red-400">Check cron log or use Run due posts now after fixing the issue.</p>
          </div>
          <ul className="divide-y divide-slate-100 dark:divide-slate-800">
            {failedPosts.map((item) => (
              <li key={item.id} className="px-6 py-4">
                <p className="text-heading font-medium">{item.generated_post?.hook || 'Post'}</p>
                {item.last_error && (
                  <p className="mt-1 text-xs text-red-600 dark:text-red-400">{item.last_error}</p>
                )}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
