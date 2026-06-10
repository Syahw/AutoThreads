export const LANGUAGES = {
  bm: { code: 'bm', label: 'Bahasa Melayu', short: 'BM' },
  en: { code: 'en', label: 'English', short: 'EN' },
};

export const DEFAULT_LANGUAGE = 'bm';
export const LANGUAGE_STORAGE_KEY = 'autothreads-language';

export function normalizeLanguage(value) {
  return value === 'en' ? 'en' : 'bm';
}

export function readStoredLanguage() {
  try {
    return normalizeLanguage(localStorage.getItem(LANGUAGE_STORAGE_KEY));
  } catch {
    return DEFAULT_LANGUAGE;
  }
}

export function writeStoredLanguage(language) {
  try {
    localStorage.setItem(LANGUAGE_STORAGE_KEY, normalizeLanguage(language));
  } catch {
    // ignore
  }
}
