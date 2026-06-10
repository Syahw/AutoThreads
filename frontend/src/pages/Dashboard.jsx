import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { BarChart3, Sparkles, Calendar, TrendingUp, ArrowRight, Zap } from 'lucide-react';
import PageHeader from '../components/ui/PageHeader';
import StatCard from '../components/ui/StatCard';
import { AnnouncementList } from '../components/AnnouncementBanner';
import { useTranslation } from '../i18n';

export default function Dashboard() {
  const { t } = useTranslation();

  const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: () => api.get('/dashboard/stats').then((r) => r.data.data),
  });

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="card skeleton h-28" />
        ))}
      </div>
    );
  }

  const cards = [
    { label: t('dashboard.totalPosts'), value: stats?.total_posts ?? 0, icon: Sparkles, accent: 'brand' },
    { label: t('dashboard.scheduled'), value: stats?.scheduled_pending ?? 0, icon: Calendar, accent: 'amber' },
    { label: t('dashboard.postedThisWeek'), value: stats?.posted_this_week ?? 0, icon: TrendingUp, accent: 'emerald' },
    { label: t('dashboard.avgQuality'), value: `${stats?.avg_quality_score ?? 0}/100`, icon: BarChart3, accent: 'violet' },
  ];

  const quickLinks = [
    { to: '/content', label: t('dashboard.generateLabel'), desc: t('dashboard.generateDesc'), color: 'brand' },
    { to: '/scheduler', label: t('dashboard.schedulerLabel'), desc: t('dashboard.schedulerDesc'), color: 'amber' },
    { to: '/analytics', label: t('dashboard.analyticsLabel'), desc: t('dashboard.analyticsDesc'), color: 'emerald' },
  ];

  const linkStyles = {
    brand: 'hover:border-brand-200 hover:bg-brand-50/50 group-hover:text-brand-700 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/10 dark:group-hover:text-brand-300',
    amber: 'hover:border-amber-200 hover:bg-amber-50/50 group-hover:text-amber-700 dark:hover:border-amber-500/40 dark:hover:bg-amber-500/10 dark:group-hover:text-amber-300',
    emerald: 'hover:border-emerald-200 hover:bg-emerald-50/50 group-hover:text-emerald-700 dark:hover:border-emerald-500/40 dark:hover:bg-emerald-500/10 dark:group-hover:text-emerald-300',
  };

  const systemStatus = [
    { label: t('dashboard.totalImpressions'), value: stats?.total_impressions?.toLocaleString() ?? 0 },
    { label: t('dashboard.totalEngagement'), value: stats?.total_engagement?.toLocaleString() ?? 0 },
    { label: t('dashboard.failedPosts'), value: stats?.failed_posts ?? 0, danger: true },
  ];

  return (
    <div>
      <PageHeader
        title={t('dashboard.title')}
        description={t('dashboard.description')}
      />

      <AnnouncementList announcements={stats?.announcements} />

      <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        {cards.map((card) => (
          <StatCard key={card.label} {...card} />
        ))}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="card p-6">
          <div className="mb-5 flex items-center gap-2">
            <Zap size={18} className="text-brand-500 dark:text-brand-400" />
            <h2 className="text-heading text-lg font-semibold">{t('dashboard.quickActions')}</h2>
          </div>
          <div className="space-y-3">
            {quickLinks.map(({ to, label, desc, color }) => (
              <Link
                key={to}
                to={to}
                className={`group flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-4 transition-all dark:border-slate-700 dark:bg-slate-800/40 ${linkStyles[color]}`}
              >
                <div>
                  <p className="text-subheading font-medium">{label}</p>
                  <p className="text-muted mt-0.5 text-xs">{desc}</p>
                </div>
                <ArrowRight size={18} className="text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-current dark:text-slate-600" />
              </Link>
            ))}
          </div>
        </div>

        <div className="card p-6">
          <h2 className="text-heading mb-5 text-lg font-semibold">{t('dashboard.systemStatus')}</h2>
          <dl className="space-y-4">
            {systemStatus.map(({ label, value, danger }) => (
              <div key={label} className="flex items-center justify-between border-b border-slate-100 pb-3 last:border-0 last:pb-0 dark:border-slate-800">
                <dt className="text-muted text-sm">{label}</dt>
                <dd className={`text-sm font-semibold ${danger ? 'text-red-600 dark:text-red-400' : 'text-heading'}`}>{value}</dd>
              </div>
            ))}
          </dl>
        </div>
      </div>
    </div>
  );
}
