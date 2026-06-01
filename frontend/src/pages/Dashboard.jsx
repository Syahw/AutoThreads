import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { BarChart3, Sparkles, Calendar, TrendingUp } from 'lucide-react';

export default function Dashboard() {
  const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: () => api.get('/dashboard/stats').then((r) => r.data.data),
  });

  if (isLoading) {
    return <div className="animate-pulse">Loading dashboard...</div>;
  }

  const cards = [
    { label: 'Total Posts', value: stats?.total_posts ?? 0, icon: Sparkles, color: 'blue' },
    { label: 'Scheduled', value: stats?.scheduled_pending ?? 0, icon: Calendar, color: 'amber' },
    { label: 'Posted This Week', value: stats?.posted_this_week ?? 0, icon: TrendingUp, color: 'green' },
    { label: 'Avg Quality', value: `${stats?.avg_quality_score ?? 0}/100`, icon: BarChart3, color: 'purple' },
  ];

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Dashboard</h1>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {cards.map(({ label, value, icon: Icon, color }) => (
          <div key={label} className="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm text-gray-500">{label}</span>
              <Icon size={18} className={`text-${color}-500`} />
            </div>
            <p className="text-2xl font-bold text-gray-900">{value}</p>
          </div>
        ))}
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
          <div className="space-y-3">
            <a href="/content" className="block p-3 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
              Generate new content
            </a>
            <a href="/scheduler" className="block p-3 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors">
              View scheduled posts
            </a>
            <a href="/analytics" className="block p-3 rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition-colors">
              Check analytics
            </a>
          </div>
        </div>

        <div className="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">System Status</h2>
          <div className="space-y-3">
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-600">Total Impressions</span>
              <span className="font-medium">{stats?.total_impressions?.toLocaleString() ?? 0}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-600">Total Engagement</span>
              <span className="font-medium">{stats?.total_engagement?.toLocaleString() ?? 0}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-600">Failed Posts</span>
              <span className="font-medium text-red-600">{stats?.failed_posts ?? 0}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
