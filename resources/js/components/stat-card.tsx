import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';

type Tone = 'primary' | 'brand' | 'success' | 'warning' | 'info';

const toneStyles: Record<Tone, string> = {
    primary: 'bg-primary-soft text-accent-foreground',
    brand: 'bg-brand-soft text-brand',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    info: 'bg-info-soft text-info',
};

interface StatCardProps {
    label: string;
    value: string | number;
    hint?: string;
    icon: LucideIcon;
    tone?: Tone;
    className?: string;
}

export function StatCard({ label, value, hint, icon: Icon, tone = 'primary', className }: StatCardProps) {
    return (
        <div
            className={cn(
                'bg-card border-border/70 group relative overflow-hidden rounded-xl border p-5 shadow-sm transition-shadow duration-300 hover:shadow-md',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="text-muted-foreground text-sm font-medium">{label}</p>
                <span
                    className={cn(
                        'flex size-9 items-center justify-center rounded-lg transition-transform duration-300 group-hover:scale-105',
                        toneStyles[tone],
                    )}
                >
                    <Icon className="size-4.5" />
                </span>
            </div>
            <p className="tabular mt-3 text-3xl font-extrabold tracking-tight">{value}</p>
            {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
        </div>
    );
}
