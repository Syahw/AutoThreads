import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  BarChart3, TrendingUp, Eye, Heart, RefreshCw, Loader2, MessageSquare,
} from 'lucide-react';
import {
  ResponsiveContainer, AreaChart, Area, BarChart, Bar, XAxis, YAxis,
  CartesianGrid, Tooltip, Legend,
} from 'recharts';
import api from '../services/api';
import PageHeader from '../components/ui/PageHeader';
import StatCard from '../components/ui/StatCard';
import EmptyState from '../components/ui/EmptyState';
import { useThemeStore } from '../stores/themeStore';
import { resolveTheme } from '../lib/theme';
import { formatDate } from '../utils/date';
import { useTranslation } from '../i18n';

function useChartTheme() {
  const mode = useThemeStore((s) => s.mode);
  const isDark = resolveTheme(mode) === 'dark';
  return {
    isDark,
    grid: isDark ? '#334155' : '#e2e8f0',
    axis: isDark ? '#94a3b8' : '#64748b',
    tooltipBg: isDark ? '#1e293b' : '#ffffff',
    tooltipBorder: isDark ? '#475569' : '#e2e8f0',
    tooltipText: isDark ? '#f1f5f9' : '#0f172a',
  };
}

function ChartTooltip({ active, payload, label, theme }) {
  if (!active || !payload?.length) return null;

  return (
    <div
      className="rounded-lg border px-3 py-2 text-xs shadow-lg"
      style={{
        backgroundColor: theme.tooltipBg,
        borderColor: theme.tooltipBorder,
        color: theme.tooltipText,
      }}
    >
      <p className="mb-1 font-medium">{label}</p>
      {payload.map((entry) => (
        <p key={entry.name} style={{ color: entry.color }}>
          {entry.name}: {Number(entry.value).toLocaleString()}
        </p>
      ))}
    </div>
  );
}

