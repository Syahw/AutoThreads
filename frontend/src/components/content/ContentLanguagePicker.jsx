import clsx from 'clsx';
import { Check } from 'lucide-react';
import { LANGUAGES } from '../../lib/language';
import { useTranslation } from '../../i18n';

export default function ContentLanguagePicker({ value, onChange }) {
  const { t } = useTranslation();
  const options = Object.values(LANGUAGES);

  return (
    <div>
      <label className="text-label mb-1.5 block text-sm font-medium">{t('content.contentLanguage')}</label>
      <p className="text-muted mb-2 text-xs">{t('content.contentLanguageDesc')}</p>
      <div className="flex flex-wrap gap-2">
        {options.map((lang) => {
          const selected = value === lang.code;
          return (
            <button
              key={lang.code}
              type="button"
              onClick={() => onChange(lang.code)}
              className={clsx(
                'rounded-full px-3 py-1.5 text-xs font-medium transition-all',
                selected
                  ? 'bg-brand-600 text-white shadow-sm ring-1 ring-brand-600'
                  : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-brand-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:ring-brand-500',
              )}
            >
              {selected && <Check size={12} className="mr-1 inline" />}
              {lang.label}
            </button>
          );
        })}
      </div>
    </div>
  );
}
