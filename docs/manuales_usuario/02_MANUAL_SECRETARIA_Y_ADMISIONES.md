# Manual de Usuario 02 — Secretaría y Admisiones (`/admisiones`, `/consultas`, `/documentos`)

## 1. Cola de Preinscripciones en Línea (`/admisiones`)
- Las solicitudes enviadas desde `/inscripcion` ingresan en estado **Preinscrita** (si adjuntaron todo el checklist del curso) o **Documentación Pendiente** (si faltan requisitos), con un plazo provisional parametrizable (7 días por defecto).
- Haga clic en **Confirmar** para verificar el checklist documental y formalizar la matrícula presencial.

## 2. Reglas de Matrícula Presencial y Excepciones Auditadas
- El campo **Correo electrónico es obligatorio** tanto al crear como al editar expedientes.
- El campo **Género** aparece precargado en **Femenino**.
- Use el botón **Autollenar** junto a Cédula/DIMEX para recuperar automáticamente los datos de una alumna ya registrada.
- **Bloqueo documental:** si falta algún documento requerido por el curso (ej. Atestados Académicos en Cursos Técnicos), el sistema bloquea la confirmación presencial.
- **Excepción documental:** únicamente si cuenta con permiso `admisiones.exceptions.authorize`, puede marcar la casilla de excepción indicando obligatoriamente **motivo** y **fecha compromiso de entrega** (queda registrado en auditoría).
- Al confirmar la matrícula, se genera automáticamente el plan de cobro (matrícula y mensualidades) y queda disponible la **Planilla de Inscripción PDF** desde el visor modal seguro.

## 3. Edición Controlada y Adjuntos Privados
- Para modificar un expediente existente, ingrese al directorio y pulse **Edición Controlada**; el sistema exige un **motivo de modificación** para registrar el cambio en `audit_logs`.
- Los adjuntos (PDF/JPG/PNG/WebP, máx. 10 MB) se almacenan de forma privada con huella SHA-256 y versionado automático. Nunca se eliminan físicamente: si un documento queda obsoleto, utilice **Archivar**.

## 4. Ventanilla Única 360° (`/consultas`) y Repositorio (`/documentos`)
- En `/consultas` puede buscar por nombre, cédula/DIMEX, correo, código `MAT-...` o recibo `REC-...` y visualizar en una sola pantalla matrículas, notas, asistencia, saldo pendiente, planilla PDF, constancia de habilidades blandas, diplomas y bitácora del ciclo de vida.
- En `/documentos` cargue reglamentos, circulares o formularios con control de versiones (`v1`, `v2`, ...) y visibilidad configurable (`publico`, `alumnas`, `interno`, `direccion`).
