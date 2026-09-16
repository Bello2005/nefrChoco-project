<?php

namespace Database\Seeders;

use App\Enums\EcntCategory;
use App\Enums\Role;
use App\Enums\VitalSignType;
use App\Models\Appointment;
use App\Models\ClinicalForm;
use App\Models\ClinicalHistory;
use App\Models\EducationalContent;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\ClinicalFormService;
use App\Services\TeleconsultationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración para entorno local.
 *
 * Usa municipios y perfiles clínicos representativos del Chocó para que los
 * tableros se evalúen con volúmenes y nombres realistas, no con "lorem ipsum".
 */
class DemoDataSeeder extends Seeder
{
    public function run(ClinicalFormService $clinicalFormService, TeleconsultationService $teleconsultationService): void
    {
        $doctor = User::firstOrCreate(
            ['email' => 'ana.mosquera@nefrochoco.test'],
            ['name' => 'Dra. Ana Mosquera', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $doctor->assignRole(Role::Medico->value);

        $secondDoctor = User::firstOrCreate(
            ['email' => 'carlos.rentería@nefrochoco.test'],
            ['name' => 'Dr. Carlos Rentería', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $secondDoctor->assignRole(Role::Medico->value);

        $patientUser = User::firstOrCreate(
            ['email' => 'juan.perea@nefrochoco.test'],
            ['name' => 'Juan Perea', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $patientUser->assignRole(Role::Paciente->value);

        $people = [
            ['María Palacios', 'femenino', '1077123456', 'Quibdó', '1979-05-10', 'Hipertensión arterial', null],
            // Única ficha con cuenta propia: permite probar la vista del paciente.
            ['Juan Perea', 'masculino', '1077998877', 'Istmina', '1968-11-02', 'Diabetes mellitus tipo 2', $patientUser->id],
            ['Rosalba Mosquera', 'femenino', '1077445566', 'Condoto', '1955-03-21', 'Enfermedad renal crónica', null],
            ['Efraín Moreno', 'masculino', '1077223344', 'Tadó', '1972-08-14', 'Hipertensión arterial', null],
            ['Yined Asprilla', 'femenino', '1077667788', 'Nuquí', '1988-01-30', 'Obesidad', null],
            ['Gilberto Cuesta', 'masculino', '1077334455', 'Quibdó', '1961-06-25', 'Diabetes mellitus tipo 2', null],
            ['Luz Dary Rivas', 'femenino', '1077556677', 'Bahía Solano', '1990-09-05', 'Riesgo cardiovascular', null],
        ];

        $patients = collect($people)->map(function (array $person) {
            [$name, $sex, $document, $municipality, $birthDate, $diagnosis, $userId] = $person;

            $patient = Patient::firstOrCreate(
                ['document_number' => $document],
                [
                    'user_id' => $userId,
                    // El paciente de demo llega sin autorizar para poder recorrer
                    // el flujo de consentimiento completo en la revisión.
                    'consent_accepted_at' => $userId ? null : now(),
                    'consent_version' => $userId ? null : config('privacy.consent_version'),
                    // Igual con el consentimiento de teleconsulta: el paciente
                    // de demo lo recorre completo al entrar a su primera sala.
                    'teleconsultation_consent_accepted_at' => $userId ? null : now(),
                    'teleconsultation_consent_version' => $userId ? null : config('privacy.teleconsultation_consent_version'),
                    'full_name' => $name,
                    'document_type' => 'CC',
                    'birth_date' => $birthDate,
                    'biological_sex' => $sex,
                    'municipality' => $municipality,
                    'phone' => '31'.random_int(10000000, 99999999),
                    'emergency_contact_name' => 'Contacto familiar',
                    'emergency_contact_phone' => '31'.random_int(10000000, 99999999),
                ],
            );

            if ($patient->clinicalHistories()->doesntExist()) {
                ClinicalHistory::create([
                    'patient_id' => $patient->id,
                    'ecnt_diagnosis' => $diagnosis,
                    'medical_history' => 'Paciente en seguimiento por programa de ECNT de la IPS.',
                    'allergies' => 'Ninguna conocida',
                    'current_medication' => 'Según esquema institucional vigente.',
                ]);
            }

            return $patient;
        });

        $this->seedAppointments($patients, $doctor, $secondDoctor, $teleconsultationService);
        $this->seedVitalSigns($patients, $doctor);
        $this->seedClinicalForms($patients, $doctor, $clinicalFormService);
        $this->seedEducationalContent();
    }

    private function seedAppointments($patients, User $doctor, User $secondDoctor, TeleconsultationService $teleconsultationService): void
    {
        if (Appointment::exists()) {
            return;
        }

        $schedule = [
            [0, 8, Appointment::TYPE_TELECONSULTATION, Appointment::STATUS_SCHEDULED],
            [0, 10, Appointment::TYPE_IN_PERSON, Appointment::STATUS_SCHEDULED],
            [1, 9, Appointment::TYPE_TELECONSULTATION, Appointment::STATUS_SCHEDULED],
            [2, 14, Appointment::TYPE_IN_PERSON, Appointment::STATUS_SCHEDULED],
            [3, 11, Appointment::TYPE_TELECONSULTATION, Appointment::STATUS_SCHEDULED],
            [-2, 9, Appointment::TYPE_IN_PERSON, Appointment::STATUS_COMPLETED],
            [-3, 15, Appointment::TYPE_TELECONSULTATION, Appointment::STATUS_COMPLETED],
            [-4, 10, Appointment::TYPE_IN_PERSON, Appointment::STATUS_NO_SHOW],
            [-5, 8, Appointment::TYPE_IN_PERSON, Appointment::STATUS_CANCELLED],
        ];

        foreach ($schedule as $index => [$dayOffset, $hour, $type, $status]) {
            $appointment = Appointment::create([
                'patient_id' => $patients[$index % $patients->count()]->id,
                'doctor_id' => $index % 3 === 0 ? $secondDoctor->id : $doctor->id,
                'scheduled_at' => now()->addDays($dayOffset)->setTime($hour, 0),
                'status' => $status,
                'type' => $type,
            ]);

            if ($type === Appointment::TYPE_TELECONSULTATION) {
                $teleconsultationService->createForAppointment($appointment);
            }
        }

        // El paciente con cuenta propia siempre arranca con una teleconsulta en
        // curso: sin ella no hay forma de revisar la sala desde su lado sin
        // esperar a que coincida la hora de una cita fija.
        $patientWithAccount = $patients->first(fn (Patient $patient) => $patient->user_id !== null);

        if ($patientWithAccount) {
            $liveAppointment = Appointment::create([
                'patient_id' => $patientWithAccount->id,
                'doctor_id' => $doctor->id,
                'scheduled_at' => now()->subMinutes(5),
                'status' => Appointment::STATUS_SCHEDULED,
                'type' => Appointment::TYPE_TELECONSULTATION,
            ]);

            $teleconsultationService->createForAppointment($liveAppointment);
        }
    }

    private function seedVitalSigns($patients, User $doctor): void
    {
        if (VitalSign::exists()) {
            return;
        }

        // Series con tendencia y algún valor fuera de rango, para que las
        // gráficas y el panel de alertas muestren comportamiento realista.
        $profiles = [
            [VitalSignType::BloodPressure, [128, 132, 135, 141, 138, 145]],
            [VitalSignType::HeartRate, [74, 78, 72, 80, 76, 82]],
            [VitalSignType::Weight, [78.4, 78.1, 77.6, 77.2, 76.9, 76.5]],
            [VitalSignType::OxygenSaturation, [97, 96, 97, 95, 96, 93]],
            [VitalSignType::Glucose, [118, 126, 134, 129, 142, 151]],
        ];

        foreach ($patients->take(5) as $patientIndex => $patient) {
            foreach ($profiles as [$type, $values]) {
                foreach ($values as $reading => $value) {
                    $jitter = ($patientIndex - 2) * 1.5;

                    $patient->vitalSigns()->create([
                        'recorded_by' => $doctor->id,
                        'type' => $type->value,
                        'value' => round($value + $jitter, 1),
                        'unit' => $type->unit(),
                        'recorded_at' => now()->subDays((count($values) - $reading) * 5),
                        'notes' => null,
                    ]);
                }
            }
        }
    }

    private function seedClinicalForms($patients, User $doctor, ClinicalFormService $clinicalFormService): void
    {
        if (ClinicalForm::exists()) {
            return;
        }

        $clinicalFormService->create($patients[0], $doctor, 'tamizaje_diabetes', [
            'edad' => '45_54',
            'imc' => 'sobrepeso',
            'perimetro_abdominal' => 'elevado',
            'actividad_fisica' => 'no',
            'frutas_verduras' => 'ocasional',
            'antecedente_familiar' => 'primer_grado',
            'observaciones' => 'Se entrega material educativo y se agenda control.',
        ]);

        $clinicalFormService->create($patients[1], $doctor, 'adherencia_tratamiento', [
            'olvida_medicamento' => 'si',
            'toma_hora_indicada' => 'no',
            'suspende_si_mejora' => 'no',
            'suspende_si_mal' => 'no',
            'barreras' => 'Dificultad de desplazamiento desde zona rural hasta el centro de salud.',
        ]);

        $clinicalFormService->create($patients[2], $doctor, 'seguimiento_hipertension', [
            'presion_sistolica' => 138,
            'presion_diastolica' => 86,
            'consumo_sal' => 'moderado',
            'sintomas' => 'Refiere cefalea ocasional.',
            'plan' => 'Continuar esquema actual y reforzar dieta hiposódica.',
        ]);

        $clinicalFormService->create($patients[3], $doctor, 'tamizaje_diabetes', [
            'edad' => 'menor_45',
            'imc' => 'normal',
            'perimetro_abdominal' => 'normal',
            'actividad_fisica' => 'si',
            'frutas_verduras' => 'diario',
            'antecedente_familiar' => 'no',
            'observaciones' => null,
        ]);
    }

    private function seedEducationalContent(): void
    {
        if (EducationalContent::exists()) {
            return;
        }

        $contents = [
            ['¿Qué es la hipertensión arterial?', 'Explicación sencilla sobre qué significa tener la presión alta y por qué es importante controlarla.', EducationalContent::TYPE_ARTICLE, EcntCategory::Hypertension],
            ['Cómo tomar tu presión en casa', 'Guía paso a paso para medir correctamente la presión arterial con un tensiómetro.', EducationalContent::TYPE_VIDEO, EcntCategory::Hypertension],
            ['Alimentación para personas con diabetes', 'Recomendaciones prácticas con alimentos disponibles en el Chocó.', EducationalContent::TYPE_PDF, EcntCategory::Diabetes],
            ['Cuidado de los pies en diabetes', 'Rutina diaria de revisión y cuidado para prevenir complicaciones.', EducationalContent::TYPE_VIDEO, EcntCategory::Diabetes],
            ['Tu riñón y el control de líquidos', 'Qué vigilar cuando vives con enfermedad renal crónica.', EducationalContent::TYPE_ARTICLE, EcntCategory::ChronicKidneyDisease],
            ['Actividad física sin gimnasio', 'Rutinas que puedes hacer en casa o en el campo, sin equipos.', EducationalContent::TYPE_VIDEO, EcntCategory::GeneralPrevention],
        ];

        foreach ($contents as [$title, $description, $type, $category]) {
            EducationalContent::create([
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'url_or_path' => 'https://www.minsalud.gov.co/salud/publica/PENT/Paginas/enfermedades-no-transmisibles.aspx',
                'ecnt_category' => $category->value,
            ]);
        }
    }
}
