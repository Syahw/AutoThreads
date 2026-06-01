import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { BarChart3 } from 'lucide-react';

export default function Analytics() {
  const { data: overview } = useQuery({
    queryKey: ['analytics'],
    queryFn: () => api.get('/analytics/overview').then((r) => r.data.data),
  });

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Analytics</h1>
      <div className="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
        <div className="text-center py-8">
          <BarChart3 size={48} className="mx-auto text-gray-300 mb-3" />
          <p className="text-gray-500">Analytics data will appear here once posts are published</p>
          <p className="text-sm text-gray-400 mt-1">Track impressions, engagement, and best performing content</p>
        </div>
      </div>
    </div>
  );
}
