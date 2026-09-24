import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Loader2, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Option {
    code: string;
    display: string;
}

interface Props {
    id: string;
    /** Clave del catálogo oficial (cie10, divipola, tipo_documento...). */
    system: string;
    value: string;
    /** Nombre del código ya elegido, para mostrarlo sin buscarlo otra vez. */
    label?: string | null;
    onChange: (code: string, display: string) => void;
    placeholder?: string;
    invalid?: boolean;
}

/**
 * Buscador en un catálogo oficial importado.
 *
 * Pensado para personal que no es técnico: se escribe parte del nombre o del
 * código y se elige de una lista corta (20 resultados). Espera a que la
 * persona deje de escribir antes de consultar, para no gastar datos con cada
 * tecla en una conexión lenta.
 */
export function CodeSelect({ id, system, value, label, onChange, placeholder = 'Escribe para buscar…', invalid }: Props) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Option[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [selectedLabel, setSelectedLabel] = useState<string | null>(label ?? null);
    const latest = useRef(0);

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);
            return;
        }

        const request = ++latest.current;
        const timer = setTimeout(async () => {
            setLoading(true);
            setFailed(false);
            try {
                const response = await fetch(route('catalogos.buscar', { sistema: system, q: query }), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const body = (await response.json()) as { data: Option[] };
                if (request === latest.current) setResults(body.data ?? []);
            } catch {
                if (request === latest.current) setFailed(true);
            } finally {
                if (request === latest.current) setLoading(false);
            }
        }, 350);

        return () => clearTimeout(timer);
    }, [query, system]);

    if (value) {
        return (
            <div className="border-input bg-card flex min-h-11 items-center justify-between gap-2 rounded-lg border px-3.5 py-2">
                <span className="text-sm">
                    <span className="font-semibold">{value}</span>
                    {selectedLabel ? ` · ${selectedLabel}` : ''}
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        onChange('', '');
                        setSelectedLabel(null);
                        setQuery('');
                    }}
                >
                    <X />
                    Cambiar
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div className="relative">
                <Input
                    id={id}
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={placeholder}
                    autoComplete="off"
                    aria-invalid={invalid || undefined}
                    className="pr-10"
                />
                {loading ? (
                    <Loader2 className="text-muted-foreground absolute top-1/2 right-3.5 size-4 -translate-y-1/2 animate-spin" />
                ) : (
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2" />
                )}
            </div>

            {failed && <p className="text-destructive text-xs">No se pudo buscar. Revisa tu conexión e intenta otra vez.</p>}

            {results.length > 0 && (
                <ul className="border-border/70 bg-card max-h-60 overflow-y-auto rounded-lg border" aria-label="Resultados">
                    {results.map((option) => (
                        <li key={option.code}>
                            <button
                                type="button"
                                className="hover:bg-muted w-full px-3.5 py-2 text-left text-sm"
                                onClick={() => {
                                    onChange(option.code, option.display);
                                    setSelectedLabel(option.display);
                                    setResults([]);
                                }}
                            >
                                <span className="font-semibold">{option.code}</span> · {option.display}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {!loading && !failed && query.trim().length >= 2 && results.length === 0 && (
                <p className="text-muted-foreground text-xs">Sin resultados. Prueba con otra palabra.</p>
            )}
        </div>
    );
}
