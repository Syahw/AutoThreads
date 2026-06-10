import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { Link2, MousePointerClick, Plus, Loader2, Trash2 } from 'lucide-react';
import PageHeader from '../components/ui/PageHeader';
import EmptyState from '../components/ui/EmptyState';
import { useTranslation } from '../i18n';

const ctaStyles = ['soft', 'direct', 'curiosity', 'urgency', 'social_proof'];

export default function Affiliates() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [showForm, setShowForm] = useState(false);
  const [formError, setFormError] = useState('');
  const [form, setForm] = useState({
    product_name: '',
    url: '',
    short_url: '',
    cta_style: 'soft',
  });

  const { data: links, isLoading } = useQuery({
    queryKey: ['affiliates'],
    queryFn: () => api.get('/affiliates').then((r) => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: (data) => api.post('/affiliates', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['affiliates'] });
      setShowForm(false);
      setFormError('');
      setForm({ product_name: '', url: '', short_url: '', cta_style: 'soft' });
    },
    onError: (err) => {
      setFormError(err.response?.data?.message || t('affiliates.saveFailed'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => api.delete(`/affiliates/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['affiliates'] }),
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    setFormError('');
    const payload = {
      product_name: form.product_name.trim(),
      url: form.url.trim(),
      cta_style: form.cta_style,
    };
    if (form.short_url.trim()) {
      payload.short_url = form.short_url.trim();
    }
    createMutation.mutate(payload);
  };

  return (
    <div>
      <PageHeader
        title={t('affiliates.title')}
        description={t('affiliates.description')}
        action={
          <button type="button" onClick={() => setShowForm(!showForm)} className="btn-primary">
            <Plus size={16} /> {t('affiliates.addLink')}
          </button>
        }
      />

      {showForm && (
        <form onSubmit={handleSubmit} className="card mb-6 p-6">
          <h2 className="text-heading mb-1 text-lg font-semibold">{t('affiliates.newLink')}</h2>
          <p className="text-muted mb-4 text-sm">
            {t('affiliates.newLinkDesc')}
          </p>

          {formError && <div className="alert-error mb-4">{formError}</div>}

          <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label htmlFor="product_name" className="text-label mb-1.5 block text-sm font-medium">
                {t('affiliates.productName')}
              </label>
              <input
                id="product_name"
                type="text"
                value={form.product_name}
                onChange={(e) => setForm({ ...form, product_name: e.target.value })}
                className="input-field"
                placeholder={t('affiliates.productPlaceholder')}
                required
              />
            </div>
            <div>
              <label htmlFor="cta_style" className="text-label mb-1.5 block text-sm font-medium">
                {t('affiliates.ctaStyle')}
              </label>
              <select
                id="cta_style"
                value={form.cta_style}
                onChange={(e) => setForm({ ...form, cta_style: e.target.value })}
                className="select-field"
              >
                {ctaStyles.map((s) => (
                  <option key={s} value={s}>{t(`affiliates.ctaStyles.${s}`)}</option>
                ))}
              </select>
            </div>
            <div className="md:col-span-2">
              <label htmlFor="url" className="text-label mb-1.5 block text-sm font-medium">
                {t('affiliates.affiliateUrl')}
              </label>
              <input
                id="url"
                type="url"
                value={form.url}
                onChange={(e) => setForm({ ...form, url: e.target.value })}
                className="input-field"
                placeholder={t('affiliates.urlPlaceholder')}
                required
              />
            </div>
            <div className="md:col-span-2">
              <label htmlFor="short_url" className="text-label mb-1.5 block text-sm font-medium">
                {t('affiliates.shortUrl')}
              </label>
              <input
                id="short_url"
                type="url"
                value={form.short_url}
                onChange={(e) => setForm({ ...form, short_url: e.target.value })}
                className="input-field"
                placeholder={t('affiliates.shortUrlPlaceholder')}
              />
            </div>
          </div>

          <div className="flex flex-wrap gap-2">
            <button type="submit" disabled={createMutation.isPending} className="btn-success">
              {createMutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Plus size={16} />}
              {t('affiliates.saveLink')}
            </button>
            <button type="button" onClick={() => setShowForm(false)} className="btn-secondary">
              {t('common.cancel')}
            </button>
          </div>
        </form>
      )}

      <div className="card overflow-hidden">
        {isLoading ? (
          <div className="space-y-3 p-6">
            {[1, 2].map((i) => (
              <div key={i} className="skeleton h-20 rounded-xl" />
            ))}
          </div>
        ) : !links?.length ? (
          <EmptyState
            icon={Link2}
            title={t('affiliates.emptyTitle')}
            description={t('affiliates.emptyDesc')}
            action={
              <button type="button" onClick={() => setShowForm(true)} className="btn-primary">
                <Plus size={16} /> {t('affiliates.addFirstLink')}
              </button>
            }
          />
        ) : (
          <ul className="divide-y divide-slate-100 dark:divide-slate-800">
            {links.map((link) => (
              <li key={link.id} className="row-hover px-6 py-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <h3 className="text-heading font-semibold">{link.product_name}</h3>
                    <p className="mt-1 truncate text-sm text-brand-600 dark:text-brand-400">
                      {link.short_url || link.url}
                    </p>
                    {link.short_url && (
                      <p className="text-muted mt-0.5 truncate text-xs">{link.url}</p>
                    )}
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="badge-draft">
                      {link.cta_style ? t(`affiliates.ctaStyles.${link.cta_style}`) : ''}
                    </span>
                    <button
                      type="button"
                      onClick={() => deleteMutation.mutate(link.id)}
                      disabled={deleteMutation.isPending}
                      className="btn-ghost !p-2 text-red-600 dark:text-red-400"
                      title={t('affiliates.deleteLink')}
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
                <div className="text-muted mt-3 flex flex-wrap gap-4 text-xs">
                  <span className="flex items-center gap-1">
                    <MousePointerClick size={12} />
                    {t('affiliates.clicks', { count: link.click_count ?? 0 })}
                  </span>
                  <span>{t('affiliates.campaign', { tag: link.campaign_tag || t('affiliates.none') })}</span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
