# Manual de Usuario 04 — Coordinación Académica y de Categoría (`/academico`, `/graduacion`)

## 1. Diferencia de Alcance
- **Coordinación Académica (`coordinacion_academica`):** administra todas las categorías, cursos, ediciones, horarios, seguimiento y graduaciones de la institución.
- **Coordinador de Área / Categoría (`coordinador_categoria`):** opera únicamente sobre las categorías asignadas en `user_academic_categories`; cualquier intento de crear cursos, ediciones u horarios fuera de su área asignada es bloqueado automáticamente.

## 2. Catálogo Académico (`/academico`)
- **Categorías:** organiza la oferta formativa (ej. Gastronomía, Tecnología y Administración).
- **Cursos Cortos, Programas y Cursos Técnicos:**
  - Defina nivel académico previo requerido (`required_education_level`) y capacidad máxima por defecto (`default_max_capacity`).
  - **Checklist documental:** por defecto los **Atestados Académicos** se exigen únicamente en **Cursos Técnicos** (Cursos Cortos y Programas requieren solo Cédula/DIMEX), pero puede ajustarse por curso.
  - **Materias obligatorias:** al crear un **Curso Técnico**, el sistema exige registrar al menos una materia del plan de estudios.
- **Ediciones:** al seleccionar el curso base, se autollenan capacidad máxima, matrícula, mensualidad y número de cuotas.
- **Horarios Anti-Choque:** protegidos mediante restricciones nativas PostgreSQL `EXCLUDE USING gist` para evitar choques de **aula** y de **profesora**, además de validar choques de horario de las alumnas al matricular.

## 3. Graduación, Actas y Diplomas QR (`/graduacion`)
1. Seleccione una matrícula candidata: el sistema evalúa automáticamente **nota mínima**, **asistencia mínima** y **solvencia financiera** (configurable en `/direccion` Tab 5).
2. Si la alumna mantiene saldo pendiente y la solvencia está activa, únicamente **Dirección** o **Super Administrador** puede autorizar una excepción con motivo auditado.
3. Ingrese **Tomo, Folio y Asiento** (combinación única irrepetible) y fecha de graduación para emitir el **Acta de Grado PDF** y el **Diploma PDF con código QR** verificable públicamente en `/verificar/{token}`.
4. En la pestaña **Plantillas Gráficas** puede crear diseños personalizados de diploma (colores, texto con placeholders y firmantes).
