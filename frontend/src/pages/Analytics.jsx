import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { BarChart3, TrendingUp, Eye, Heart } from 'lucide-react';
import PageHeader from '../components/ui/PageHeader';
import StatCard from '../components/ui/StatCard';

export default function Analytics() {
  const { data: overview, isLoading } = useQuery({
    queryKey: ['analytics'],
    queryFn: () => api.get('/analytics/overview').then((r) => r.data.data),
  });

  const stats = [
    { label: 'Total posts', value: overview?.total_posts ?? 0, icon: BarChart3, accent: 'brand' },
    { label: 'Approved', value: overview?.approved_posts ?? 0, icon: TrendingUp, accent: 'amber' },
    { label: 'Published', value: overview?.published_posts ?? 0, icon: Eye, accent: 'emerald' },
    { label: 'Avg quality', value: `${overview?.avg_quality_score ?? 0}/100`, icon: Heart, accent: 'violet' },
  ];

  return (
    <div>
      <PageHeader
        title="Analytics"
        description="Track performance after posts are published to Threads."
      />

      {isLoading ? (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="card skeleton h-28" />
          ))}
        </div>
      ) : (
        <>
          <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            {stats.map((s) => (
              <StatCard key={s.label} {...s} />
            ))}
          </div>
          <div className="card p-8 text-center">
            <BarChart3 size={40} className="mx-auto text-slate-300 dark:text-slate-600" />
            <p className="text-muted mt-4">
              Detailed charts will populate once posts are published and analytics are collected.
            </p>
          </div>
        </>
      )}
    </div>
  );
}