export default function Analytics() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const theme = useChartTheme();

  const { data: overview, isLoading: overviewLoading } = useQuery({
    queryKey: ['analytics'],
    queryFn: () => api.get('/analytics/overview').then((r) => r.data.data),
  });

  const { data: trend = [], isLoading: trendLoading } = useQuery({
    queryKey: ['analytics-trend'],
    queryFn: () => api.get('/analytics/trend').then((r) => r.data.data),
  });

  const { data: topPosts = [], isLoading: postsLoading } = useQuery({
    queryKey: ['analytics-posts'],
    queryFn: () => api.get('/analytics/posts').then((r) => r.data.data),
  });

  const { data: bestTimes = [], isLoading: timesLoading } = useQuery({
    queryKey: ['analytics-best-times'],
    queryFn: () => api.get('/analytics/best-times').then((r) => r.data.data),
  });

  const { data: bestHooks = [], isLoading: hooksLoading } = useQuery({
    queryKey: ['analytics-best-hooks'],
    queryFn: () => api.get('/analytics/best-hooks').then((r) => r.data.data),
  });

  const collectMutation = useMutation({
    mutationFn: () => api.post('/analytics/collect'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['analytics'] });
      queryClient.invalidateQueries({ queryKey: ['analytics-trend'] });
      queryClient.invalidateQueries({ queryKey: ['analytics-posts'] });
      queryClient.invalidateQueries({ queryKey: ['analytics-best-times'] });
      queryClient.invalidateQueries({ queryKey: ['analytics-best-hooks'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard-stats'] });
    },
  });

  const stats = [
    { label: t('analytics.totalPosts'), value: overview?.total_posts ?? 0, icon: BarChart3, accent: 'brand' },
    { label: t('analytics.published'), value: overview?.published_posts ?? 0, icon: Eye, accent: 'emerald' },
    { label: t('analytics.impressions'), value: (overview?.total_impressions ?? 0).toLocaleString(), icon: TrendingUp, accent: 'amber' },
    { label: t('analytics.engagement'), value: (overview?.total_engagement ?? 0).toLocaleString(), icon: Heart, accent: 'violet' },
  ];

  const trendData = trend.map((row) => ({
    date: formatDate(row.date),
    impressions: Number(row.impressions),
    engagement: Number(row.engagement),
  }));

  const postsData = topPosts.map((post, i) => ({
    name: post.hook ? post.hook.slice(0, 28) + (post.hook.length > 28 ? '…' : '') : t('analytics.postName', { n: i + 1 }),
    engagement: Number(post.engagement),
    impressions: Number(post.impressions),
  }));

  const timesData = bestTimes.map((slot) => ({
    label: `${slot.day?.slice(0, 3)} ${slot.hour}:00`,
    engagement: Number(slot.engagement),
  }));

  const chartsLoading = trendLoading || postsLoading || timesLoading || hooksLoading;
  const hasPublished = (overview?.published_posts ?? 0) > 0;
  const hasMetrics = (overview?.total_impressions ?? 0) > 0 || (overview?.total_engagement ?? 0) > 0;
  const showEmptyCharts = !chartsLoading && hasPublished && !hasMetrics;

  const impressionsLabel = t('analytics.impressions');
  const engagementLabel = t('analytics.engagement');

  return (
    <div>
      <PageHeader
        title={t('analytics.title')}
        description={t('analytics.description')}
        action={
          <button
            type="button"
            onClick={() => collectMutation.mutate()}
            disabled={collectMutation.isPending || !hasPublished}
            className="btn btn-secondary"
            title={!hasPublished ? t('analytics.refreshTitle') : undefined}
          >
            {collectMutation.isPending ? (
              <Loader2 size={16} className="animate-spin" />
            ) : (
              <RefreshCw size={16} />
            )}
            {t('analytics.refreshMetrics')}
          </button>
        }
      />

      {collectMutation.isSuccess && (
        <div className="alert-success mb-6">
          {t('analytics.collectSuccess', { count: collectMutation.data?.data?.data?.collected ?? 0 })}
          {collectMutation.data?.data?.data?.failed > 0 && (
            <span className="ml-1">
              {t('analytics.collectPartial', { count: collectMutation.data.data.data.failed })}
            </span>
          )}
        </div>
      )}

      {collectMutation.isError && (
        <div className="alert-error mb-6">
          {collectMutation.error?.response?.data?.message || t('analytics.collectFailed')}
        </div>
      )}

      {overviewLoading ? (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="card skeleton h-28" />
          ))}
        </div>
      ) : (
        <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          {stats.map((s) => (
            <StatCard key={s.label} {...s} />
          ))}
        </div>
      )}

      {chartsLoading ? (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {[1, 2].map((i) => (
            <div key={i} className="card skeleton h-72" />
          ))}
        </div>
      ) : !hasPublished ? (
        <div className="card">
          <EmptyState
            icon={BarChart3}
            title={t('analytics.emptyNoPublishedTitle')}
            description={t('analytics.emptyNoPublishedDesc')}
          />
        </div>
      ) : showEmptyCharts ? (
        <div className="card">
          <EmptyState
            icon={TrendingUp}
            title={t('analytics.emptyNoMetricsTitle')}
            description={t('analytics.emptyNoMetricsDesc')}
            action={
              <button
                type="button"
                onClick={() => collectMutation.mutate()}
                disabled={collectMutation.isPending}
                className="btn btn-primary"
              >
                {collectMutation.isPending ? (
                  <Loader2 size={16} className="animate-spin" />
                ) : (
                  <RefreshCw size={16} />
                )}
                {t('analytics.refreshMetrics')}
              </button>
            }
          />
        </div>
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div className="card p-6">
              <h2 className="text-heading mb-1 text-lg font-semibold">{t('analytics.engagementTrend')}</h2>
              <p className="text-muted mb-5 text-sm">{t('analytics.engagementTrendDesc')}</p>
              {trendData.length > 0 ? (
                <ResponsiveContainer width="100%" height={260}>
                  <AreaChart data={trendData}>
                    <CartesianGrid strokeDasharray="3 3" stroke={theme.grid} />
                    <XAxis dataKey="date" tick={{ fill: theme.axis, fontSize: 11 }} />
                    <YAxis tick={{ fill: theme.axis, fontSize: 11 }} />
                    <Tooltip content={<ChartTooltip theme={theme} />} />
                    <Legend />
                    <Area
                      type="monotone"
                      dataKey="impressions"
                      name={impressionsLabel}
                      stroke="#6366f1"
                      fill="#6366f1"
                      fillOpacity={0.15}
                      strokeWidth={2}
                    />
                    <Area
                      type="monotone"
                      dataKey="engagement"
                      name={engagementLabel}
                      stroke="#10b981"
                      fill="#10b981"
                      fillOpacity={0.15}
                      strokeWidth={2}
                    />
                  </AreaChart>
                </ResponsiveContainer>
              ) : (
                <p className="text-muted py-16 text-center text-sm">{t('analytics.noTrendData')}</p>
              )}
            </div>

            <div className="card p-6">
              <h2 className="text-heading mb-1 text-lg font-semibold">{t('analytics.topPosts')}</h2>
              <p className="text-muted mb-5 text-sm">{t('analytics.topPostsDesc')}</p>
              {postsData.length > 0 ? (
                <ResponsiveContainer width="100%" height={260}>
                  <BarChart data={postsData} layout="vertical" margin={{ left: 8, right: 16 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke={theme.grid} horizontal={false} />
                    <XAxis type="number" tick={{ fill: theme.axis, fontSize: 11 }} />
                    <YAxis
                      type="category"
                      dataKey="name"
                      width={100}
                      tick={{ fill: theme.axis, fontSize: 10 }}
                    />
                    <Tooltip content={<ChartTooltip theme={theme} />} />
                    <Bar dataKey="engagement" name={engagementLabel} fill="#8b5cf6" radius={[0, 4, 4, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <p className="text-muted py-16 text-center text-sm">{t('analytics.noPostMetrics')}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div className="card p-6">
              <h2 className="text-heading mb-1 text-lg font-semibold">{t('analytics.bestTimes')}</h2>
              <p className="text-muted mb-5 text-sm">{t('analytics.bestTimesDesc')}</p>
              {timesData.length > 0 ? (
                <ResponsiveContainer width="100%" height={240}>
                  <BarChart data={timesData}>
                    <CartesianGrid strokeDasharray="3 3" stroke={theme.grid} />
                    <XAxis dataKey="label" tick={{ fill: theme.axis, fontSize: 10 }} />
                    <YAxis tick={{ fill: theme.axis, fontSize: 11 }} />
                    <Tooltip content={<ChartTooltip theme={theme} />} />
                    <Bar dataKey="engagement" name={engagementLabel} fill="#f59e0b" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <p className="text-muted py-16 text-center text-sm">
                  {t('analytics.noPostingTimes')}
                </p>
              )}
            </div>

            <div className="card p-6">
              <h2 className="text-heading mb-1 text-lg font-semibold">{t('analytics.topHooks')}</h2>
              <p className="text-muted mb-5 text-sm">{t('analytics.topHooksDesc')}</p>
              {bestHooks.length > 0 ? (
                <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                  {bestHooks.map((post) => (
                    <li key={post.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                      <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                        <MessageSquare size={14} />
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="text-subheading line-clamp-2 text-sm font-medium">{post.hook}</p>
                        <p className="text-muted mt-0.5 text-xs">
                          {t('analytics.hookMeta', {
                            category: post.category,
                            tone: post.tone,
                            engagement: Number(post.engagement).toLocaleString(),
                          })}
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-muted py-16 text-center text-sm">{t('analytics.noHookData')}</p>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
