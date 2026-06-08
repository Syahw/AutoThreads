import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Calendar, Loader2, Plus, RotateCcw, Save, Trash2 } from 'lucide-react';
import api from '../../services/api';
import {
  DEFAULT_SCHEDULE_PRESETS,
  SCHEDULE_PRESET_TYPES,
  createEmptySchedulePreset,
  normalizeSchedulePresetsForSave,
} from '../../utils/schedule';

function presetTimeValue(preset) {
  const h = String(preset.hour ?? 9).padStart(2, '0');
  const m = String(preset.minute ?? 0).padStart(2, '0');
  return `${h}:${m}`;
}

export default function SchedulePresetsEditor() {
  const queryClient = useQueryClient();
  const [presets, setPresets] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [saved, setSaved] = useState(false);

  const { data: userSettings, isLoading } = useQuery({
    queryKey: ['settings'],
    queryFn: () => api.get('/settings').then((r) => r.data.data),
  });

  const { data: schedulerSettings } = useQuery({
    queryKey: ['scheduler-settings'],
    queryFn: () => api.get('/scheduler/settings').then((r) => r.data.data),
  });

  useEffect(() => {
    if (isLoading) return;
    const custom = userSettings?.schedule_presets;
    if (Array.isArray(custom) && custom.length > 0) {
      setPresets(custom);
    } else {
      setPresets([]);
    }
    setDirty(false);
  }, [userSettings, isLoading]);

  const saveMutation = useMutation({
    mutationFn: (nextPresets) => api.put('/settings', {
      schedule_presets: nextPresets.length > 0 ? normalizeSchedulePresetsForSave(nextPresets) : null,
    }),
    onSuccess: () => {
      setSaved(true);
      setDirty(false);
      queryClient.invalidateQueries({ queryKey: ['settings'] });
      queryClient.invalidateQueries({ queryKey: ['scheduler-settings'] });
      setTimeout(() => setSaved(false), 2500);
    },
  });

  const updatePreset = (index, patch) => {
    setPresets((prev) => prev.map((p, i) => (i === index ? { ...p, ...patch } : p)));
    setDirty(true);
    setSaved(false);
  };

  const removePreset = (index) => {
    setPresets((prev) => prev.filter((_, i) => i !== index));
    setDirty(true);
    setSaved(false);
  };

  const addPreset = () => {
    setPresets((prev) => [...prev, createEmptySchedulePreset()]);
    setDirty(true);
    setSaved(false);
  };

  const loadDefaults = () => {
    setPresets([...DEFAULT_SCHEDULE_PRESETS]);
    setDirty(true);
    setSaved(false);
  };

  const clearCustom = () => {
    setPresets([]);
    setDirty(true);
    setSaved(false);
  };

  const handleSave = () => {
    saveMutation.mutate(presets);
  };

  const usingDefaults = !userSettings?.schedule_presets?.length && presets.length === 0;

  return (
    <div className="card p-6 lg:col-span-2">
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="icon-box">
            <Calendar size={20} />
          </div>
          <div>
            <h2 className="text-heading text-lg font-semibold">Schedule quick picks</h2>
            <p className="text-muted text-sm">
              Shortcuts on the Content page when scheduling posts.
              {schedulerSettings?.timezone && (
                <span className="ml-1">Timezone: {schedulerSettings.timezone}</span>
              )}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <button type="button" onClick={loadDefaults} className="btn-secondary !py-2 !text-xs">
            <RotateCcw size={14} /> Load defaults
          </button>
          <button type="button" onClick={addPreset} className="btn-secondary !py-2 !text-xs">
            <Plus size={14} /> Add preset
          </button>
        </div>
      </div>

      {isLoading ? (
        <p className="text-muted flex items-center gap-2 text-sm">
          <Loader2 size={16} className="animate-spin" /> Loading...
        </p>
      ) : usingDefaults ? (
        <div className="panel-muted mb-5 p-4">
          <p className="text-subheading text-sm font-medium">Using built-in defaults</p>
          <ul className="text-muted mt-2 space-y-1 text-sm">
            {DEFAULT_SCHEDULE_PRESETS.map((p) => (
              <li key={p.id}>· {p.label}</li>
            ))}
          </ul>
          <p className="text-muted mt-3 text-xs">
            Click &quot;Load defaults&quot; to customize, or &quot;Add preset&quot; to start from scratch.
          </p>
        </div>
      ) : null}

      {presets.length > 0 && (
        <div className="mb-5 space-y-3">
          {presets.map((preset, index) => (
            <div key={preset.id || index} className="panel-muted grid gap-3 p-4 sm:grid-cols-[1fr_auto]">
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label className="text-label mb-1 block text-xs font-medium">Label</label>
                  <input
                    type="text"
                    value={preset.label || ''}
                    onChange={(e) => updatePreset(index, { label: e.target.value })}
                    placeholder="e.g. Tomorrow 9:00 AM"
                    className="input-field !py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="text-label mb-1 block text-xs font-medium">Type</label>
                  <select
                    value={preset.type || 'tomorrow_at'}
                    onChange={(e) => updatePreset(index, { type: e.target.value })}
                    className="select-field !py-2 text-sm"
                  >
                    {SCHEDULE_PRESET_TYPES.map((opt) => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                </div>

                {preset.type === 'minutes_from_now' ? (
                  <div>
                    <label className="text-label mb-1 block text-xs font-medium">Minutes from now</label>
                    <input
                      type="number"
                      min={1}
                      max={10080}
                      value={preset.minutes ?? 60}
                      onChange={(e) => updatePreset(index, { minutes: parseInt(e.target.value, 10) || 60 })}
                      className="input-field !py-2 text-sm"
                    />
                  </div>
                ) : preset.type !== 'next_midnight' ? (
                  <div>
                    <label className="text-label mb-1 block text-xs font-medium">Time</label>
                    <input
                      type="time"
                      value={presetTimeValue(preset)}
                      step={60}
                      onChange={(e) => {
                        const [h, m] = (e.target.value || '09:00').split(':');
                        updatePreset(index, { hour: parseInt(h, 10), minute: parseInt(m, 10) });
                      }}
                      className="input-field !py-2 text-sm [color-scheme:light] dark:[color-scheme:dark]"
                    />
                  </div>
                ) : (
                  <div className="flex items-end">
                    <p className="text-muted text-xs">Schedules the next calendar midnight (12:00 AM).</p>
                  </div>
                )}
              </div>

              <div className="flex items-start justify-end sm:pt-6">
                <button
                  type="button"
                  onClick={() => removePreset(index)}
                  className="btn-danger !py-2 !text-xs"
                  aria-label="Remove preset"
                >
                  <Trash2 size={14} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button
          type="button"
          onClick={handleSave}
          disabled={!dirty || saveMutation.isPending}
          className="btn-primary !py-2 !text-xs"
        >
          {saveMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
          Save quick picks
        </button>
        {presets.length > 0 && (
          <button type="button" onClick={clearCustom} className="btn-secondary !py-2 !text-xs">
            Clear custom (use defaults)
          </button>
        )}
        {saved && (
          <span className="text-sm font-medium text-emerald-600 dark:text-emerald-400">Saved</span>
        )}
        {saveMutation.isError && (
          <span className="text-sm text-red-600 dark:text-red-400">
            {saveMutation.error?.response?.data?.message || 'Save failed'}
          </span>
        )}
      </div>
    </div>
  );
}
