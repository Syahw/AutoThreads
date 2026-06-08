import clsx from 'clsx';
import { Calendar, Clock } from 'lucide-react';
import {
  buildDatetimeLocal,
  formatDatetimeLocalPreview,
  getSchedulePresets,
  isWithinPostingWindow,
  parseDatetimeLocal,
  todayDateString,
} from '../../utils/schedule';

export default function ScheduleDateTimePicker({
  value,
  onChange,
  disabled = false,
  settings = null,
  className,
}) {
  const { date, hour, minute } = parseDatetimeLocal(value);
  const earliest = settings?.earliest_hour ?? 0;
  const latest = settings?.latest_hour ?? 23;
  const presets = getSchedulePresets(settings);
  const preview = formatDatetimeLocalPreview(value, settings?.timezone);
  const inWindow = isWithinPostingWindow(value, settings);
  const timeValue = `${hour}:${minute}`;

  const update = (patch) => {
    const next = parseDatetimeLocal(value);
    onChange(buildDatetimeLocal({ ...next, ...patch }));
  };

  return (
    <div className={clsx('space-y-3', className)}>
      {presets.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {presets.map((preset) => (
            <button
              key={preset.id}
              type="button"
              disabled={disabled}
              onClick={() => onChange(preset.value)}
              className={clsx(
                'rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors',
                value === preset.value
                  ? 'border-brand-400 bg-brand-50 text-brand-700 dark:border-brand-500 dark:bg-brand-500/15 dark:text-brand-300'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-brand-300 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-slate-500'
              )}
            >
              {preset.label}
            </button>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]">
        <div>
          <label className="text-label mb-1.5 flex items-center gap-1.5 text-xs font-medium">
            <Calendar size={12} className="text-brand-500 dark:text-brand-400" />
            Date
          </label>
          <input
            type="date"
            value={date}
            min={todayDateString()}
            disabled={disabled}
            onChange={(e) => update({ date: e.target.value })}
            className="input-field [color-scheme:light] dark:[color-scheme:dark]"
          />
        </div>

        <div className="sm:min-w-[9rem]">
          <label className="text-label mb-1.5 flex items-center gap-1.5 text-xs font-medium">
            <Clock size={12} className="text-brand-500 dark:text-brand-400" />
            Time
          </label>
          <input
            type="time"
            value={timeValue}
            disabled={disabled}
            step={60}
            onChange={(e) => {
              const [h, m] = (e.target.value || '12:00').split(':');
              update({ hour: h, minute: m });
            }}
            className="input-field w-full [color-scheme:light] dark:[color-scheme:dark]"
            aria-label="Time"
          />
        </div>
      </div>

      {preview && (
        <div
          className={clsx(
            'flex items-start gap-2 rounded-xl border px-3.5 py-2.5 text-sm',
            inWindow
              ? 'border-brand-200/80 bg-brand-50/50 text-brand-900 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-100'
              : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200'
          )}
        >
          <Calendar size={16} className="mt-0.5 shrink-0 opacity-70" />
          <div>
            <p className="font-medium leading-snug">{preview}</p>
            {settings && (
              <p className="mt-0.5 text-xs opacity-80">
                {settings.timezone}
                {!inWindow && ` · Pick a time between ${earliest}:00 and ${latest}:59`}
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
