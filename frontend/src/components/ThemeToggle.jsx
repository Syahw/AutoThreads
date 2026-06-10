import { Moon, Sun, Monitor } from 'lucide-react';
import clsx from 'clsx';
import { resolveTheme } from '../lib/theme';
import { useThemeStore } from '../stores/themeStore';
import { useTranslation } from '../i18n';

export default function ThemeToggle({ compact = false }) {
  const mode = useThemeStore((s) => s.mode);
  const setMode = useThemeStore((s) => s.setMode);
  const { t } = useTranslation();

  const options = [
    { value: 'light', icon: Sun, label: t('theme.light') },
    { value: 'dark', icon: Moon, label: t('theme.dark') },
    { value: 'system', icon: Monitor, label: t('theme.system') },
  ];

  const isDark = resolveTheme(mode) === 'dark';

  if (compact) {
    const nextMode = isDark ? 'light' : 'dark';
    const Icon = isDark ? Sun : Moon;

    return (
      <button
        type="button"
        onClick={() => setMode(nextMode)}
        className="rounded-xl border border-slate-200 p-2 text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
        title={isDark ? t('theme.switchToLight') : t('theme.switchToDark')}
        aria-label={isDark ? t('theme.switchToLight') : t('theme.switchToDark')}
      >
        <Icon size={18} />
      </button>
    );
  }

  return (
    <div
      className="inline-flex rounded-xl border border-slate-200 bg-slate-100/80 p-1 dark:border-slate-700 dark:bg-slate-800/80"
      role="group"
      aria-label={t('theme.label')}
    >
      {options.map(({ value, icon: Icon, label }) => (
        <button
          key={value}
          type="button"
          onClick={() => setMode(value)}
          className={clsx(
            'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors',
            mode === value
              ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
              : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
          )}
          title={label}
          aria-pressed={mode === value}
        >
          <Icon size={14} />
          <span className="hidden sm:inline">{label}</span>
        </button>
      ))}
    </div>
  );
}
