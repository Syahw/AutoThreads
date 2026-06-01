import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { Sparkles, RefreshCw, Check, X } from 'lucide-react';

const categories = [
  'story', 'product_recommendation', 'comparison', 'productivity_tip',
  'viral_hook', 'opinion', 'list_post', 'wish_i_knew', 'general',
];

const tones = [
  'casual', 'professional', 'witty', 'inspirational',
  'controversial', 'educational', 'storytelling', 'urgent',
];

export default function ContentGenerator() {
  const queryClient = useQueryClient();
  const [config, setConfig] = useState({
    niche_id: '', category: 'general', tone: '', variations: 1,
  });

  const { data: niches } = useQuery({
    queryKey: ['niches'],
    queryFn: () => api.get('/niches').then((r) => r.data.data),
  });

  const { data: posts } = useQuery({
    queryKey: ['content'],
    queryFn: () => api.get('/content?limit=10').then((r) => r.data.data),
  });

  const generateMutation = useMutation({
    mutationFn: (data) => api.post('/content/generate', data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['content'] }),
  });

  const approveMutation = useMutation({
    mutationFn: (id) => api.put(`/content/${id}/approve`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['content'] }),
  });

  const handleGenerate = () => {
    generateMutation.mutate(config);
  };

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Content Generator</h1>

      {/* Generation Form */}
      <div className="bg-white rounded-xl p-6 border border-gray-100 shadow-sm mb-6">
        <h2 className="text-lg font-semibold mb-4">Generate New Content</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Niche</label>
            <select
              value={config.niche_id}
              onChange={(e) => setConfig({ ...config, niche_id: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg"
            >
              <option value="">Select niche</option>
              {niches?.map((n) => (
                <option key={n.id} value={n.id}>{n.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select
              value={config.category}
              onChange={(e) => setConfig({ ...config, category: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg"
            >
              {categories.map((c) => (
                <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Tone</label>
            <select
              value={config.tone}
              onChange={(e) => setConfig({ ...config, tone: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg"
            >
              <option value="">Auto-rotate</option>
              {tones.map((t) => (
                <option key={t} value={t}>{t}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Variations</label>
            <input
              type="number" min="1" max="5"
              value={config.variations}
              onChange={(e) => setConfig({ ...config, variations: parseInt(e.target.value) })}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg"
            />
          </div>
        </div>
        <button
          onClick={handleGenerate}
          disabled={generateMutation.isPending}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          <Sparkles size={16} />
          {generateMutation.isPending ? 'Generating...' : 'Generate Content'}
        </button>
      </div>

      {/* Generated Posts */}
      <div className="space-y-4">
        {posts?.map((post) => (
          <div key={post.id} className="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div className="flex items-start justify-between mb-3">
              <div className="flex gap-2">
                <span className="px-2 py-1 text-xs bg-gray-100 rounded">{post.category}</span>
                <span className="px-2 py-1 text-xs bg-blue-50 text-blue-700 rounded">{post.tone}</span>
                <span className="px-2 py-1 text-xs bg-purple-50 text-purple-700 rounded">
                  Score: {post.quality_score}
                </span>
              </div>
              <span className={`px-2 py-1 text-xs rounded ${
                post.status === 'approved' ? 'bg-green-50 text-green-700' :
                post.status === 'draft' ? 'bg-gray-100 text-gray-600' :
                'bg-red-50 text-red-700'
              }`}>{post.status}</span>
            </div>
            <p className="text-gray-800 whitespace-pre-line mb-3">{post.content}</p>
            {post.status === 'draft' && (
              <div className="flex gap-2">
                <button
                  onClick={() => approveMutation.mutate(post.id)}
                  className="flex items-center gap-1 px-3 py-1 text-sm bg-green-50 text-green-700 rounded hover:bg-green-100"
                >
                  <Check size={14} /> Approve
                </button>
                <button className="flex items-center gap-1 px-3 py-1 text-sm bg-red-50 text-red-700 rounded hover:bg-red-100">
                  <X size={14} /> Reject
                </button>
                <button className="flex items-center gap-1 px-3 py-1 text-sm bg-blue-50 text-blue-700 rounded hover:bg-blue-100">
                  <RefreshCw size={14} /> Regenerate
                </button>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
