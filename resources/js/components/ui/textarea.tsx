import * as React from 'react';

import { cn } from '@/lib/utils';

const Textarea = React.forwardRef<HTMLTextAreaElement, React.ComponentProps<'textarea'>>(({ className, ...props }, ref) => {
    return (
        <textarea
            className={cn(
                'border-input bg-card text-foreground placeholder:text-muted-foreground/70 focus-visible:border-ring/60 focus-visible:ring-ring/25 flex min-h-24 w-full rounded-lg border px-3.5 py-2.5 text-base shadow-xs transition-[border-color,box-shadow] duration-200 outline-hidden focus-visible:ring-4 disabled:cursor-not-allowed disabled:opacity-55 md:text-sm',
                className,
            )}
            ref={ref}
            {...props}
        />
    );
});

Textarea.displayName = 'Textarea';

export { Textarea };
