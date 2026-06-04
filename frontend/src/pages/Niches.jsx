import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { Plus, Tag } from 'lucide-react';
import PageHeader from '../components/ui/PageHeader';
import EmptyState from '../components/ui/EmptyState';

export default function Niches() {
  const queryClient = useQueryClient();
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ name: '', description: '', target_audience: '' });

  const { data: niches, isLoading } = useQuery({
    queryKey: ['niches'],
    queryFn: () => api.get('/niches').then((r) => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: (data) => api.post('/niches', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['niches'] });
      setShowForm(false);
      setForm({ name: '', description: '', target_audience: '' });
    },
  });

  return (
    <div>
      <PageHeader
        title="Niches"
        description="Organize content by topic and target audience for better AI prompts."
        action={
          <button type="button" onClick={() => setShowForm(!showForm)} className="btn-primary">
            <Plus size={16} /> Add niche
          </button>
        }
      />

      {showForm && (
        <div className="card mb-6 p-6">
          <h2 className="text-heading mb-4 text-lg font-semibold">New niche</h2>
          <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <input
              placeholder="Niche name"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className="input-field"
            />
            <input
              placeholder="Description"
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className="input-field"
            />
            <input
              placeholder="Target audience"
              value={form.target_audience}
              onChange={(e) => setForm({ ...form, target_audience: e.target.value })}
              className="input-field"
            />
          </div>
          <button type="button" onClick={() => createMutation.mutate(form)} className="btn-success">
            Create niche
          </button>
        </div>
      )}

      {isLoading ? (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="card skeleton h-32" />
          ))}
        </div>
      ) : niches?.length === 0 ? (
        <div className="card">
          <EmptyState icon={Tag} title="No niches yet" description="Create a niche to tailor generated content." />
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          {niches.map((niche) => (
            <div key={niche.id} className="card-hover p-5">
              <div className="mb-3 flex items-center gap-2">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                  <Tag size={16} />
                </div>
                <h3 className="text-heading font-semibold">{niche.name}</h3>
              </div>
              <p className="text-muted line-clamp-2 text-sm">{niche.description || 'No description'}</p>
              <p className="mt-3 text-xs font-medium text-slate-400 dark:text-slate-500">{niche.post_count ?? 0} posts</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
