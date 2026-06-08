import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../../services/api';
import PageHeader from '../../components/ui/PageHeader';

function Section({ title, children }) {
  return (
    <div className="card p-6">
      <h2 className="text-heading mb-4 text-lg font-semibold">{title}</h2>
      <div className="space-y-4">{children}</div>
    </div>
  );
}

function Field({ label, children }) {
  return (
    <label className="form-group block">
      <span className="form-label">{label}</span>
      {children}
    </label>
  );
}

export default function AdminSettings() {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['admin-settings'],
    queryFn: () => api.get('/admin/settings').then((r) => r.data.data),
  });

  const [form, setForm] = useState(null);

  useEffect(() => {
    if (data) setForm(JSON.parse(JSON.stringify(data)));
  }, [data]);

  const save = useMutation({
    mutationFn: (body) => api.put('/admin/settings', body),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-settings'] }),
  });

  const set = (section, key, value) => {
    setForm((prev) => ({
      ...prev,
      [section]: { ...prev[section], [key]: value },
    }));
  };

  const setPlan = (plan, key, value) => {
    setForm((prev) => ({
      ...prev,
      plans: {
        ...prev.plans,
        [plan]: { ...prev.plans[plan], [key]: value },
      },
    }));
  };

  const setFeature = (key, value) => {
    setForm((prev) => ({
      ...prev,
      features: { ...prev.features, [key]: value },
    }));
  };

  if (isLoading || !form) {
    return <div className="card skeleton h-64" />;
  }

  return (
    <div>
      <PageHeader
        title="Platform settings"
        description="General, scheduler, AI limits, plan quotas, and feature flags."
        action={
          <button
            type="button"
            className="btn-primary"
            disabled={save.isPending}
            onClick={() => save.mutate(form)}
          >
            {save.isPending ? 'Saving…' : 'Save settings'}
          </button>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Section title="General">
          <Field label="Site name">
            <input className="input-field" value={form.general?.site_name ?? ''} onChange={(e) => set('general', 'site_name', e.target.value)} />
          </Field>
          <Field label="Timezone">
            <input className="input-field" value={form.general?.timezone ?? ''} onChange={(e) => set('general', 'timezone', e.target.value)} />
          </Field>
        </Section>

        <Section title="Scheduler">
          <Field label="Earliest posting hour">
            <input type="number" min={0} max={23} className="input-field" value={form.scheduler?.earliest_hour ?? 0} onChange={(e) => set('scheduler', 'earliest_hour', parseInt(e.target.value, 10))} />
          </Field>
          <Field label="Latest posting hour">
            <input type="number" min={0} max={23} className="input-field" value={form.scheduler?.latest_hour ?? 23} onChange={(e) => set('scheduler', 'latest_hour', parseInt(e.target.value, 10))} />
          </Field>
          <Field label="Retry count">
            <input type="number" min={0} className="input-field" value={form.scheduler?.max_retries ?? 3} onChange={(e) => set('scheduler', 'max_retries', parseInt(e.target.value, 10))} />
          </Field>
          <Field label="Worker frequency (minutes)">
            <input type="number" min={1} className="input-field" value={form.scheduler?.worker_frequency_minutes ?? 1} onChange={(e) => set('scheduler', 'worker_frequency_minutes', parseInt(e.target.value, 10))} />
          </Field>
        </Section>

        <Section title="AI settings">
          <Field label="Default model">
            <input className="input-field" value={form.ai?.default_model ?? ''} onChange={(e) => set('ai', 'default_model', e.target.value)} />
          </Field>
          <Field label="Max tokens">
            <input type="number" className="input-field" value={form.ai?.max_tokens ?? 2000} onChange={(e) => set('ai', 'max_tokens', parseInt(e.target.value, 10))} />
          </Field>
          <Field label="Daily user limit">
            <input type="number" className="input-field" value={form.ai?.daily_user_limit ?? 50} onChange={(e) => set('ai', 'daily_user_limit', parseInt(e.target.value, 10))} />
          </Field>
        </Section>

        <Section title="Plan limits (posts/month)">
          {Object.entries(form.plans ?? {}).map(([plan, limits]) => (
            <Field key={plan} label={plan.charAt(0).toUpperCase() + plan.slice(1)}>
              <input
                type="number"
                className="input-field"
                value={limits.posts_per_month ?? 0}
                onChange={(e) => setPlan(plan, 'posts_per_month', parseInt(e.target.value, 10))}
              />
            </Field>
          ))}
        </Section>

        <Section title="Feature flags">
          {Object.entries(form.features ?? {}).map(([key, enabled]) => (
            <label key={key} className="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
              <span className="text-subheading capitalize text-sm">{key.replace(/_/g, ' ')}</span>
              <input
                type="checkbox"
                checked={!!enabled}
                onChange={(e) => setFeature(key, e.target.checked)}
                className="checkbox-field"
              />
            </label>
          ))}
        </Section>
      </div>

      {save.isSuccess && (
        <p className="mt-4 text-sm text-emerald-600 dark:text-emerald-400">Settings saved.</p>
      )}
    </div>
  );
}
