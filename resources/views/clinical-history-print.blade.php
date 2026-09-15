<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Historia clínica · {{ $patient->full_name }}</title>
        <style>
            @page { size: A4; margin: 18mm 16mm; }

            /* Documento destinado a papel: siempre claro, sin importar el tema del dispositivo. */
            :root { color-scheme: light; }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                background: #fff;
                font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
                color: #14242a;
                font-size: 12px;
                line-height: 1.6;
            }

            .sheet { max-width: 820px; margin: 0 auto; padding: 28px 24px; }

            header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 20px;
                border-bottom: 2px solid #1a6b6f;
                padding-bottom: 14px;
            }

            .brand { display: flex; align-items: center; gap: 11px; }

            .mark {
                width: 40px; height: 40px; border-radius: 10px;
                background: #1a6b6f; color: #fff;
                display: flex; align-items: center; justify-content: center;
            }

            .brand strong { display: block; font-size: 15px; letter-spacing: -0.01em; }
            .brand span { font-size: 11px; color: #5a6f76; }

            .meta { text-align: right; font-size: 11px; color: #5a6f76; }

            h1 { font-size: 17px; margin: 22px 0 14px; letter-spacing: -0.01em; }
            h2 { font-size: 12px; margin: 20px 0 7px; text-transform: uppercase; letter-spacing: 0.06em; color: #1a6b6f; }

            .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px 20px; }

            .field { border-bottom: 1px solid #e2eaec; padding-bottom: 6px; }
            .field dt { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #7b8d93; margin: 0; }
            .field dd { margin: 2px 0 0; font-weight: 600; }

            .block {
                border: 1px solid #e2eaec;
                border-radius: 8px;
                padding: 12px 14px;
                white-space: pre-line;
                min-height: 44px;
            }

            footer {
                margin-top: 34px;
                border-top: 1px solid #e2eaec;
                padding-top: 12px;
                font-size: 10px;
                color: #7b8d93;
            }

            .signature { margin-top: 42px; display: flex; gap: 40px; }
            .signature div { flex: 1; border-top: 1px solid #14242a; padding-top: 6px; font-size: 10px; color: #5a6f76; }

            .toolbar { max-width: 820px; margin: 16px auto 0; padding: 0 24px; text-align: right; }

            .toolbar button {
                padding: 9px 18px; border: 0; border-radius: 8px;
                background: #1a6b6f; color: #fff;
                font: inherit; font-weight: 600; cursor: pointer;
            }

            @media print { .toolbar { display: none; } .sheet { padding: 0; } }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <button type="button" onclick="window.print()">Imprimir o guardar como PDF</button>
        </div>

        <div class="sheet">
            <header>
                <div class="brand">
                    <span class="mark">
                        <svg width="22" height="22" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 3.2c4.9 4.6 8.4 8.7 8.4 13.1A8.4 8.4 0 0 1 16 24.7a8.4 8.4 0 0 1-8.4-8.4c0-4.4 3.5-8.5 8.4-13.1Z"/>
                            <path d="M10.6 16.8h2.6l1.5-3.4 2.1 6 1.6-2.6h2.9"/>
                        </svg>
                    </span>
                    <div>
                        <strong>IPS NefroChocó</strong>
                        <span>Programa de enfermedades crónicas no transmisibles</span>
                    </div>
                </div>

                <div class="meta">
                    Historia N.º {{ $history->id }}<br>
                    Emitida el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm a') }}
                </div>
            </header>

            <h1>Historia clínica</h1>

            <h2>Identificación del paciente</h2>
            <dl class="grid">
                <div class="field"><dt>Nombre completo</dt><dd>{{ $patient->full_name }}</dd></div>
                <div class="field"><dt>Documento</dt><dd>{{ $patient->document_type }} {{ $patient->document_number }}</dd></div>
                <div class="field"><dt>Fecha de nacimiento</dt><dd>{{ $patient->birth_date->format('d/m/Y') }} ({{ $patient->birth_date->age }} años)</dd></div>
                <div class="field"><dt>Municipio</dt><dd>{{ $patient->municipality }}</dd></div>
                <div class="field"><dt>Teléfono</dt><dd>{{ $patient->phone }}</dd></div>
                <div class="field"><dt>Contacto de emergencia</dt><dd>{{ $patient->emergency_contact_name ?? '—' }} {{ $patient->emergency_contact_phone }}</dd></div>
            </dl>

            <h2>Diagnóstico ECNT</h2>
            <div class="block">{{ $history->ecnt_diagnosis ?: 'Sin diagnóstico registrado.' }}</div>

            <h2>Antecedentes</h2>
            <div class="block">{{ $history->medical_history ?: 'Sin antecedentes registrados.' }}</div>

            <h2>Alergias</h2>
            <div class="block">{{ $history->allergies ?: 'Ninguna registrada.' }}</div>

            <h2>Medicación actual</h2>
            <div class="block">{{ $history->current_medication ?: 'Ninguna registrada.' }}</div>

            <div class="signature">
                <div>Firma del profesional</div>
                <div>Registro médico</div>
            </div>

            <footer>
                Documento generado por {{ $printedBy->name }} el {{ now()->format('d/m/Y H:i') }}.
                Contiene datos personales sensibles protegidos por la Ley 1581 de 2012; su consulta quedó registrada en la auditoría de la
                plataforma. Custodia y circulación restringida al personal autorizado de la IPS.
            </footer>
        </div>
    </body>
</html>
