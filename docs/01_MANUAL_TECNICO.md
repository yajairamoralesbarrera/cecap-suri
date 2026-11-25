# Manual Técnico v3.0 — Plataforma PWA CeCap (SURÍ Proyecto Educativo, Costa Rica)

## 1. Arquitectura General
La plataforma utiliza **Laravel 13** + **Livewire 4 (`csp_safe = true`)** + **Tailwind CSS v4** sobre **PostgreSQL 16** con extensiones `pgcrypto` y `btree_gist`.

- **Capa de Dominio (`app/Services/`):** toda regla crítica de negocio vive en servicios desacoplados e inyectables:
  - `AuditService`: escritura inmutable en `audit_logs`.
  - `AttachmentService`: almacenamiento privado en `storage/app/private/attachments`, validación estricta MIME (`PDF/JPG/PNG/WebP`, máx. 10 MB), checksum SHA-256 y versionado sin borrado físico.
  - `ScheduleConflictService`: respaldo aplicativo de los constraints `EXCLUDE USING gist` para aulas, profesoras y choques de horario de alumnas.
  - `EnrollmentService`: flujo provisional en línea (7 días, género femenino obligatorio, correo obligatorio, estado `documentacion_pendiente`) y matrícula presencial con bloqueo documental y excepciones auditadas.
  - `FinanceService`: generación de planes de cobro, becas (0–100% o monto fijo preservando `gross_amount`), recibos `REC-YYYY-NNNNN` con abonos parciales/múltiples y anulación exclusiva por reverso en `financial_ledger_entries`.
  - `GraduationService`: evaluación automática de elegibilidad, excepción de solvencia exclusiva de Dirección/SuperAdmin, verificación de unicidad Tomo/Folio/Asiento y generación QR SVG.
  - `StaffPayrollService`: separación legal de Costa Rica entre Planilla Laboral (CCSS, BP, Renta, provisiones Aguinaldo/Vacaciones) y Servicios Profesionales (Clave numérica Hacienda de 50 dígitos + IVA).
  - `GovernanceService`: control de concesión/revocación de roles críticos (`super_admin`, `direccion`), aplicación de permisos base, clonación segura de permisos con datos personales nuevos y emisión de claves temporales.

## 2. Integridad de Base de Datos (PostgreSQL 16)
### 2.1 Política Estricta "Sin Borrado Físico"
1. Todas las claves foráneas usan `ON DELETE RESTRICT`.
2. Todos los modelos Eloquent usan `App\Models\Concerns\PreventsPhysicalDeletion` (bloquea `delete()` y `forceDelete()`).
3. La migración `2026_11_25_170600_create_no_physical_delete_triggers.php` instala:
   - `trg_no_delete_*` (`BEFORE DELETE`) en las 34 tablas operativas y de catálogos.
   - `trg_append_only_*` (`BEFORE UPDATE OR DELETE`) en `audit_logs`, `financial_ledger_entries` y `student_lifecycle_events`.

### 2.2 Bloqueo Anti-Choque con `EXCLUDE USING gist`
La tabla `edition_schedules` define columnas generadas `STORED`:
- `time_slot int4range GENERATED ALWAYS AS (int4range(start_minute, end_minute)) STORED`
- `date_span daterange GENERATED ALWAYS AS (daterange(start_date, end_date, '[]')) STORED`

Y dos restricciones GiST nativas:
- `edition_schedules_no_classroom_overlap`
- `edition_schedules_no_teacher_overlap`

## 3. Seguridad y Livewire 4 CSP-Safe
- `config/livewire.php` define `'csp_safe' => true`.
- Ninguna plantilla Blade utiliza `$set(...)` inline ni manejadores `onclick` inline. Toda interacción de cliente (sidebar colapsable, instalación PWA, visor PDF modal) se maneja por delegación de eventos en `resources/js/app.js`.
- Los adjuntos privados se sirven únicamente mediante `GET /adjuntos/{uuid}` (`AttachmentController`) con validación de permisos/propiedad, cabecera `X-Content-Type-Options: nosniff` y registro en `audit_logs`.
- Las planillas públicas de preinscripción utilizan `URL::temporarySignedRoute(..., now()->addDays(7))` con `hasValidSignatureWhileIgnoring(['vista', 'descargar'])`.

## 4. PWA Universal y Visor PDF Seguro
- `/manifest.webmanifest` se genera dinámicamente desde `institution_settings`.
- `/sw.js` implementa bypass nativo para solicitudes PDF (`Accept: application/pdf`, `/pdf/`, `/planilla/`, `.pdf`, `?vista=pdf`) y excluye estrictamente rutas transaccionales (`/livewire`, `/login`, `/admisiones`, `/finanzas`, `/personal`, `/direccion`, `/adjuntos`, etc.).
- El visor modal PDF (`#cecap-pdf-modal`) ofrece vista previa instantánea mediante `?vista=html`, modo `Blob` (`URL.createObjectURL`), impresión y descarga directa.

## 5. Pruebas Automatizadas
- **Pest 4:** `phpunit.xml` apunta a la BD aislada `cecap_testing` (nunca toca `cecap`). Cubre no borrado físico, append-only, 8 roles, `must_change_password`, recuperación dual, GiST anti-choque, admisiones/excepciones, becas, recibos/reversos, graduación QR, personal CR, PWA y CSV BOM.
- **Playwright E2E:** `playwright.config.js` valida desktop (`1440x900`), tablet (`820x1180`) y mobile (`390x844`), ergonomía táctil (`>=44px`, inputs `>=16px`), colapso limpio de sidebar, barra inferior móvil y navegación por los 11 módulos.
