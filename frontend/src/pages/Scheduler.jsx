import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { Calendar, Clock } from 'lucide-react';

export default function Scheduler() {
  const { data: scheduled, isLoading } = useQuery({
    queryKey: ['scheduler'],
    queryFn: () => api.get('/scheduler?status=queued').then((r) => r.data.data),
  });

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Scheduler</h1>
      <div className="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
        {isLoading ? (
          <p className="text-gray-500">Loading scheduled posts...</p>
        ) : scheduled?.length === 0 ? (
          <div className="text-center py-8">
            <Calendar size={48} className="mx-auto text-gray-300 mb-3" />
            <p className="text-gray-500">No posts scheduled yet</p>
            <p className="text-sm text-gray-400">Approve content and schedule it for posting</p>
          </div>
        ) : (
          <div className="space-y-3">
            {scheduled?.map((post) => (
              <div key={post.id} className="flex items-center justify-between p-4 border border-gray-100 rounded-lg">
                <div>
                  <p className="text-sm text-gray-800 line-clamp-1">
                    {post.generated_post?.hook || 'Scheduled post'}
                  </p>
                  <div className="flex items-center gap-1 mt-1 text-xs text-gray-500">
                    <Clock size={12} />
                    {new Date(post.scheduled_at).toLocaleString()}
                  </div>
                </div>
                <span className="px-2 py-1 text-xs bg-amber-50 text-amber-700 rounded">
                  {post.status}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
