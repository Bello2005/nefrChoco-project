import * as React from 'react';

import { cn } from '@/lib/utils';

const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'>>(({ className, type, ...props }, ref) => {
    return (
        <input
            type={type}
            className={cn(
                'border-input bg-card text-foreground placeholder:text-muted-foreground/70 focus-visible:border-ring/60 focus-visible:ring-ring/25 flex h-11 w-full rounded-lg border px-3.5 py-2 text-base shadow-xs transition-[border-color,box-shadow] duration-200 outline-hidden file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-4 disabled:cursor-not-allowed disabled:opacity-55 md:text-sm',
                className,
            )}
            ref={ref}
            {...props}
        />
    );
});

Input.displayName = 'Input';

export { Input };
