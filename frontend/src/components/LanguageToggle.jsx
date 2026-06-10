import clsx from 'clsx';
import { LANGUAGES } from '../lib/language';
import { useLanguageStore } from '../stores/languageStore';
import { useTranslation } from '../i18n';

export default function LanguageToggle({ className, size = 'md' }) {
  const language = useLanguageStore((s) => s.language);
  const setLanguage = useLanguageStore((s) => s.setLanguage);
  const { t } = useTranslation();

  const sizeClass = size === 'sm' ? 'text-xs' : 'text-sm';

  return (
    <div
      className={clsx(
        'inline-flex rounded-lg border border-slate-200 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-900',
        className,
      )}
      role="group"
      aria-label={t('language.label')}
    >
      {Object.values(LANGUAGES).map((lang) => (
        <button
          key={lang.code}
          type="button"
          onClick={() => setLanguage(lang.code)}
          className={clsx(
            'rounded-md px-3 py-1.5 font-medium transition-all',
            sizeClass,
            language === lang.code
              ? 'bg-brand-600 text-white shadow-sm'
              : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200',
          )}
        >
          {lang.short}
        </button>
      ))}
    </div>
  );
}
