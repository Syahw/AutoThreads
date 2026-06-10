import bm from './bm';
import en from './en';
import { normalizeLanguage } from '../lib/language';
import { useLanguageStore } from '../stores/languageStore';

const dictionaries = { bm, en };

function getNested(obj, path) {
  return path.split('.').reduce((acc, key) => (acc && acc[key] !== undefined ? acc[key] : undefined), obj);
}

/**
 * @param {string} key - dot notation e.g. 'nav.dashboard'
 * @param {Record<string, string|number>} [vars]
 * @param {'bm'|'en'} [lang]
 */
export function t(key, vars = {}, lang) {
  const language = normalizeLanguage(lang ?? useLanguageStore.getState().language);
  let str = getNested(dictionaries[language], key)
    ?? getNested(dictionaries.en, key)
    ?? key;

  if (typeof str !== 'string') return key;

  Object.entries(vars).forEach(([k, v]) => {
    str = str.replaceAll(`{${k}}`, String(v));
  });

  return str;
}

export function useTranslation() {
  const language = useLanguageStore((s) => s.language);
  return {
    language,
    t: (key, vars = {}) => t(key, vars, language),
  };
}
