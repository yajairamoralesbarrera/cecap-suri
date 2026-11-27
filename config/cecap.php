<?php

return [
    'roles' => [
        'super_admin' => [
            'label' => 'Super Administrador',
            'description' => 'Único perfil con permiso system.code.manage para configuraciones críticas a nivel de código y gestión de otros Super Administradores.',
            'is_critical' => true,
        ],
        'direccion' => [
            'label' => 'Dirección / Administrador',
            'description' => 'Administración operativa integral, excepciones de solvencia/graduación, tablero gerencial y gestión de administradores operativos.',
            'is_critical' => true,
        ],
        'secretaria' => [
            'label' => 'Secretaría',
            'description' => 'Admisiones, matrícula presencial, revisión documental, planillas PDF, expediente de alumnas y repositorio documental.',
            'is_critical' => false,
        ],
        'administracion_caja' => [
            'label' => 'Administración / Caja',
            'description' => 'Planes de cobro, becas, caja, emisión de recibos REC-YYYY-NNNNN, reversos auditados, morosidad y pagos de colaboradores.',
            'is_critical' => false,
        ],
        'coordinacion_academica' => [
            'label' => 'Coordinación Académica',
            'description' => 'Gestión global de categorías, cursos, materias, ediciones, horarios anti-choque, seguimiento académico y graduación.',
            'is_critical' => false,
        ],
        'coordinador_categoria' => [
            'label' => 'Coordinador de Área / Categoría',
            'description' => 'Gestión académica restringida estrictamente a las categorías asignadas en user_academic_categories.',
            'is_critical' => false,
        ],
        'profesora' => [
            'label' => 'Profesora',
            'description' => 'Registro de asistencia móvil versionada, notas con rectificación auditada y habilidades blandas de sus grupos asignados.',
            'is_critical' => false,
        ],
        'alumna' => [
            'label' => 'Alumna',
            'description' => 'Portal estudiantil: consulta de matrículas, horarios, asistencia, calificaciones, habilidades blandas, recibos y Planilla PDF.',
            'is_critical' => false,
        ],
    ],

    'role_labels' => [
        'super_admin' => 'Super Administrador',
        'direccion' => 'Dirección / Administrador',
        'secretaria' => 'Secretaría',
        'administracion_caja' => 'Administración / Caja',
        'coordinacion_academica' => 'Coordinación Académica',
        'coordinador_categoria' => 'Coordinador de Área / Categoría',
        'profesora' => 'Profesora',
        'alumna' => 'Alumna',
    ],

    'modules' => [
        'academico' => 'Módulo Académico',
        'admisiones' => 'Admisiones y Matrícula',
        'seguimiento' => 'Seguimiento Académico',
        'finanzas' => 'Finanzas y Caja',
        'graduacion' => 'Graduación y Diplomas',
        'personal' => 'Colaboradores',
        'actividades' => 'Talleres y Actividades',
        'documentos' => 'Repositorio Documental',
        'consultas' => 'Expediente Alumnas',
        'direccion' => 'Dirección y Gobernanza',
    ],

    'document_types' => [
        'cedula_dimex' => 'Cédula / DIMEX',
        'atestado_academico' => 'Atestados Académicos (Títulos / Notas)',
        'atestado_profesional' => 'Atestados Profesionales (Títulos / Colegiatura)',
        'contrato_pdf' => 'Contrato Firmado PDF',
        'comprobante_pago' => 'Comprobante de Pago',
        'documento_institucional' => 'Documento Institucional',
        'otro' => 'Otro Documento',
    ],

    'program_types' => [
        'curso_corto' => 'Curso Corto',
        'programa' => 'Programa',
        'curso_tecnico' => 'Curso Técnico',
    ],

    'days_of_week' => [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ],
];
