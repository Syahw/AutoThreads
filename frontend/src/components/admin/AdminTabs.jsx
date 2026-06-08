import clsx from 'clsx';

export default function AdminTabs({ tabs, active, onChange }) {
  return (
    <div
      className="mb-6 inline-flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-slate-100/80 p-1 dark:border-slate-700 dark:bg-slate-800/60"
      role="tablist"
    >
      {tabs.map(({ id, label }) => (
        <button
          key={id}
          type="button"
          role="tab"
          aria-selected={active === id}
          onClick={() => onChange(id)}
          className={clsx(
            'rounded-lg px-5 py-2.5 text-sm font-semibold transition-all',
            active === id
              ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
              : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'
          )}
        >
          {label}
        </button>
      ))}
    </div>
  );
}
