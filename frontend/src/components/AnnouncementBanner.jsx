import { Megaphone } from 'lucide-react';
import clsx from 'clsx';

const TARGET_STYLES = {
  all: 'border-brand-200 bg-brand-50/80 dark:border-brand-800/60 dark:bg-brand-950/30',
  free: 'border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/40',
  paid: 'border-amber-200 bg-amber-50/80 dark:border-amber-800/60 dark:bg-amber-950/30',
};

const ICON_STYLES = {
  all: 'bg-brand-500/15 text-brand-600 dark:text-brand-400',
  free: 'bg-slate-200/80 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  paid: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
};

export default function AnnouncementBanner({ announcement }) {
  const target = announcement.target ?? 'all';

  return (
    <div
      className={clsx(
        'flex gap-4 rounded-2xl border p-4 shadow-sm sm:p-5',
        TARGET_STYLES[target] ?? TARGET_STYLES.all
      )}
    >
      <div
        className={clsx(
          'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
          ICON_STYLES[target] ?? ICON_STYLES.all
        )}
      >
        <Megaphone size={18} />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-heading font-semibold">{announcement.title}</p>
        <p className="text-subheading mt-1 text-sm leading-relaxed">{announcement.message}</p>
      </div>
    </div>
  );
}

export function AnnouncementList({ announcements = [] }) {
  if (!announcements.length) return null;

  return (
    <div className="mb-8 space-y-3">
      {announcements.map((a) => (
        <AnnouncementBanner key={a.id} announcement={a} />
      ))}
    </div>
  );
}
