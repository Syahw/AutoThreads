const pad = (n) => String(n).padStart(2, '0');

/**
 * Parse API/DB datetime without treating naive strings as UTC.
 * Supports: YYYY-MM-DD, YYYY-MM-DD HH:MM:SS, optional trailing timezone name.
 */
export function parseNaiveDatetime(value) {
  if (!value) return null;
  const normalized = String(value).trim().replace('T', ' ');
  const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2})(?::(\d{2}))?)?/);
  if (!match) return null;

  return {
    year: Number(match[1]),
    month: Number(match[2]),
    day: Number(match[3]),
    hour: match[4] != null ? Number(match[4]) : null,
    minute: match[5] != null ? Number(match[5]) : null,
    second: match[6] != null ? Number(match[6]) : 0,
  };
}

function parseDateValue(value) {
  if (value == null || value === '') return null;

  if (typeof value === 'object') {
    if (value.scheduled_at) return parseDateValue(value.scheduled_at);
    return null;
  }

  const str = String(value).trim();

  const naive = parseNaiveDatetime(str);
  if (naive) return naive;

  const parsed = new Date(str);
  if (!Number.isNaN(parsed.getTime())) {
    return {
      year: parsed.getFullYear(),
      month: parsed.getMonth() + 1,
      day: parsed.getDate(),
      hour: parsed.getHours(),
      minute: parsed.getMinutes(),
      second: parsed.getSeconds(),
    };
  }

  return null;
}

function formatParts(parts) {
  return `${pad(parts.day)}/${pad(parts.month)}/${parts.year}`;
}

function formatTime12(hour, minute, second = 0) {
  const h = hour % 12 || 12;
  const ampm = hour < 12 ? 'AM' : 'PM';
  return `${h}:${pad(minute)}:${pad(second)} ${ampm}`;
}

/**
 * Date only: 04/06/2026
 */
export function formatDate(value, fallback = '—') {
  const parts = parseDateValue(value);
  if (!parts) return value ? String(value) : fallback;
  return formatParts(parts);
}

/**
 * Date and time: 04/06/2026, 2:54:48 PM
 */
export function formatDateTime(value, fallback = '—') {
  const parts = parseDateValue(value);
  if (!parts) return value ? String(value) : fallback;
  if (parts.hour == null) return formatParts(parts);
  return `${formatParts(parts)}, ${formatTime12(parts.hour, parts.minute, parts.second ?? 0)}`;
}

/**
 * Pick date-only or date-time based on whether a time component is present.
 */
export function formatDateAuto(value, fallback = '—') {
  const parts = parseDateValue(value);
  if (!parts) return value ? String(value) : fallback;
  if (parts.hour != null) return formatDateTime(parts);
  return formatParts(parts);
}
