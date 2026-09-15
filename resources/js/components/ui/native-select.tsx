import { ChevronDown } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

const NativeSelect = React.forwardRef<HTMLSelectElement, React.ComponentProps<'select'>>(({ className, children, ...props }, ref) => {
    return (
        <div className="relative">
            <select
                ref={ref}
                className={cn(
                    'border-input bg-card text-foreground focus-visible:border-ring/60 focus-visible:ring-ring/25 h-11 w-full appearance-none rounded-lg border px-3.5 pr-10 text-base shadow-xs transition-[border-color,box-shadow] duration-200 outline-hidden focus-visible:ring-4 disabled:cursor-not-allowed disabled:opacity-55 md:text-sm',
                    className,
                )}
                {...props}
            >
                {children}
            </select>
            <ChevronDown className="text-muted-foreground pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2" />
        </div>
    );
});

NativeSelect.displayName = 'NativeSelect';

export { NativeSelect };
