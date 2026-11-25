# Manual de Usuario 01 — Super Administrador y Dirección (`/direccion`)

## 1. Roles y Alcance
- **Super Administrador (`super_admin`):** único perfil con permiso `system.code.manage` y autoridad para conceder o revocar el rol `super_admin`. Es el único usuario sembrado en el arranque limpio (`superadmin@cecap.or.cr`).
- **Dirección / Administrador (`direccion`):** administra la operación institucional, concede o revoca el rol Administrador operativo (`direccion`), autoriza excepciones de solvencia para graduación y gestiona catálogos, usuarios y métricas.

## 2. Pestañas del Módulo `/direccion`
1. **Tab 1 — Tablero y CSV BOM:** consulta indicadores clave (alumnas, matrículas confirmadas/pendientes, recaudación, cartera por cobrar, graduadas) y descarga archivos CSV con **UTF-8 BOM** listos para abrir con tildes y `₡` en Excel.
2. **Tab 2 — Usuarios y 8 Roles:**
   - **Catálogo visible de los 8 roles** con descripción de responsabilidades.
   - **Creación de usuarios:** todo usuario nuevo se crea con `must_change_password = true` (cambio obligatorio al iniciar sesión en `/cambiar-contrasena`). Soporta escenarios **multirrol** (ej. `profesora` + `alumna`).
   - **Accesos por persona (`uses_custom_permissions`):** permite personalizar permisos puntuales o restablecer con el botón **Aplicar permisos base**.
   - **Clonación segura de permisos:** copia roles, permisos personalizados y categorías de un usuario modelo exigiendo **nombre, correo, cédula/DIMEX y teléfono nuevos**.
   - **Clave temporal:** emite una contraseña temporal forzando cambio obligatorio en el próximo ingreso.
   - **Inactivación sin borrado físico:** desactiva cuentas preservando todo historial y auditoría.
3. **Tab 3 — Auditoría Inmutable:** filtra los registros append-only de `audit_logs` por módulo.
4. **Tab 4 — Picklists CR:** agrega o inactiva opciones de listas desplegables (escolaridad, tipos de identificación, métodos de pago, motivos de rectificación de nota, etc.) y consulta la división territorial de Costa Rica.
5. **Tab 5 — Configuración e Inscripción:**
   - Edita el plazo provisional de inscripción en línea (por defecto 7 días), el texto explicativo y el horario presencial de atención.
   - Edita el mensaje parametrizable de parámetro de género mostrado en `/inscripcion`.
   - Activa o desactiva la exigencia de solvencia financiera para graduación.
   - Activa o desactiva módulos globales (`institution_settings.enabled_modules`).
