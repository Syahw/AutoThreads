import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { Plus, Tag, Pencil, Trash2, Loader2, Save, X } from 'lucide-react';
import PageHeader from '../components/ui/PageHeader';
import EmptyState from '../components/ui/EmptyState';
import { useTranslation } from '../i18n';

const emptyForm = { name: '', description: '', target_audience: '' };

export default function Niches() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [editForm, setEditForm] = useState(emptyForm);
  const [formError, setFormError] = useState('');

  const { data: niches, isLoading } = useQuery({
    queryKey: ['niches'],
    queryFn: () => api.get('/niches').then((r) => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: (data) => api.post('/niches', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['niches'] });
      setShowForm(false);
      setForm(emptyForm);
      setFormError('');
    },
    onError: (err) => {
      setFormError(err.response?.data?.message || t('niches.createFailed'));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }) => api.put(`/niches/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['niches'] });
      setEditingId(null);
      setEditForm(emptyForm);
      setFormError('');
    },
    onError: (err) => {
      setFormError(err.response?.data?.message || t('niches.updateFailed'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => api.delete(`/niches/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['niches'] });
      if (editingId) setEditingId(null);
    },
    onError: (err) => {
      setFormError(err.response?.data?.message || t('niches.deleteFailed'));
    },
  });

  const startEdit = (niche) => {
    setFormError('');
    setEditingId(niche.id);
    setEditForm({
      name: niche.name ?? '',
      description: niche.description ?? '',
      target_audience: niche.target_audience ?? '',
    });
  };

  const cancelEdit = () => {
    setEditingId(null);
    setEditForm(emptyForm);
    setFormError('');
  };

  const handleCreate = (e) => {
    e.preventDefault();
    setFormError('');
    createMutation.mutate({
      name: form.name.trim(),
      description: form.description.trim() || undefined,
      target_audience: form.target_audience.trim() || undefined,
    });
  };

  const handleUpdate = (e) => {
    e.preventDefault();
    if (!editingId) return;
    setFormError('');
    updateMutation.mutate({
      id: editingId,
      name: editForm.name.trim(),
      description: editForm.description.trim() || undefined,
      target_audience: editForm.target_audience.trim() || undefined,
    });
  };

  const handleDelete = (niche) => {
    if (!window.confirm(t('niches.deleteConfirm', { name: niche.name }))) return;
    setFormError('');
    deleteMutation.mutate(niche.id);
  };

  return (
    <div>
      <PageHeader
        title={t('niches.title')}
        description={t('niches.description')}
        action={
          <button
            type="button"
            onClick={() => { setShowForm(!showForm); setFormError(''); }}
            className="btn-primary"
          >
            <Plus size={16} /> {t('niches.addNiche')}
          </button>
        }
      />

      {formError && !editingId && !showForm && (
        <div className="alert-error mb-4">{formError}</div>
      )}

      {showForm && (
        <form onSubmit={handleCreate} className="card mb-6 p-6">
          <h2 className="text-heading mb-4 text-lg font-semibold">{t('niches.newNiche')}</h2>
          {formError && <div className="alert-error mb-4">{formError}</div>}
          <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
              <label htmlFor="create-name" className="text-label mb-1.5 block text-sm font-medium">
                {t('niches.nameRequired')}
              </label>
              <input
                id="create-name"
                placeholder={t('niches.nicheName')}
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                className="input-field"
                required
              />
            </div>
            <div>
              <label htmlFor="create-description" className="text-label mb-1.5 block text-sm font-medium">
                {t('common.description')}
              </label>
              <input
                id="create-description"
                placeholder={t('common.description')}
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                className="input-field"
              />
            </div>
            <div>
              <label htmlFor="create-audience" className="text-label mb-1.5 block text-sm font-medium">
                {t('niches.targetAudience')}
              </label>
              <input
                id="create-audience"
                placeholder={t('niches.targetAudience')}
                value={form.target_audience}
                onChange={(e) => setForm({ ...form, target_audience: e.target.value })}
                className="input-field"
              />
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <button type="submit" disabled={createMutation.isPending} className="btn-success">
              {createMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Plus size={16} />}
              {t('niches.createNiche')}
            </button>
            <button type="button" onClick={() => { setShowForm(false); setFormError(''); }} className="btn-secondary">
              {t('common.cancel')}
            </button>
          </div>
        </form>
      )}

      {isLoading ? (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="card skeleton h-32" />
          ))}
        </div>
      ) : niches?.length === 0 ? (
        <div className="card">
          <EmptyState icon={Tag} title={t('niches.emptyTitle')} description={t('niches.emptyDesc')} />
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          {niches.map((niche) => (
            <div key={niche.id} className="card-hover p-5">
              {editingId === niche.id ? (
                <form onSubmit={handleUpdate}>
                  <h3 className="text-heading mb-3 text-sm font-semibold">{t('niches.editNiche')}</h3>
                  {formError && <div className="alert-error mb-3 text-xs">{formError}</div>}
                  <div className="space-y-3">
                    <input
                      placeholder={t('niches.nicheName')}
                      value={editForm.name}
                      onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                      className="input-field !py-2 text-sm"
                      required
                    />
                    <input
                      placeholder={t('common.description')}
                      value={editForm.description}
                      onChange={(e) => setEditForm({ ...editForm, description: e.target.value })}
                      className="input-field !py-2 text-sm"
                    />
                    <input
                      placeholder={t('niches.targetAudience')}
                      value={editForm.target_audience}
                      onChange={(e) => setEditForm({ ...editForm, target_audience: e.target.value })}
                      className="input-field !py-2 text-sm"
                    />
                  </div>
                  <div className="mt-4 flex flex-wrap gap-2">
                    <button type="submit" disabled={updateMutation.isPending} className="btn-primary !py-2 !text-xs">
                      {updateMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
                      {t('common.save')}
                    </button>
                    <button type="button" onClick={cancelEdit} className="btn-secondary !py-2 !text-xs">
                      <X size={14} /> {t('common.cancel')}
                    </button>
                  </div>
                </form>
              ) : (
                <>
                  <div className="mb-3 flex items-start justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-2">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                        <Tag size={16} />
                      </div>
                      <h3 className="text-heading font-semibold">{niche.name}</h3>
                    </div>
                    <div className="flex shrink-0 items-center gap-1">
                      <button
                        type="button"
                        onClick={() => startEdit(niche)}
                        className="btn-ghost !p-2"
                        title={t('niches.editNiche')}
                      >
                        <Pencil size={16} />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(niche)}
                        disabled={deleteMutation.isPending}
                        className="btn-ghost !p-2 text-red-600 dark:text-red-400"
                        title={t('niches.deleteNicheTitle')}
                      >
                        {deleteMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Trash2 size={16} />}
                      </button>
                    </div>
                  </div>
                  <p className="text-muted line-clamp-2 text-sm">{niche.description || t('common.noDescription')}</p>
                  {niche.target_audience && (
                    <p className="text-muted mt-2 text-xs">
                      {t('niches.audience')} {niche.target_audience}
                    </p>
                  )}
                  <p className="mt-3 text-xs font-medium text-slate-400 dark:text-slate-500">
                    {t('niches.postCount', { count: niche.post_count ?? 0 })}
                  </p>
                </>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
