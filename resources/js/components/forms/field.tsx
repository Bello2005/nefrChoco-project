import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface FieldProps {
    /** Se omite cuando la etiqueta ya se renderiza aparte (p. ej. junto a un enlace). */
    label?: string;
    htmlFor: string;
    error?: string;
    hint?: string;
    className?: string;
    children: ReactNode;
}

export function Field({ label, htmlFor, error, hint, className, children }: FieldProps) {
    return (
        <div className={cn('grid gap-2', className)}>
            {label && (
                <Label htmlFor={htmlFor} className="text-sm font-semibold">
                    {label}
                </Label>
            )}
            {children}
            {hint && !error && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export function FormCard({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('bg-card border-border/70 space-y-5 rounded-xl border p-6 shadow-sm', className)}>{children}</div>;
}
