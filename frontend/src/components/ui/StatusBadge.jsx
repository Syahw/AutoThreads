import clsx from 'clsx';
import { useTranslation } from '../../i18n';

const styles = {
  draft: 'badge-draft',
  approved: 'badge-approved',
  posted: 'badge-posted',
  rejected: 'badge-rejected',
  scheduled: 'badge-scheduled',
  queued: 'badge-scheduled',
  processing: 'badge bg-brand-50 text-brand-700 ring-1 ring-brand-600/10 dark:bg-brand-950/50 dark:text-brand-300',
};

export default function StatusBadge({ status }) {
  const { t } = useTranslation();
  const label = t(`status.${status}`, {}) !== `status.${status}`
    ? t(`status.${status}`)
    : status;

  return (
    <span className={clsx(styles[status] || 'badge-draft', 'capitalize')}>
      {label}
    </span>
  );
}
