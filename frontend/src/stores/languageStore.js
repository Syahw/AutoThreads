import { create } from 'zustand';
import {
  DEFAULT_LANGUAGE,
  readStoredLanguage,
  writeStoredLanguage,
  normalizeLanguage,
} from '../lib/language';

export const useLanguageStore = create((set) => ({
  language: readStoredLanguage(),

  setLanguage: (language) => {
    const normalized = normalizeLanguage(language);
    writeStoredLanguage(normalized);
    set({ language: normalized });
  },
}));

export function initLanguage() {
  const language = readStoredLanguage();
  useLanguageStore.setState({ language });
  return language;
}
