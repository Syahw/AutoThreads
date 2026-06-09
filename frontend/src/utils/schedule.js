import { formatDateTime } from './date';

const pad = (n) => String(n).padStart(2, '0');

/** Built-in quick picks when the user has not configured custom presets in Settings. */
export const DEFAULT_SCHEDULE_PRESETS = [
  { id: '1h', label: 'In 1 hour', type: 'minutes_from_now', minutes: 60 },
  { id: 'midnight', label: 'Tonight 12:00 AM', type: 'next_midnight', hour: 0, minute: 0 },
  { id: 'tomorrow-am', label: 'Tomorrow 9:00 AM', type: 'tomorrow_at', hour: 9, minute: 0 },
  { id: 'tomorrow-pm', label: 'Tomorrow 6:00 PM', type: 'tomorrow_at', hour: 18, minute: 0 },
];

/**
 * Default datetime-local value: 1 hour ahead (exact minute, not rounded).
 */
export function defaultScheduleDatetimeLocal() {
  const d = new Date();
  d.setHours(d.getHours() + 1);
  d.setSeconds(0);
  d.setMilliseconds(0);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function todayDateString() {
  const d = new Date();
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function parseDatetimeLocal(value) {
  if (!value) {
    const def = defaultScheduleDatetimeLocal();
    return parseDatetimeLocal(def);
  }
  const [date, time] = value.split('T');
  const [hour = '12', minute = '00'] = (time || '').split(':');
  return {
    date: date || todayDateString(),
    hour: pad(parseInt(hour, 10) || 0),
    minute: pad(parseInt(minute, 10) || 0).slice(0, 2),
  };
}

export function buildDatetimeLocal({ date, hour, minute }) {
  const h = Math.min(23, Math.max(0, parseInt(hour, 10) || 0));
  const m = Math.min(59, Math.max(0, parseInt(minute, 10) || 0));
  return `${date}T${pad(h)}:${pad(m)}`;
}

/** Send to API as Y-m-d H:i:s in scheduler timezone (naive string). */
export function toSchedulerPayload(datetimeLocal) {
  if (!datetimeLocal) return null;
  const base = datetimeLocal.trim().replace('T', ' ');
  return base.length === 16 ? `${base}:00` : base;
}

/**
 * Display wall-clock time exactly as stored (scheduler timezone), not shifted to UTC.
 */
export function formatScheduledAt(value) {
  if (!value) return '';

  if (typeof value === 'object') {
    if (value.scheduled_at) {
      return formatScheduledAt(value.scheduled_at);
    }
    return '';
  }

  return formatDateTime(value, '');
}

export function formatDatetimeLocalPreview(datetimeLocal, timeZone) {
  if (!datetimeLocal) return '';
  const payload = toSchedulerPayload(datetimeLocal);
  return formatScheduledAt(payload, timeZone);
}

function clampHour(hour, earliest, latest) {
  return Math.min(latest, Math.max(earliest, hour));
}

function nextMidnightDatetimeLocal() {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  d.setHours(0, 0, 0, 0);
  return buildDatetimeLocal({
    date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
    hour: 0,
    minute: 0,
  });
}

function tomorrowDateString() {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Resolve a single preset config into a datetime-local string.
 */
export function resolveSchedulePreset(preset, settings = null) {
  const earliest = settings?.earliest_hour ?? 0;
  const latest = settings?.latest_hour ?? 23;
  const type = preset?.type || 'minutes_from_now';

  if (type === 'minutes_from_now') {
    const minutes = Math.max(1, parseInt(preset.minutes, 10) || 60);
    const d = new Date();
    d.setMinutes(d.getMinutes() + minutes);
    d.setSeconds(0);
    d.setMilliseconds(0);
    return buildDatetimeLocal({
      date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
      hour: d.getHours(),
      minute: d.getMinutes(),
    });
  }

  if (type === 'next_midnight') {
    const hour = clampHour(parseInt(preset.hour, 10) || 0, earliest, latest);
    if (hour === 0) {
      return nextMidnightDatetimeLocal();
    }
    const d = new Date();
    d.setHours(hour, parseInt(preset.minute, 10) || 0, 0, 0);
    if (d <= new Date()) {
      d.setDate(d.getDate() + 1);
    }
    return buildDatetimeLocal({
      date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
      hour: d.getHours(),
      minute: d.getMinutes(),
    });
  }

  if (type === 'today_at') {
    const hour = clampHour(parseInt(preset.hour, 10) || 9, earliest, latest);
    const minute = parseInt(preset.minute, 10) || 0;
    const d = new Date();
    d.setHours(hour, minute, 0, 0);
    if (d <= new Date()) {
      d.setDate(d.getDate() + 1);
    }
    return buildDatetimeLocal({
      date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
      hour: d.getHours(),
      minute: d.getMinutes(),
    });
  }

  if (type === 'tomorrow_at') {
    const hour = clampHour(parseInt(preset.hour, 10) || 9, earliest, latest);
    const minute = parseInt(preset.minute, 10) || 0;
    return buildDatetimeLocal({
      date: tomorrowDateString(),
      hour,
      minute,
    });
  }

  return defaultScheduleDatetimeLocal();
}

export function getEffectiveSchedulePresets(settings) {
  const custom = settings?.schedule_presets;
  if (Array.isArray(custom) && custom.length > 0) {
    return custom;
  }
  return DEFAULT_SCHEDULE_PRESETS;
}

export function getSchedulePresets(settings) {
  const configs = getEffectiveSchedulePresets(settings);

  return configs
    .filter((preset) => preset?.label && preset?.type)
    .map((preset, index) => ({
      id: preset.id || `preset-${index}`,
      label: preset.label,
      value: resolveSchedulePreset(preset, settings),
      config: preset,
    }));
}

export function isWithinPostingWindow(datetimeLocal, settings) {
  if (!datetimeLocal || !settings) return true;
  const { hour } = parseDatetimeLocal(datetimeLocal);
  const h = parseInt(hour, 10);
  return h >= settings.earliest_hour && h <= settings.latest_hour;
}

export const SCHEDULE_PRESET_TYPES = [
  { value: 'minutes_from_now', label: 'Minutes from now' },
  { value: 'today_at', label: 'Today at time (rolls to tomorrow if past)' },
  { value: 'tomorrow_at', label: 'Tomorrow at time' },
  { value: 'next_midnight', label: 'Next midnight (12:00 AM)' },
];

export function createEmptySchedulePreset() {
  return {
    id: `custom-${Date.now()}`,
    label: 'Custom time',
    type: 'tomorrow_at',
    hour: 9,
    minute: 0,
  };
}

export function normalizeSchedulePresetsForSave(presets) {
  if (!Array.isArray(presets)) return [];
  return presets
    .filter((p) => p?.label?.trim() && p?.type)
    .map((p, index) => ({
      id: p.id || `preset-${index}`,
      label: p.label.trim(),
      type: p.type,
      ...(p.type === 'minutes_from_now'
        ? { minutes: Math.max(1, parseInt(p.minutes, 10) || 60) }
        : {
            hour: Math.min(23, Math.max(0, parseInt(p.hour, 10) || 0)),
            minute: Math.min(59, Math.max(0, parseInt(p.minute, 10) || 0)),
          }),
    }));
}
