# Guía de usuario — IPS NefroChocó

Cómo usar la plataforma según tu rol. Está escrita para leerse sin conocimientos técnicos.

- [Para todos](#para-todos)
- [Si eres paciente](#si-eres-paciente)
- [Si eres profesional de salud](#si-eres-profesional-de-salud)
- [Si eres administrador](#si-eres-administrador)

---

## Para todos

### Entrar

Ingresa con el correo y la contraseña que te asignó la IPS. Al entrar, la plataforma te lleva sola a la pantalla que te corresponde.

### Proteger tu cuenta con un código

En **Mi cuenta → Doble factor** puedes pedir que, además de tu contraseña, se te pida un código que cambia cada 30 segundos.

Necesitas una aplicación de autenticación en el teléfono (por ejemplo Google Authenticator). **Genera los códigos sin internet**, así que funciona aunque no tengas señal.

Al activarlo aparecen ocho **códigos de recuperación**. Guárdalos en un lugar seguro: son tu única forma de entrar si pierdes el teléfono, se muestran una sola vez y cada uno sirve una vez.

### Si se va la señal

La plataforma sigue abierta. Lo que registres queda guardado en tu teléfono y se envía solo cuando vuelve la conexión. Verás un aviso mientras haya cosas pendientes por enviar.

---

## Si eres paciente

### La primera vez

Antes de ver tus datos te pedimos autorizar su tratamiento, como exige la ley. Si no autorizas, no podemos mostrarte tu información.

### Tus citas

En **Mis citas** ves las próximas y el historial. Cada una dice si es **presencial** (vas al puesto de salud) o **teleconsulta** (por videollamada).

### Entrar a una teleconsulta

Cuando se acerque la hora aparece el botón **Unirse a la teleconsulta**, tanto en Inicio como en Mis citas. La sala se abre 15 minutos antes de tu cita.

La primera vez te vamos a explicar cómo funciona la atención por videollamada y a pedirte que la autorices. Es importante que sepas:

- **No hay examen físico.** Tu profesional no puede tocarte ni tomarte muestras. Si hace falta, te pedirá ir al puesto de salud.
- **La conexión puede cortarse.** Si pasa, vuelve a entrar: la sala sigue abierta y tu profesional te espera adentro.
- **La sala es privada** y solo entran tú y tu profesional. La videollamada no se graba.

Puedes pedir atención presencial en cualquier momento, sin dar explicaciones y sin perder tu cita.

> **Si la señal está débil:** apaga tu cámara. El audio consume mucho menos datos y es lo que tu profesional necesita para atenderte.

### Registrar tus mediciones

En **Signos vitales** anotas tu presión, peso, glucemia u otras medidas que te haya pedido tu profesional. Si un valor sale fuera de lo esperado, tu médico recibe un aviso.

Puedes registrarlas **sin conexión**: se guardan y se envían solas después. No hace falta que hagas nada.

### Tu historia y material educativo

En **Mi historia** consultas lo que tu profesional ha registrado. En **Educación** hay material sobre tu enfermedad, en lenguaje sencillo.

---

## Si eres profesional de salud

### Tu panel

Al entrar ves tu actividad: citas de hoy y de la semana, teleconsultas pendientes y las alertas de telemonitoreo de todo el programa.

**Pacientes para revisar primero** es una franja que cruza datos ya registrados y te sugiere a quién mirar antes. Cada sugerencia dice **por qué** se generó y con qué dato exacto.

> Son apoyo a la decisión, **no un diagnóstico**. La conducta final la defines tú.

### Pacientes

El padrón es de la IPS: puedes consultar y editar la ficha de cualquier persona inscrita, porque el programa rota profesionales y cubre municipios dispersos.

Desde la ficha registras entradas de historia clínica, aplicas formularios, ves el telemonitoreo y consultas el apoyo a la decisión de esa persona.

Eliminar una ficha no está a tu alcance: arrastra toda la historia clínica y queda en administración.

### Citas y teleconsultas

En **Citas** agendas y ves tu agenda en calendario. **Solo puedes modificar o cancelar tus propias citas**: las de otro profesional aparecen protegidas, para no romper su agenda ni la trazabilidad de quién decidió qué.

Al entrar a una teleconsulta se abre la sala de video con el paciente. Al terminar, **cierras la consulta escribiendo tus notas**, que quedan en la historia clínica y marcan la cita como completada. Esa acción es exclusivamente tuya.

### Formularios clínicos

En **Formularios** aplicas los instrumentos disponibles (tamizaje de riesgo de diabetes, adherencia al tratamiento, seguimiento de hipertensión). El puntaje y su interpretación se calculan solos y quedan guardados con la fecha.

### Telemonitoreo

Muestra las mediciones de cada paciente en gráficas y resalta las que salen del rango de referencia. Las alertas se calculan sobre todo el histórico, no solo sobre las últimas mediciones.

---

## Si eres administrador

### Usuarios

En **Usuarios** creas las cuentas del personal y de los pacientes que van a usar la plataforma, y asignas su rol. No hay registro autoservicio para el personal.

Recuerda que la ficha clínica y la cuenta son cosas distintas: muchas fichas corresponden a personas sin acceso a la plataforma.

### Custodia del padrón

En **Pacientes** ves todas las fichas con cuánto dato clínico cuelga de cada una, y puedes eliminarlas. Es la única zona donde se puede hacer.

Eliminar una ficha arrastra su historia clínica, sus controles y sus mediciones. Hazlo solo cuando corresponda por solicitud del titular o para depurar registros de prueba.

### Contenido educativo

En **Contenido educativo** publicas los materiales que ven los pacientes, clasificados por enfermedad.

### Auditoría

En **Auditoría** consultas quién hizo qué y cuándo, con dos filtros:

- **Accesos**: quién consultó datos clínicos, qué pantalla abrió (ficha, historia clínica, formulario o telemonitoreo), desde qué dirección y en qué momento. La ley exige poder demostrar las lecturas, no solo los cambios. Las filas marcadas como *refresco* son recargas de una pantalla ya abierta.
- **Cambios**: qué registros se crearon o modificaron y **qué campos** se tocaron.

El registro guarda los nombres de los campos, nunca su contenido: copiar un diagnóstico al registro de auditoría anularía el cifrado con que se guarda.
