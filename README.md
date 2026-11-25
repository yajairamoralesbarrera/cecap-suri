# Plataforma PWA CeCap — SURÍ Proyecto Educativo (Costa Rica) v3.0

Sistema integral de gestión educativa, administrativa, financiera, documental y de talento humano para **CeCap (SURÍ Proyecto Educativo, Costa Rica)**, construido como Aplicación Web Progresiva (PWA) universal compatible con Windows, macOS, Linux, iOS/iPadOS y Android.

## Stack Tecnológico
- **Backend:** PHP 8.3 / 8.4, Laravel 13, Livewire 4 (`csp_safe = true`, sin `$set` inline), Spatie Laravel Permission
- **Base de Datos:** PostgreSQL 15+ / 16 (`pgcrypto`, `btree_gist`, `ON DELETE RESTRICT`, triggers `BEFORE DELETE` y append-only)
- **Documentos y QR:** DomPDF (`barryvdh/laravel-dompdf`), `bacon/bacon-qr-code`, visor PDF modal seguro (`Blob` / `?vista=html`)
- **Frontend y PWA:** Tailwind CSS v4 (`@tailwindcss/vite`), Vite 8, `/manifest.webmanifest` dinámico, `/sw.js` con bypass nativo para PDFs y exclusión transaccional
- **Pruebas:** Pest 4 (sobre BD aislada `cecap_testing`) + Playwright E2E (escritorio, tableta y móvil)

## Principios de Integridad y Seguridad v3.0
- **Política estricta "Sin Borrado Físico":** FKs `ON DELETE RESTRICT`, trait `PreventsPhysicalDeletion` y triggers PostgreSQL `BEFORE DELETE` en tablas operativas y `BEFORE UPDATE OR DELETE` en tablas append-only (`audit_logs`, `financial_ledger_entries`, `student_lifecycle_events`).
- **8 Roles de Gobernanza:** `super_admin` (único con `system.code.manage`), `direccion`, `secretaria`, `administracion_caja`, `coordinacion_academica`, `coordinador_categoria` (restringido por `user_academic_categories`), `profesora` y `alumna` (auto-aprovisionada al registrarse).
- **Seguridad de Contraseñas:** todo usuario nuevo inicia con `must_change_password = true` (cambio obligatorio en `/cambiar-contrasena`); emisión de clave temporal por Admin; recuperación dual en `/recuperar-contrasena` (enlace por correo + validación de datos personales con `RateLimiter` y auditoría).
- **Arranque Limpio:** `DatabaseSeeder` únicamente siembra catálogos CR, configuración institucional, permisos/roles y el usuario único `superadmin@cecap.or.cr` (`Y4y4&K1k3`), con 0 datos ficticios. `DemoDataSeeder` está separado exclusivamente para pruebas o demostraciones.

## Módulos Principales
| Ruta | Módulo |
|---|---|
| `/inscripcion` | Preinscripción pública en línea provisional (plazo 7 días, género femenino obligatorio con mensaje parametrizable, correo obligatorio, checklist por curso, adjuntos privados, Planilla PDF firmada) |
| `/verificar/{token}` | Verificación pública QR de diplomas oficiales |
| `/panel` | Panel gerencial / operativo y Portal de Alumnas (soporte multirrol) |
| `/academico` | Categorías, Cursos Cortos/Programas/Técnicos (materias obligatorias en Técnicos), Ediciones con autollenado de capacidad, Horarios anti-choque PostgreSQL `EXCLUDE USING gist` |
| `/admisiones` | Cola provisional, matrícula presencial con bloqueo documental y excepciones auditadas, edición controlada, adjuntos privados SHA-256 y Planilla PDF |
| `/seguimiento` | Asistencia móvil versionada, calificaciones con rectificación por motivo estandarizado y Habilidades Blandas permanentes con Constancia PDF |
| `/finanzas` | Planes de cobro, becas (0-100% o monto fijo sin alterar monto bruto), pagadores, caja con abonos parciales/múltiples (`REC-YYYY-NNNNN` PDF), reversos auditados y morosidad |
| `/graduacion` | Elegibilidad automática, solvencia configurable con excepción exclusiva de Dirección, Actas (Tomo/Folio/Asiento únicos), plantillas gráficas y Diplomas QR PDF |
| `/personal` | Atestados profesionales obligatorios para Profesoras, separación legal CR Planilla Laboral (CCSS/BP/Renta/Aguinaldo/Vacaciones) vs Servicios Profesionales (Factura Hacienda 50 dígitos + IVA), contratos editables/PDF y comprobantes PDF |
| `/actividades` | Talleres institucionales, inscripción y control de asistencia |
| `/documentos` | Repositorio documental versionado con almacenamiento privado y SHA-256 |
| `/consultas` | Ventanilla Única de Consultas Rápidas 360° |
| `/direccion` | Tablero KPIs, CSV UTF-8 BOM, Usuarios/8 Roles/Clonación segura, Auditoría, Picklists CR y Tab 5 Configuración Institucional y Módulos Globales |

## Instalación Rápida
```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=0.0.0.0 --port=8000
```

### Datos de Demostración (Opcional — solo pruebas)
```bash
php artisan db:seed --class=DemoDataSeeder
```

### Ejecución de Pruebas
```bash
# Suite Pest 4 (usa BD aislada cecap_testing configurada en phpunit.xml)
./vendor/bin/pest

# Suite Playwright E2E (Desktop, Tablet y Mobile)
npx playwright test
```

## Documentación Completa v3.0
- [01 — Manual Técnico](docs/01_MANUAL_TECNICO.md)
- [02 — Guía de Despliegue y Mantenimiento](docs/02_GUIA_DESPLIEGUE_Y_MANTENIMIENTO.md)
- Manuales de Usuario:
  1. [Super Administrador y Dirección](docs/manuales_usuario/01_MANUAL_SUPERADMIN_Y_DIRECCION.md)
  2. [Secretaría y Admisiones](docs/manuales_usuario/02_MANUAL_SECRETARIA_Y_ADMISIONES.md)
  3. [Administración y Caja](docs/manuales_usuario/03_MANUAL_ADMINISTRACION_Y_CAJA.md)
  4. [Coordinación Académica y de Categoría](docs/manuales_usuario/04_MANUAL_COORDINACION_ACADEMICA_Y_CATEGORIA.md)
  5. [Profesoras y Personal CR](docs/manuales_usuario/05_MANUAL_PROFESORAS_Y_PERSONAL.md)
  6. [Alumnas e Inscripción en Línea](docs/manuales_usuario/06_MANUAL_ALUMNAS_E_INSCRIPCION_EN_LINEA.md)
