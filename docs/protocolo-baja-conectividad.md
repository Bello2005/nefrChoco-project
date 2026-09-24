# Protocolo de contingencia por baja conectividad

> **TODO: validar con la IPS.** Esta es una plantilla (Res. 1644 de 2026, art. 33). Los pasos,
> los tiempos y los canales los define la IPS con su equipo médico; aquí solo está la estructura
> y lo que la plataforma ya ofrece.

## Antes de la cita

1. El paciente usa **Probar mi conexión** desde *Mis citas*. La plataforma mide su internet, su cámara y su micrófono y le dice uno de tres resultados:
   - **Lista para video.**
   - **Mejor solo audio:** al entrar a la sala, apaga la cámara.
   - **No alcanza:** buscar un lugar con mejor señal, o comunicarse con la IPS para una llamada o una nueva fecha.
2. El médico ve el último resultado en su agenda y en la sala (*Conexión lista para video / para solo audio / insuficiente*).
3. **[Definir]** Si el resultado es *insuficiente*, ¿quién llama al paciente antes de la cita y con cuánta anticipación?

## Durante la teleconsulta

| Situación | Qué hacer | Quién | Tiempo máximo |
|---|---|---|---|
| La imagen se congela o el audio se entrecorta | Apagar las cámaras y seguir solo con audio | Médico y paciente | **[Definir]** |
| La llamada se cae | El paciente vuelve a entrar a la sala; la sala sigue abierta durante la ventana de la cita | Paciente | **[Definir]** minutos de espera |
| El paciente no logra volver a entrar | Pasar a **llamada telefónica** al número de la ficha | Médico | **[Definir]** |
| Tampoco hay llamada | Reprogramar la cita (queda *no asistió* o se reprograma según el caso) | **[Definir]** | **[Definir]** |
| Síntomas de alarma durante una llamada inestable | **[Definir con la médica]** | Médico | Inmediato |

## Cómo se registra en la nota

**[Definir con la médica]** Sugerencia de estructura para la nota de la teleconsulta:

- Modalidad final: video / solo audio / llamada telefónica.
- Si hubo cortes: cuántos y si afectaron la valoración.
- Si la atención no se pudo completar: motivo y conducta (reprogramación, remisión a atención presencial).

Una nota cerrada no se edita: si después se necesita corregir algo, se agrega una **aclaración** desde la historia clínica.

## Pruebas reales de conectividad (evidencia)

Pruebas hechas por Bello en el navegador con limitación de red (DevTools → *Network* → *Throttling*), repitiendo **Probar mi conexión** y una videollamada de prueba en `jitsi.bello.works`. Anotar el perfil usado (por ejemplo, *Slow 4G*, *3G* o un perfil propio con kbps y latencia).

| Fecha | Municipio | Red (operador / tipo) | Perfil de limitación | Resultado de la prueba | ¿Hubo video? ¿Audio? | Observaciones |
|---|---|---|---|---|---|---|
| | | | | | | |

Con estos resultados se ajustan los umbrales de `config/teleconsultation.php` (`connection_check`), que hoy están en **[CONFIRMAR]**.
