const pad = (n) => String(n).padStart(2, '0');

/**
 * Default datetime-local value: 1 hour ahead, rounded to next 5 minutes.
 */
export function defaultScheduleDatetimeLocal() {
  const d = new Date();
  d.setHours(d.getHours() + 1);
  const minutes = Math.ceil(d.getMinutes() / 5) * 5;
  d.setMinutes(minutes);
  d.setSeconds(0);
  d.setMilliseconds(0);
  if (d.getMinutes() >= 60) {
    d.setHours(d.getHours() + 1);
    d.setMinutes(0);
  }
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
    hour: String(parseInt(hour, 10) || 0),
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
 * Parse API/DB datetime without treating it as UTC (fixes +8h display bug).
 */
export function parseNaiveDatetime(value) {
  if (!value) return null;
  const normalized = String(value).trim().replace('T', ' ');
  const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/);
  if (!match) return null;
  return {
    year: Number(match[1]),
    month: Number(match[2]),
    day: Number(match[3]),
    hour: Number(match[4]),
    minute: Number(match[5]),
  };
}

function formatHour12(hour, minute) {
  const h = hour % 12 || 12;
  const ampm = hour < 12 ? 'AM' : 'PM';
  return `${h}:${pad(minute)} ${ampm}`;
}

/**
 * Display wall-clock time exactly as stored (scheduler timezone), not shifted to UTC.
 */
export function formatScheduledAt(value, timeZone) {
  if (!value) return '';

  if (typeof value === 'object') {
    if (value.scheduled_at_display) {
      const tz = value.scheduled_at_timezone || timeZone;
      const suffix = tz ? ` (${tz})` : '';
      return `${value.scheduled_at_display}${suffix}`;
    }
    if (value.scheduled_at) {
      return formatScheduledAt(value.scheduled_at, value.scheduled_at_timezone || timeZone);
    }
    return '';
  }

  const parts = parseNaiveDatetime(value);
  if (!parts) {
    return String(value);
  }

  const date = new Date(parts.year, parts.month - 1, parts.day);
  const dayName = date.toLocaleDateString(undefined, { weekday: 'short' });
  const month = date.toLocaleDateString(undefined, { month: 'short' });
  const time = formatHour12(parts.hour, parts.minute);
  const suffix = timeZone ? ` (${timeZone})` : '';

  return `${dayName}, ${parts.day} ${month} ${parts.year}, ${time}${suffix}`;
}

export function formatDatetimeLocalPreview(datetimeLocal, timeZone) {
  if (!datetimeLocal) return '';
  const payload = toSchedulerPayload(datetimeLocal);
  return formatScheduledAt(payload, timeZone);
}

export function buildHourOptions(earliestHour = 0, latestHour = 23) {
  const options = [];
  for (let h = earliestHour; h <= latestHour; h += 1) {
    options.push({ value: String(h), label: formatHour12(h, 0) });
  }
  return options;
}

export function buildMinuteOptions(step = 5) {
  const options = [];
  for (let m = 0; m < 60; m += step) {
    options.push({ value: pad(m), label: pad(m) });
  }
  return options;
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

export function getSchedulePresets(settings) {
  const earliest = settings?.earliest_hour ?? 0;
  const latest = settings?.latest_hour ?? 23;

  const inOneHour = defaultScheduleDatetimeLocal();

  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const tomorrowDate = `${tomorrow.getFullYear()}-${pad(tomorrow.getMonth() + 1)}-${pad(tomorrow.getDate())}`;

  const morningHour = clampHour(9, earliest, latest);
  const eveningHour = clampHour(18, earliest, latest);
  const midnightHour = clampHour(0, earliest, latest);

  const presets = [
    { id: '1h', label: 'In 1 hour', value: inOneHour },
  ];

  if (midnightHour === 0) {
    presets.push({
      id: 'midnight',
      label: 'Tonight 12:00 AM',
      value: nextMidnightDatetimeLocal(),
    });
  }

  presets.push(
    {
      id: 'tomorrow-am',
      label: `Tomorrow ${formatHour12(morningHour, 0)}`,
      value: buildDatetimeLocal({ date: tomorrowDate, hour: morningHour, minute: 0 }),
    },
    {
      id: 'tomorrow-pm',
      label: `Tomorrow ${formatHour12(eveningHour, 0)}`,
      value: buildDatetimeLocal({ date: tomorrowDate, hour: eveningHour, minute: 0 }),
    },
  );

  return presets;
}

export function isWithinPostingWindow(datetimeLocal, settings) {
  if (!datetimeLocal || !settings) return true;
  const { hour } = parseDatetimeLocal(datetimeLocal);
  const h = parseInt(hour, 10);
  return h >= settings.earliest_hour && h <= settings.latest_hour;
}
