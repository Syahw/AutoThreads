import clsx from 'clsx';

const accents = {
  brand: 'from-brand-500 to-violet-500',
  amber: 'from-amber-500 to-orange-500',
  emerald: 'from-emerald-500 to-teal-500',
  violet: 'from-violet-500 to-purple-500',
};

export default function StatCard({ label, value, icon: Icon, accent = 'brand' }) {
  return (
    <div className="card-hover group p-5">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-muted text-sm font-medium">{label}</p>
          <p className="text-heading mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{value}</p>
        </div>
        <div
          className={clsx(
            'flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-md transition-transform group-hover:scale-105',
            accents[accent] || accents.brand
          )}
        >
          <Icon size={20} strokeWidth={2} />
        </div>
      </div>
    </div>
  );
}
