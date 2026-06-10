import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Link2, Loader2, Plus } from 'lucide-react';
import clsx from 'clsx';
import api from '../../services/api';
import { useTranslation } from '../../i18n';

const CTA_STYLES = ['soft', 'direct', 'curiosity', 'urgency', 'social_proof'];

export default function AffiliateLinkPicker({ value, onChange, affiliates = [], suggestedProductName = '' }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [showForm, setShowForm] = useState(false);
  const [formError, setFormError] = useState('');
  const [form, setForm] = useState({ product_name: '', url: '', cta_style: 'soft' });

  const createMutation = useMutation({
    mutationFn: (data) => api.post('/affiliates', data),
    onSuccess: (res) => {
      const link = res.data?.data;
      queryClient.invalidateQueries({ queryKey: ['affiliates'] });
      if (link?.id) onChange(String(link.id));
      setShowForm(false);
      setFormError('');
      setForm({ product_name: '', url: '', cta_style: 'soft' });
    },
    onError: (err) => {
      setFormError(err.response?.data?.message || t('affiliates.saveFailed'));
    },
  });

  const handleCreate = (e) => {
    e.preventDefault();
    setFormError('');
    createMutation.mutate({
      product_name: form.product_name.trim(),
      url: form.url.trim(),
      cta_style: form.cta_style,
    });
  };

  return (
    <div className="space-y-3">
      <label className="text-label flex items-center gap-1.5 text-sm font-medium">
        <Link2 size={14} className="text-brand-500" />
        {t('content.affiliateLink')}
      </label>

      <div className="flex flex-wrap gap-2">
        <select
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="select-field min-w-0 flex-1"
        >
          <option value="">{t('content.noneSharing')}</option>
          {affiliates.map((a) => (
            <option key={a.id} value={a.id}>{a.product_name}</option>
          ))}
        </select>
        <button
          type="button"
          onClick={() => {
            setShowForm((v) => {
              const next = !v;
              if (next && suggestedProductName && !form.product_name) {
                setForm((prev) => ({ ...prev, product_name: suggestedProductName }));
              }
              return next;
            });
          }}
          className="btn-secondary shrink-0 !py-2 !text-xs"
        >
          <Plus size={14} /> {t('content.addAffiliateInline')}
        </button>
      </div>

      {value && (
        <p className="text-muted text-xs">{t('content.affiliateWithLink')}</p>
      )}

      {showForm && (
        <form onSubmit={handleCreate} className="panel-muted space-y-3 p-4">
          {formError && <div className="alert-error !py-2 text-xs">{formError}</div>}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label className="text-label mb-1 block text-xs font-medium">{t('affiliates.productName')}</label>
              <input
                type="text"
                value={form.product_name}
                onChange={(e) => setForm({ ...form, product_name: e.target.value })}
                className="input-field"
                placeholder={t('affiliates.productPlaceholder')}
                required
              />
            </div>
            <div>
              <label className="text-label mb-1 block text-xs font-medium">{t('affiliates.ctaStyle')}</label>
              <select
                value={form.cta_style}
                onChange={(e) => setForm({ ...form, cta_style: e.target.value })}
                className="select-field"
              >
                {CTA_STYLES.map((s) => (
                  <option key={s} value={s}>{t(`affiliates.ctaStyles.${s}`)}</option>
                ))}
              </select>
            </div>
            <div className="sm:col-span-2">
              <label className="text-label mb-1 block text-xs font-medium">{t('affiliates.affiliateUrl')}</label>
              <input
                type="url"
                value={form.url}
                onChange={(e) => setForm({ ...form, url: e.target.value })}
                className="input-field"
                placeholder={t('affiliates.urlPlaceholder')}
                required
              />
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <button
              type="submit"
              disabled={createMutation.isPending}
              className="btn-primary !py-1.5 !text-xs"
            >
              {createMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : null}
              {t('affiliates.saveLink')}
            </button>
            <button
              type="button"
              onClick={() => { setShowForm(false); setFormError(''); }}
              className={clsx('btn-secondary !py-1.5 !text-xs')}
            >
              {t('common.cancel')}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
