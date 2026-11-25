<?php

namespace Database\Seeders;

use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\CrCanton;
use App\Models\CrDistrict;
use App\Models\CrProvince;
use App\Models\DiplomaTemplate;
use App\Models\InstitutionSetting;
use App\Models\Picklist;
use Illuminate\Database\Seeder;

class CostaRicaCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Configuración Institucional CeCap — SURÍ Proyecto Educativo
        if (! InstitutionSetting::query()->exists()) {
            InstitutionSetting::create([
                'institution_name' => 'CeCap — SURÍ Proyecto Educativo',
                'short_name' => 'CeCap SURÍ',
                'legal_id' => '3-002-045123',
                'country' => 'Costa Rica',
                'phone' => '+506 2253-0000',
                'email' => 'admisiones@cecap.or.cr',
                'address' => 'San José, Costa Rica — Sede Central SURÍ Proyecto Educativo',
                'website' => 'https://cecap.or.cr',
                'theme_color' => '#0F4C4C',
                'accent_color' => '#C87D32',
                'online_enrollment_provisional_days' => 7,
                'online_enrollment_notice' => 'Su inscripción en línea es PROVISIONAL y mantiene su espacio reservado por un plazo máximo de 7 días naturales. Para formalizar la matrícula debe presentarse en las oficinas de CeCap a cancelar la matrícula y la primera mensualidad junto con sus documentos originales.',
                'attention_hours' => 'Lunes a Viernes de 8:00 a.m. a 4:30 p.m. · Sábados de 8:00 a.m. a 12:00 m.d.',
                'gender_policy_message' => 'Agradecemos su interés en CeCap (SURÍ Proyecto Educativo). Actualmente nuestros programas educativos están dirigidos exclusivamente a mujeres, por lo que esta solicitud no cumple con los parámetros de ingreso de la institución.',
                'require_financial_solvency_for_graduation' => true,
                'enabled_modules' => array_fill_keys(array_keys(config('cecap.modules')), true),
                'receipt_prefix' => 'REC',
                'diploma_footer_text' => 'Verificable en línea mediante código QR oficial de CeCap — SURÍ Proyecto Educativo, Costa Rica.',
                'director_name' => 'Licda. Dirección General CeCap',
                'director_title' => 'Directora General — SURÍ Proyecto Educativo',
            ]);
        }

        // 2. Turnos Académicos
        $shifts = [
            ['code' => 'matutino', 'name' => 'Matutino', 'start_time' => '08:00:00', 'end_time' => '12:00:00'],
            ['code' => 'vespertino', 'name' => 'Vespertino', 'start_time' => '13:00:00', 'end_time' => '17:00:00'],
            ['code' => 'nocturno', 'name' => 'Nocturno', 'start_time' => '18:00:00', 'end_time' => '21:30:00'],
        ];
        foreach ($shifts as $s) {
            AcademicShift::firstOrCreate(['code' => $s['code']], $s);
        }

        // 3. Modalidades Académicas
        $modalities = [
            ['code' => 'presencial', 'name' => 'Presencial'],
            ['code' => 'virtual', 'name' => 'Virtual'],
            ['code' => 'hibrida', 'name' => 'Híbrida'],
        ];
        foreach ($modalities as $m) {
            AcademicModality::firstOrCreate(['code' => $m['code']], $m);
        }

        // 4. Picklists Administrables Costa Rica
        $picklists = [
            'education_level' => [
                ['sin_requisito', 'Sin requisito académico previo'],
                ['primaria_incompleta', 'Primaria incompleta'],
                ['primaria_completa', 'Primaria completa (VI grado)'],
                ['noveno_aprobado', 'Tercer Ciclo aprobado (IX año)'],
                ['bachillerato_secundaria', 'Bachillerato en Educación Media'],
                ['tecnico_medio', 'Técnico Medio / Superior'],
                ['universitario', 'Educación Universitaria'],
            ],
            'id_type' => [
                ['cedula_nacional', 'Cédula Nacional (CR)'],
                ['dimex', 'DIMEX (Residencia CR)'],
                ['pasaporte', 'Pasaporte'],
                ['permiso_refugio', 'Carné de Refugio / Permiso Laboral'],
            ],
            'gender' => [
                ['femenino', 'Femenino'],
                ['masculino', 'Masculino'],
                ['otro', 'Otro / Prefiero no indicar'],
            ],
            'payment_method' => [
                ['efectivo', 'Efectivo (CRC)'],
                ['sinpe_movil', 'SINPE Móvil'],
                ['transferencia', 'Transferencia Bancaria'],
                ['tarjeta', 'Tarjeta Débito / Crédito'],
                ['deposito', 'Depósito Bancario'],
            ],
            'grade_rectification_reason' => [
                ['error_digitacion', 'Error de digitación en acta/sistema'],
                ['revision_examen', 'Revisión de examen o proyecto aprobada'],
                ['reposicion_justificada', 'Evaluación de reposición justificada'],
                ['apelacion_academica', 'Resolución de apelación académica'],
            ],
            'document_category' => [
                ['reglamento', 'Reglamentos y Políticas'],
                ['circular', 'Circulares y Comunicados'],
                ['programa_estudio', 'Programas de Estudio y Mallas'],
                ['formulario', 'Formularios y Plantillas'],
                ['acta_institucional', 'Actas y Convenios'],
            ],
            'workshop_type' => [
                ['taller', 'Taller Práctico'],
                ['charla', 'Charla / Conferencia'],
                ['feria_empleo', 'Feria de Empleo / Emprendimiento'],
                ['jornada_social', 'Jornada Comunitaria'],
            ],
            'payer_type' => [
                ['alumna', 'Propia Alumna'],
                ['familiar', 'Familiar / Encargado'],
                ['empresa', 'Empresa Patrocinante'],
                ['ong', 'ONG / Fundación'],
                ['institucion_publica', 'Institución Pública (IMAS / Municipalidad)'],
            ],
        ];

        foreach ($picklists as $group => $items) {
            foreach ($items as $idx => [$code, $label]) {
                Picklist::firstOrCreate(
                    ['group_key' => $group, 'code' => $code],
                    [
                        'label' => $label,
                        'sort_order' => ($idx + 1) * 10,
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );
            }
        }

        // 5. División Territorial Costa Rica (7 Provincias con Cantones y Distritos en cascada)
        $territories = [
            ['01', 'San José', [
                ['0101', 'San José', [['010101', 'Carmen'], ['010102', 'Merced'], ['010103', 'Hospital'], ['010104', 'Catedral'], ['010105', 'Zapote'], ['010107', 'Uruca'], ['010109', 'Pavas']]],
                ['0102', 'Escazú', [['010201', 'Escazú'], ['010202', 'San Antonio'], ['010203', 'San Rafael']]],
                ['0103', 'Desamparados', [['010301', 'Desamparados'], ['010302', 'San Miguel'], ['010305', 'San Antonio'], ['010307', 'Patarrá']]],
                ['0108', 'Goicoechea', [['010801', 'Guadalupe'], ['010802', 'San Francisco'], ['010803', 'Calle Blancos'], ['010805', 'Ipís']]],
                ['0114', 'Moravia', [['011401', 'San Vicente'], ['011402', 'San Jerónimo'], ['011403', 'La Trinidad']]],
                ['0115', 'Montes de Oca', [['011501', 'San Pedro'], ['011502', 'Sabanilla'], ['011503', 'Mercedes'], ['011504', 'San Rafael']]],
                ['0118', 'Curridabat', [['011801', 'Curridabat'], ['011802', 'Granadilla'], ['011803', 'Sánchez'], ['011804', 'Tirrases']]],
            ]],
            ['02', 'Alajuela', [
                ['0201', 'Alajuela', [['020101', 'Alajuela'], ['020102', 'San José'], ['020103', 'Carrizal'], ['020105', 'Guácima']]],
                ['0202', 'San Ramón', [['020201', 'San Ramón'], ['020202', 'Santiago'], ['020203', 'San Juan']]],
                ['0203', 'Grecia', [['020301', 'Grecia'], ['020302', 'San Isidro'], ['020303', 'San José']]],
                ['0210', 'San Carlos', [['021001', 'Quesada'], ['021002', 'Florencia'], ['021007', 'La Fortuna']]],
            ]],
            ['03', 'Cartago', [
                ['0301', 'Cartago', [['030101', 'Oriental'], ['030102', 'Occidental'], ['030103', 'Carmen'], ['030104', 'San Nicolás']]],
                ['0302', 'Paraíso', [['030201', 'Paraíso'], ['030202', 'Santiago'], ['030203', 'Orosi']]],
                ['0303', 'La Unión', [['030301', 'Tres Ríos'], ['030302', 'San Diego'], ['030303', 'San Juan']]],
                ['0305', 'Turrialba', [['030501', 'Turrialba'], ['030502', 'La Suiza']]],
            ]],
            ['04', 'Heredia', [
                ['0401', 'Heredia', [['040101', 'Heredia'], ['040102', 'Mercedes'], ['040103', 'San Francisco'], ['040104', 'Ulloa']]],
                ['0402', 'Barva', [['040201', 'Barva'], ['040202', 'San Pedro']]],
                ['0403', 'Santo Domingo', [['040301', 'Santo Domingo'], ['040302', 'San Vicente']]],
                ['0407', 'Belén', [['040701', 'San Antonio'], ['040702', 'La Ribera'], ['040703', 'La Asunción']]],
            ]],
            ['05', 'Guanacaste', [
                ['0501', 'Liberia', [['050101', 'Liberia'], ['050102', 'Cañas Dulces'], ['050104', 'Nacascolo']]],
                ['0502', 'Nicoya', [['050201', 'Nicoya'], ['050202', 'Mansión'], ['050206', 'Nosara']]],
                ['0503', 'Santa Cruz', [['050301', 'Santa Cruz'], ['050309', 'Tamarindo']]],
            ]],
            ['06', 'Puntarenas', [
                ['0601', 'Puntarenas', [['060101', 'Puntarenas'], ['060108', 'Barranca'], ['060115', 'El Roble']]],
                ['0602', 'Esparza', [['060201', 'Espíritu Santo'], ['060202', 'San Juan Grande']]],
                ['0603', 'Buenos Aires', [['060301', 'Buenos Aires'], ['060302', 'Volcán']]],
            ]],
            ['07', 'Limón', [
                ['0701', 'Limón', [['070101', 'Limón'], ['070102', 'Valle La Estrella'], ['070103', 'Río Blanco']]],
                ['0702', 'Pococí', [['070201', 'Guápiles'], ['070202', 'Jiménez'], ['070203', 'Rita']]],
                ['0703', 'Siquirres', [['070301', 'Siquirres'], ['070302', 'Pacuarito']]],
            ]],
        ];

        foreach ($territories as [$pCode, $pName, $cantons]) {
            $province = CrProvince::firstOrCreate(['code' => $pCode], ['name' => $pName, 'is_active' => true]);
            foreach ($cantons as [$cCode, $cName, $districts]) {
                $canton = CrCanton::firstOrCreate(
                    ['province_id' => $province->id, 'code' => $cCode],
                    ['name' => $cName, 'is_active' => true]
                );
                foreach ($districts as [$dCode, $dName]) {
                    CrDistrict::firstOrCreate(
                        ['canton_id' => $canton->id, 'code' => $dCode],
                        ['name' => $dName, 'is_active' => true]
                    );
                }
            }
        }

        // 6. Plantillas base de Diploma
        if (! DiplomaTemplate::query()->exists()) {
            DiplomaTemplate::create([
                'name' => 'Plantilla Institucional Clásica CeCap SURÍ',
                'program_type' => null,
                'header_title' => 'Certificado Oficial de Aprovechamiento',
                'body_template' => 'CeCap — SURÍ Proyecto Educativo hace constar que {{student_name}}, portadora de la identificación {{student_id}}, ha completado y aprobado satisfactoriamente el programa "{{course_name}}" ({{program_type}}), con una duración de {{duration_hours}} horas lectivas y calificación final de {{final_grade}}.',
                'primary_color' => '#0F4C4C',
                'accent_color' => '#C87D32',
                'border_style' => 'classic',
                'signatory_one_name' => 'Dirección General CeCap',
                'signatory_one_title' => 'Directora General — SURÍ Proyecto Educativo',
                'signatory_two_name' => 'Coordinación Académica',
                'signatory_two_title' => 'Coordinación Académica CeCap',
                'is_default' => true,
                'is_active' => true,
            ]);
        }
    }
}
