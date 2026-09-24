# Protocolo de escalamiento del telemonitoreo

> **TODO: validar con la médica de la IPS.** Plantilla (Res. 1644 de 2026, art. 19 par. 1). La
> plataforma ya genera las señales de la tabla; **a quién se escala, en cuánto tiempo y qué se
> hace** lo define el equipo médico de la IPS.

## Señales que genera la plataforma

| Señal | Dónde aparece | Quién la ve |
|---|---|---|
| Medición fuera del rango de referencia (`config/vital_signs.php`) | Aviso al médico tratante y panel de telemonitoreo | Médico |
| Alteración sostenida y reglas del apoyo a decisiones (`config/clinical_support.php`) | Ficha del paciente y panel del médico | Médico |
| Control vencido según el nivel de riesgo (`max_days_without_reading`) | "Pacientes con control vencido" en el panel del médico; "Te toca medirte" al paciente | Médico y paciente |

## Escalamiento

| Situación | A quién se escala | Tiempo máximo | Cómo se hace | Cómo se registra |
|---|---|---|---|---|
| Medición fuera de rango, sin síntomas | **[Definir]** | **[Definir]** | **[Definir]** (¿llamada?, ¿teleconsulta prioritaria?) | **[Definir]** |
| Medición fuera de rango con síntomas de alarma | **[Definir]** | **[Definir]** | **[Definir]** | **[Definir]** |
| Alteración sostenida | **[Definir]** | **[Definir]** | **[Definir]** | **[Definir]** |
| Control vencido, primer aviso | El paciente recibe "Te toca medirte" (automático, una vez al día) | — | Notificación en la plataforma | Automático |
| Control vencido que sigue sin medición después de **[Definir]** días | **[Definir]** | **[Definir]** | **[Definir]** (¿llamada del auxiliar?, ¿visita?) | **[Definir]** |
| Paciente sin respuesta a los contactos | **[Definir]** | **[Definir]** | **[Definir]** | **[Definir]** |

## Frecuencia mínima de seguimiento por nivel de riesgo

El médico asigna a cada paciente un nivel (**bajo**, **medio**, **alto**) en su ficha, en la tarjeta *Seguimiento remoto*. Los días máximos sin medición de cada signo vital por nivel se definen en `config/vital_signs.php` → `max_days_without_reading`. **Hoy están todos en `null`**: mientras no se definan, la función está apagada y la plataforma lo dice.

| Signo vital | Bajo | Medio | Alto |
|---|---|---|---|
| Presión arterial | **[Definir]** | **[Definir]** | **[Definir]** |
| Glucemia | **[Definir]** | **[Definir]** | **[Definir]** |
| Peso | **[Definir]** | **[Definir]** | **[Definir]** |
| Otros (frecuencia cardíaca, temperatura, saturación) | **[Definir]** | **[Definir]** | **[Definir]** |

Un signo que se deja en `null` en un nivel no se exige en ese nivel.
