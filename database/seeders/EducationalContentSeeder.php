<?php

namespace Database\Seeders;

use App\Enums\EcntCategory;
use App\Models\EducationalContent;
use Illuminate\Database\Seeder;

/**
 * Material educativo del programa de ECNT.
 *
 * No es dato de demostración: es el contenido con el que arranca la plataforma,
 * así que corre también fuera de `local`. Está escrito dentro de la aplicación
 * en vez de enlazarse a otro sitio porque un enlace externo no se puede guardar
 * en el teléfono, y el compromiso del anteproyecto es que el material siga
 * disponible sin conexión continua.
 *
 * ---------------------------------------------------------------------------
 * TODO: validar con la médica de la IPS.
 *
 * TODOS los textos de este archivo son contenido clínico dirigido a pacientes y
 * NINGUNO ha sido revisado por la profesional de la IPS. Están redactados a
 * partir de guías públicas de promoción y prevención, en lenguaje sencillo y
 * evitando cualquier indicación de dosis o de tratamiento. Aun así, qué se le
 * dice a un paciente con enfermedad renal crónica sobre líquidos, sal o
 * analgésicos es una decisión clínica y la toma ella, no este archivo.
 *
 * Revisar antes de publicar en producción, uno por uno.
 * ---------------------------------------------------------------------------
 */
class EducationalContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->contents() as $content) {
            EducationalContent::firstOrCreate(
                ['title' => $content['title']],
                $content,
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function contents(): array
    {
        return [
            [
                'title' => 'Tus riñones y la enfermedad renal crónica',
                'description' => 'Qué hacen tus riñones, por qué la enfermedad avanza sin avisar y qué está en tus manos.',
                'type' => EducationalContent::TYPE_ARTICLE,
                'ecnt_category' => EcntCategory::ChronicKidneyDisease->value,
                'available_offline' => true,
                // TODO: validar con la médica de la IPS.
                'body' => <<<'MD'
                    Tus riñones son dos órganos del tamaño de un puño, uno a cada lado de la espalda baja. Trabajan todo el día filtrando la sangre: sacan lo que al cuerpo ya no le sirve y lo eliminan por la orina. También ayudan a mantener la presión en su sitio y a que tengas sangre suficiente.

                    ## Qué es la enfermedad renal crónica

                    Es cuando los riñones van perdiendo esa capacidad de filtrar, poco a poco y durante años. En el programa, las dos causas más frecuentes son **la presión alta** y **la diabetes**: ambas van dañando los filtros del riñón sin que la persona sienta nada.

                    ## Por qué es tan importante el control

                    Esta es la parte difícil de aceptar: **la enfermedad renal avanza sin síntomas**. Puedes sentirte perfectamente bien y tener los riñones ya afectados. Los primeros avisos aparecen cuando se ha perdido bastante función.

                    Por eso tu equipo médico te pide exámenes de sangre y de orina aunque te sientas bien. Con ellos calculan qué tanto están filtrando tus riñones y pueden actuar temprano, que es cuando de verdad se puede frenar.

                    ## Qué está en tus manos

                    - **Controlar la presión y el azúcar.** Es lo que más protege al riñón.
                    - **Tomar los medicamentos todos los días**, también los días en que te sientes bien.
                    - **Cuidar la sal.** No es solo el salero: los cubos de caldo, los embutidos y los paquetes traen mucha.
                    - **No tomar analgésicos por tu cuenta.** Los antiinflamatorios como el ibuprofeno o el diclofenaco pueden dañar el riñón. Pregunta antes de tomar cualquiera.
                    - **No fumar.**
                    - **Ir a tus controles**, aunque sea por teleconsulta y aunque ese mes te sientas bien.

                    ## Cuándo avisar sin esperar al control

                    Comunícate con la IPS si notas hinchazón en los pies, los tobillos o la cara, si estás orinando mucho menos de lo normal, si te sientes cansado de una forma que no es la habitual en ti, o si tienes náuseas que no se te quitan.
                    MD,
            ],
            [
                'title' => 'Hipertensión: la enfermedad que no se siente',
                'description' => 'Por qué hay que tratar la presión alta aunque no duela nada, y qué puedes hacer desde casa.',
                'type' => EducationalContent::TYPE_ARTICLE,
                'ecnt_category' => EcntCategory::Hypertension->value,
                'available_offline' => true,
                // TODO: validar con la médica de la IPS.
                'body' => <<<'MD'
                    La presión arterial es la fuerza con la que la sangre empuja las paredes de tus arterias. Cuando se mide salen dos números: el de arriba, cuando el corazón late, y el de abajo, cuando descansa entre latido y latido.

                    ## "Pero yo no siento nada"

                    Es lo más común, y es justo el problema. **La mayoría de las personas con presión alta no siente absolutamente nada durante años.** No duele la cabeza, no se marea, no hay ninguna señal.

                    Que no se sienta no quiere decir que no haga daño. Mientras tanto, la presión alta va forzando el corazón y deteriorando las arterias del riñón, de los ojos y del cerebro. El daño se acumula en silencio.

                    ## El medicamento se toma todos los días

                    Si te recetaron tratamiento, es para tomarlo a diario, incluso cuando te sientes bien. De hecho, **sentirte bien suele ser señal de que el medicamento está funcionando**, no de que ya no lo necesitas.

                    No lo suspendas ni cambies la cantidad por tu cuenta. Si te está cayendo mal o se te acabó, coméntalo en la consulta o escribe a la IPS: siempre hay algo que ajustar.

                    ## La sal escondida

                    Bajar la sal ayuda de verdad, pero casi nunca está donde uno cree. Más que el salero, mira:

                    - Cubos y sobres de caldo concentrado
                    - Embutidos: salchichas, mortadela, chorizo
                    - Paquetes y pasabocas
                    - Enlatados y sopas de sobre

                    Cocinar con ajo, cebolla, cilantro, limón o achiote le devuelve sabor a la comida sin agregar sal.

                    ## Medirte en casa ayuda a tu equipo

                    Una sola medición en el consultorio dice poco; varias tomadas en tu casa, a lo largo de las semanas, dicen mucho. Registra cada medición en la app, en **Signos vitales**. Aunque estés sin señal se guarda y se envía sola cuando vuelva.
                    MD,
            ],
            [
                'title' => 'Diabetes tipo 2: tu día a día',
                'description' => 'Qué vigilar cada día, cómo cuidar tus pies y cómo reconocer cuándo el azúcar está muy bajo o muy alto.',
                'type' => EducationalContent::TYPE_ARTICLE,
                'ecnt_category' => EcntCategory::Diabetes->value,
                'available_offline' => true,
                // TODO: validar con la médica de la IPS.
                'body' => <<<'MD'
                    En la diabetes tipo 2, el azúcar se queda en la sangre en lugar de entrar a las células para darte energía. Mantenerla controlada es lo que evita que, con los años, dañe los ojos, los riñones, los nervios y el corazón.

                    ## Tus pies merecen un minuto al día

                    Es la recomendación que más se pasa por alto y la que más complicaciones evita. La diabetes puede ir quitando sensibilidad en los pies: una herida deja de doler, no te das cuenta y se complica.

                    Cada día, con buena luz:

                    - Míralos completos, también entre los dedos y la planta. Si no alcanzas a verla, usa un espejo o pide ayuda.
                    - Busca cortes, ampollas, grietas o zonas enrojecidas.
                    - Sécalos bien después de bañarte, sobre todo entre los dedos.
                    - **No camines descalzo**, ni dentro de la casa.
                    - Usa calzado cerrado que no apriete y revisa por dentro antes de ponértelo.

                    **Una herida en el pie que no cierra, o que huele mal, no espera al próximo control.** Comunícate con la IPS ese mismo día.

                    ## Cuando el azúcar baja demasiado

                    Puede pasar si te saltaste una comida o hiciste más esfuerzo del habitual. Se siente como sudor frío, temblor, mareo, hambre repentina o dificultad para pensar con claridad.

                    Si te pasa, toma algo dulce de inmediato y cuéntalo en tu próximo control, para que revisen qué hay que ajustar.

                    ## Cuando el azúcar está alto

                    Mucha sed, orinar muy seguido, visión borrosa y cansancio son las señales típicas. Si se mantienen varios días, avisa a tu equipo médico.

                    ## Lo que sostiene todo lo demás

                    Comer a horas parecidas cada día, moverte un poco todos los días, tomar los medicamentos como te los indicaron y registrar tus mediciones en la app. Nada de eso es espectacular, y es exactamente lo que funciona.
                    MD,
            ],
            [
                'title' => 'Comer bien con lo que hay en el Chocó',
                'description' => 'Cómo armar tu plato con pescado, plátano, arroz y frutas de la región, sin dietas imposibles.',
                'type' => EducationalContent::TYPE_ARTICLE,
                'ecnt_category' => EcntCategory::Obesity->value,
                'available_offline' => true,
                // TODO: validar con la médica de la IPS.
                'body' => <<<'MD'
                    Comer bien no es comer raro ni caro. Casi todo lo que necesitas ya está en la plaza, en el río y en el patio. La idea no es prohibirte cosas, sino cambiar las proporciones.

                    ## El plato, repartido

                    Imagina tu plato dividido así:

                    - **La mitad**, verduras y frutas.
                    - **Un cuarto**, proteína: pescado fresco, pollo, huevo, fríjol o lenteja.
                    - **Un cuarto**, harinas: arroz, plátano, yuca o ñame.

                    La mayoría de nosotros tiene el plato al revés, con las harinas ocupando la mitad. Corregir esa proporción, sin quitar ningún alimento, ya cambia bastante.

                    ## Lo que juega a tu favor por aquí

                    El pescado fresco del río y del mar es una de las mejores proteínas que existen. El borojó, el chontaduro, la papaya, el banano, la guayaba y los cítricos son frutas de la región. El plátano y la yuca son buenas harinas: el punto es cuánto, no si sí o si no.

                    Cuando puedas, prefiere sudado, asado o sancochado antes que frito. No hay que eliminar el frito de la vida, pero no tiene que ser de todos los días.

                    ## Donde se esconden las calorías

                    - **Las bebidas azucaradas.** Un vaso de gaseosa o de jugo con azúcar añadida puede tener más azúcar que un postre, y no llena.
                    - **Los paquetes.** Aportan sal, grasa y poco más.
                    - **El azúcar del tinto.** Bajarla de a poco funciona mejor que quitarla de golpe.

                    Toma agua. Si en tu zona el agua no es segura, hiérvela o trátala antes.

                    ## Moverte cuenta, aunque no sea "ejercicio"

                    Caminar, trabajar la parcela, cargar, barrer, bailar. No necesitas gimnasio ni ropa especial: necesitas que la mayoría de los días tengas un rato de movimiento.
                    MD,
            ],
            [
                'title' => 'Señales de alarma: cuándo no esperar al control',
                'description' => 'Situaciones en las que hay que buscar atención de inmediato, sin esperar la teleconsulta.',
                'type' => EducationalContent::TYPE_ARTICLE,
                'ecnt_category' => EcntCategory::GeneralPrevention->value,
                'available_offline' => true,
                // TODO: validar con la médica de la IPS.
                'body' => <<<'MD'
                    La teleconsulta sirve para tu seguimiento: revisar cómo vas, ajustar el tratamiento, resolver dudas. **No sirve para una urgencia.** Hay situaciones en las que no hay que esperar cita ni señal: hay que buscar atención de inmediato.

                    ## Busca atención ya mismo si tienes

                    - **Dolor en el pecho**, sobre todo si aprieta o se corre al brazo, al cuello o a la mandíbula.
                    - **Dificultad para respirar** estando en reposo.
                    - **Debilidad repentina** en la cara, un brazo o una pierna, o dificultad súbita para hablar o entender. Aquí los minutos cuentan.
                    - **Dolor de cabeza muy fuerte y distinto** a los que te dan normalmente, sobre todo con visión borrosa o vómito.
                    - **Hinchazón que aumenta rápido** en piernas, cara o abdomen.
                    - **No haber orinado** en todo el día.
                    - **Una herida en el pie** que no cierra, está caliente, huele mal o tiene pus.
                    - **Confusión, sudor frío y temblor** que no mejoran después de comer algo dulce.

                    ## Qué hacer

                    Ve al puesto de salud u hospital más cercano, o pide ayuda para llegar. No esperes a que pase solo y no esperes a la próxima teleconsulta.

                    Si estás en una zona donde llegar toma horas, sal igual: es preferible llegar y que no fuera nada, a quedarte esperando.

                    ## Después

                    Cuando pase la urgencia, cuéntale a tu equipo en el siguiente control qué ocurrió y qué te hicieron. Esa información cambia tu seguimiento.
                    MD,
            ],
            [
                // Enlace externo a propósito: sirve para ver en la interfaz la diferencia
                // entre el material que viaja con la aplicación y el que exige señal.
                'title' => 'Cómo tomar tu presión en casa (video)',
                'description' => 'Video paso a paso para medir correctamente con el tensiómetro. Requiere conexión.',
                'type' => EducationalContent::TYPE_VIDEO,
                'ecnt_category' => EcntCategory::Hypertension->value,
                'available_offline' => false,
                'url_or_path' => 'https://www.minsalud.gov.co/salud/publica/PENT/Paginas/enfermedades-no-transmisibles.aspx',
            ],
        ];
    }
}
