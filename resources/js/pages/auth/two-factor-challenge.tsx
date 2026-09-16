import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { Field } from '@/components/forms/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';

export default function TwoFactorChallenge() {
    const [usingRecoveryCode, setUsingRecoveryCode] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        recovery_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.login'), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    const toggleMode = () => {
        setUsingRecoveryCode((previous) => !previous);
        reset('code', 'recovery_code');
    };

    return (
        <AuthLayout
            title="Verifica que eres tú"
            description={
                usingRecoveryCode
                    ? 'Escribe uno de los códigos de recuperación que guardaste al activar el doble factor.'
                    : 'Abre tu aplicación de autenticación y escribe el código de seis dígitos.'
            }
        >
            <Head title="Verificación en dos pasos" />

            <form className="space-y-5" onSubmit={submit}>
                {usingRecoveryCode ? (
                    <Field
                        label="Código de recuperación"
                        htmlFor="recovery_code"
                        error={errors.recovery_code ?? errors.code}
                        hint="Cada código sirve una sola vez."
                    >
                        <Input
                            id="recovery_code"
                            name="recovery_code"
                            required
                            autoFocus
                            autoComplete="one-time-code"
                            spellCheck={false}
                            value={data.recovery_code}
                            onChange={(e) => setData('recovery_code', e.target.value)}
                            placeholder="XXXXX-XXXXX"
                        />
                    </Field>
                ) : (
                    <Field label="Código de verificación" htmlFor="code" error={errors.code} hint="Cambia cada 30 segundos.">
                        <Input
                            id="code"
                            name="code"
                            required
                            autoFocus
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            className="tabular text-center text-lg tracking-[0.4em]"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                            placeholder="000000"
                        />
                    </Field>
                )}

                <Button type="submit" size="lg" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Entrar
                </Button>
            </form>

            <button
                type="button"
                onClick={toggleMode}
                className="text-muted-foreground hover:text-foreground focus-ring mt-6 w-full rounded-md text-center text-sm font-medium underline underline-offset-4"
            >
                {usingRecoveryCode ? 'Usar el código de mi aplicación' : '¿Perdiste tu teléfono? Usa un código de recuperación'}
            </button>
        </AuthLayout>
    );
}
