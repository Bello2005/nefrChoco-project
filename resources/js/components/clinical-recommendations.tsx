import { Badge } from '@/components/ui/badge';
import { type ClinicalRecommendation } from '@/types';

type BadgeVariant = 'destructive' | 'warning' | 'info' | 'secondary';

const priorityVariant: Record<string, BadgeVariant> = {
    alta: 'destructive',
    media: 'warning',
    baja: 'info',
};

/**
 * Aviso obligatorio en toda superficie del apoyo a decisiones.
 *
 * El sistema ordena a quién mirar primero; no dice qué tiene el paciente ni
 * qué hacer con él. Dejarlo explícito evita que una sugerencia se lea como
 * una indicación clínica.
 */
export function DecisionSupportNotice({ className = '' }: { className?: string }) {
    return (
        <p className={`text-muted-foreground text-sm ${className}`}>
            Sugerencias calculadas a partir de datos ya registrados. Son <span className="font-medium">apoyo a la decisión, no un diagnóstico</span>:
            la conducta final la define el profesional.
        </p>
    );
}

export function RecommendationList({ recommendations }: { recommendations: ClinicalRecommendation[] }) {
    return (
        <ul className="space-y-3">
            {recommendations.map((recommendation) => (
                <li key={recommendation.rule} className="border-border/70 rounded-lg border p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={priorityVariant[recommendation.priority] ?? 'secondary'}>{recommendation.priorityLabel}</Badge>
                        <p className="text-sm font-semibold">{recommendation.title}</p>
                    </div>

                    {/* El motivo es lo que hace revisable la sugerencia: nombra el dato. */}
                    <p className="text-muted-foreground mt-2.5 text-sm">
                        <span className="text-foreground font-semibold">Por qué: </span>
                        {recommendation.reason}
                    </p>
                    <p className="text-muted-foreground mt-1.5 text-sm">
                        <span className="text-foreground font-semibold">Sugerencia: </span>
                        {recommendation.action}
                    </p>
                </li>
            ))}
        </ul>
    );
}
