import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    icon?: LucideIcon;
    actions?: ReactNode;
    className?: string;
}

export function PageHeader({ title, description, icon: Icon, actions, className }: PageHeaderProps) {
    return (
        <div className={cn('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="flex items-start gap-3.5">
                {Icon && (
                    <span className="bg-primary-soft text-accent-foreground flex size-11 shrink-0 items-center justify-center rounded-xl">
                        <Icon className="size-5" />
                    </span>
                )}
                <div className="space-y-1">
                    <h1 className="text-2xl font-extrabold">{title}</h1>
                    {description && <p className="text-muted-foreground max-w-prose text-sm">{description}</p>}
                </div>
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
