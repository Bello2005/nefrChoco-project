import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}

export function EmptyState({ icon: Icon, title, description, action, className }: EmptyStateProps) {
    return (
        <div className={cn('bg-grid-soft flex flex-col items-center justify-center rounded-xl px-6 py-14 text-center', className)}>
            <span className="bg-primary-soft text-accent-foreground ring-card flex size-14 items-center justify-center rounded-2xl ring-8">
                <Icon className="size-6" />
            </span>
            <p className="font-display mt-4 text-base font-bold">{title}</p>
            {description && <p className="text-muted-foreground mt-1.5 max-w-sm text-sm">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
