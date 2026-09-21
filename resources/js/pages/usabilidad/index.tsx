import { Field, FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Usabilidad', href: '/usabilidad' }];

/** Los extremos se rotulan; los intermedios se entienden por posición. */
const scaleLabels: Record<number, string> = {
    1: 'Muy en desacuerdo',
    5: 'Muy de acuerdo',
};

interface Props {
    statements: Record<string, string>;
    alreadyAnswered: boolean;
}

export default function UsabilidadIndex({ statements, alreadyAnswered }: Props) {
    const items = Object.entries(statements).map(([item, statement]) => ({ item: Number(item), statement }));

    const { data, setData, post, processing, errors } = useForm<{ answers: Record<number, number | null>; comments: string }>({
        answers: Object.fromEntries(items.map(({ item }) => [item, null])),
        comments: '',
    });

    const answeredCount = items.filter(({ item }) => data.answers[item] !== null).length;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('usabilidad.store'));
    };

    if (alreadyAnswered) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Cuestionario de usabilidad" />

                <div className="mx-auto max-w-2xl space-y-6">
                    <PageHeader title="Cuestionario de usabilidad" icon={ClipboardCheck} />
                    <FormCard>
                        <p className="text-sm font-semibold">Ya respondiste el cuestionario.</p>
                        <p className="text-muted-foreground text-sm">
                            Gracias por tomarte el tiempo. Se registra una sola respuesta por persona para que el promedio refleje a cuánta gente se
                            le preguntó, no cuántas veces respondió cada quien.
                        </p>
                    </FormCard>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuestionario de usabilidad" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="Cuestionario de usabilidad"
                    description="Diez afirmaciones sobre tu experiencia usando la plataforma. No hay respuestas correctas: responde lo primero que pienses."
                    icon={ClipboardCheck}
                />

                <form onSubmit={submit}>
                    <FormCard className="space-y-7">
                        {items.map(({ item, statement }) => (
                            <div key={item} className="space-y-3">
                                <p className="text-sm font-semibold">
                                    <span className="text-muted-foreground mr-2 font-bold">{item}.</span>
                                    {statement}
                                </p>

                                <ToggleGroup
                                    type="single"
                                    value={data.answers[item]?.toString() ?? ''}
                                    onValueChange={(value) => {
                                        // Radix devuelve cadena vacía al deseleccionar; el
                                        // instrumento no admite ítems en blanco.
                                        if (value) {
                                            setData('answers', { ...data.answers, [item]: Number(value) });
                                        }
                                    }}
                                    className="justify-start gap-2"
                                >
                                    {[1, 2, 3, 4, 5].map((value) => (
                                        <ToggleGroupItem
                                            key={value}
                                            value={value.toString()}
                                            aria-label={`${statement} — ${value} de 5${scaleLabels[value] ? `, ${scaleLabels[value]}` : ''}`}
                                            variant="outline"
                                            className={cn(
                                                'size-11 shrink-0 text-sm font-bold',
                                                'data-[state=on]:bg-primary data-[state=on]:text-primary-foreground',
                                            )}
                                        >
                                            {value}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>

                                <div className="text-muted-foreground flex justify-between text-xs">
                                    <span>{scaleLabels[1]}</span>
                                    <span>{scaleLabels[5]}</span>
                                </div>

                                {errors[`answers.${item}` as keyof typeof errors] && (
                                    <p className="text-destructive text-xs font-medium">{errors[`answers.${item}` as keyof typeof errors]}</p>
                                )}
                            </div>
                        ))}

                        <Field
                            label="¿Algo que quieras contarnos?"
                            htmlFor="comments"
                            error={errors.comments}
                            hint="Opcional. Lo que escribas se lee sin tu nombre."
                        >
                            <Textarea id="comments" rows={4} value={data.comments} onChange={(e) => setData('comments', e.target.value)} />
                        </Field>

                        <div className="flex flex-wrap items-center gap-3 pt-1">
                            <Button disabled={processing || answeredCount < items.length}>Enviar respuestas</Button>
                            <span className="text-muted-foreground text-sm">
                                {answeredCount} de {items.length} respondidas
                            </span>
                        </div>
                    </FormCard>
                </form>
            </div>
        </AppLayout>
    );
}
