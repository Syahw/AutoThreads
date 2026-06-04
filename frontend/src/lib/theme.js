const STORAGE_KEY = 'autothreads-theme';

export function getSystemTheme() {
  if (typeof window === 'undefined') return 'light';
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function resolveTheme(mode) {
  if (mode === 'system') return getSystemTheme();
  return mode === 'dark' ? 'dark' : 'light';
}

export function applyTheme(mode) {
  const resolved = resolveTheme(mode);
  const root = document.documentElement;

  if (resolved === 'dark') {
    root.classList.add('dark');
    root.style.colorScheme = 'dark';
  } else {
    root.classList.remove('dark');
    root.style.colorScheme = 'light';
  }

  return resolved;
}

/** Read mode from localStorage (same shape as former zustand persist). */
export function readStoredTheme() {
  if (typeof window === 'undefined') return 'system';

  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return 'system';
    const parsed = JSON.parse(raw);
    const mode = parsed?.state?.mode ?? parsed?.mode;
    if (mode === 'light' || mode === 'dark' || mode === 'system') return mode;
  } catch {
    /* ignore */
  }
  return 'system';
}

export function writeStoredTheme(mode) {
  if (typeof window === 'undefined') return;

  localStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({ state: { mode }, version: 0 })
  );
}

export function initTheme() {
  applyTheme(readStoredTheme());
}
