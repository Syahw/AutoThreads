import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../services/api';
import PageHeader from '../../components/ui/PageHeader';
import StatusBadge from '../../components/admin/StatusBadge';

const LEVELS = ['all', 'error', 'warning', 'info'];

export default function AdminLogs() {
  const [level, setLevel] = useState('all');

  const { data: logs = [], isLoading, refetch } = useQuery({
    queryKey: ['admin-logs', level],
    queryFn: () => api.get('/admin/system-logs', { params: { level, lines: 150 } }).then((r) => r.data.data),
    refetchInterval: 60000,
  });

  return (
    <div>
      <PageHeader
        title="System logs"
        description="Centralized worker and application logs for debugging."
        action={
          <button type="button" onClick={() => refetch()} className="btn-secondary">
            Refresh
          </button>
        }
      />

      <div className="card mb-6 flex flex-wrap gap-2 p-4">
        {LEVELS.map((l) => (
          <button
            key={l}
            type="button"
            onClick={() => setLevel(l)}
            className={level === l ? 'btn-primary text-xs py-1.5 capitalize' : 'btn-secondary text-xs py-1.5 capitalize'}
          >
            {l}
          </button>
        ))}
      </div>

      <div className="card overflow-hidden">
        <div className="max-h-[70vh] overflow-y-auto p-4 font-mono text-xs">
          {isLoading ? (
            <p className="text-muted">Loading logs…</p>
          ) : logs.length === 0 ? (
            <p className="text-muted">No log entries for this filter.</p>
          ) : (
            logs.map((entry, i) => (
              <div
                key={i}
                className="flex flex-wrap items-start gap-2 border-b border-slate-100 py-2 dark:border-slate-800"
              >
                <StatusBadge status={entry.level} label={entry.level} />
                <span className="text-muted shrink-0 uppercase">{entry.source}</span>
                <span className="text-slate-700 break-all dark:text-slate-300">{entry.message}</span>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
