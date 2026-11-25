# Plan de Pruebas Manuales y de Aceptación (UAT) — Plataforma PWA CeCap SURÍ v3.0

Este plan permite validar de extremo a extremo todas las funcionalidades, controles de seguridad, reglas legales de Costa Rica y experiencia PWA de la plataforma.

## 0. Preparación del Entorno
- **Acceso inicial (arranque limpio):** `superadmin@cecap.or.cr` / `Y4y4&K1k3` (único usuario inicial, 0 datos ficticios).
- **Verificación inicial recomendada:** entrar a `/direccion` → **Tab 2. Usuarios y 8 Roles** y confirmar que solo aparece `Super Administrador CeCap` y el catálogo visible de los 8 roles.

---

## Bloque 1 — Seguridad, Contraseñas y Gobernanza (`/direccion`, `/cambiar-contrasena`, `/recuperar-contrasena`)
- [ ] **P1.1 Creación de categoría y usuario multirrol:** en `/direccion` → Tab 2, marcar `Profesora` + `Coordinador de Área / Categoría`. Si no hay categorías, usar **"+ Crear categoría"** (ej. `CAT-GASTRO` / *Gastronomía*) o crearla en `/academico` → Categorías; asignar la categoría y crear el usuario con clave temporal.
- [ ] **P1.2 Cambio obligatorio de contraseña (`must_change_password`):** cerrar sesión e ingresar con el nuevo usuario. Verificar que cualquier ruta redirige obligatoriamente a `/cambiar-contrasena` hasta definir una contraseña nueva.
- [ ] **P1.3 Alcance estricto de `coordinador_categoria`:** con el usuario anterior, entrar a `/academico` y comprobar que solo puede crear/ver cursos dentro de su categoría asignada (*Gastronomía*) y que otra categoría diferente queda bloqueada.
- [ ] **P1.4 Clonación segura de permisos:** en `/direccion` → Tab 2, clonar un usuario existente intentando repetir su correo (debe bloquear) y luego con correo/cédula/teléfono nuevos (debe crear usuario nuevo con `must_change_password = true`).
- [ ] **P1.5 Permisos por persona y base del cargo:** en **Accesos**, activar `uses_custom_permissions`, quitar un permiso puntual, guardar y verificar el bloqueo; luego pulsar **Aplicar permisos base** para restaurar.
- [ ] **P1.6 Control de roles críticos:** ingresar con un usuario `direccion` y comprobar que NO puede asignar ni revocar `super_admin`, ni ver la sección exclusiva `system.code.manage` en Tab 5.
- [ ] **P1.7 Recuperación dual (`/recuperar-contrasena`):**
  1. Probar opción 1 (**Enlace por correo**).
  2. Probar opción 2 (**Validación de datos personales**): ingresar cédula errónea (debe fallar y registrarse en `/direccion` → Tab 3 Auditoría); ingresar correo + cédula + teléfono correctos (debe llevar a `/restablecer-contrasena/{token}`).
- [ ] **P1.8 Módulos globales y parámetros (Tab 5):** en `/direccion` → Tab 5, editar el texto de inscripción provisional (7 días), el horario de atención y el mensaje de género; desactivar temporalmente `Talleres y Actividades`, comprobar que `/actividades` retorna `403` y volver a activarlo.

---

## Bloque 2 — Módulo Académico y Horarios Anti-Choque (`/academico`)
- [ ] **P2.1 Curso Corto vs Curso Técnico:**
  - Crear un **Curso Corto** (ej. *Panadería Artesanal*, capacidad `18`): verificar que el checklist por defecto marca únicamente `Cédula / DIMEX` y no exige materias.
  - Intentar crear un **Curso Técnico** sin materias (debe bloquear). Agregar al menos 2 materias (ej. *Contabilidad I* y *Legislación CR*) y verificar que el checklist por defecto incluye `Cédula / DIMEX` + `Atestados Académicos`.
- [ ] **P2.2 Autollenado en Ediciones:** en la pestaña **Ediciones**, seleccionar el curso creado y verificar que **Capacidad Máxima**, **Matrícula**, **Mensualidad** y **Cuotas** se autollenan automáticamente desde el curso.
- [ ] **P2.3 Bloqueo anti-choque PostgreSQL (`EXCLUDE USING gist`):** crear un Aula, asignar horario (ej. Martes `08:00–11:00`) a una edición e intentar asignar un horario superpuesto (Martes `09:00–12:00`) en la misma aula o con la misma profesora en otra edición: debe rechazarlo inmediatamente.

---

## Bloque 3 — Inscripción Pública en Línea y Admisiones (`/inscripcion`, `/admisiones`)
- [ ] **P3.1 Parámetro de género en `/inscripcion`:** cambiar Género a *Masculino* u *Otro* → debe mostrar la alerta roja con el mensaje parametrizable configurado en Tab 5 y deshabilitar el envío.
- [ ] **P3.2 Correo obligatorio y preinscripción incompleta:** seleccionar el Curso Técnico, dejar el correo vacío (debe rechazar), luego completarlo adjuntando solo la Cédula (sin atestados). Verificar que entra en estado **Documentación Pendiente** con vencimiento a 7 días y muestra los botones **Ver / Imprimir Planilla de Inscripción PDF** (modal seguro) y **Descargar Planilla PDF**.
- [ ] **P3.3 Bloqueo presencial y excepción auditada en `/admisiones`:** abrir la solicitud en la cola, pulsar **Confirmar** sin marcar Atestados ni excepción → debe bloquearse. Marcar **Autorizar excepción**, indicar motivo + fecha compromiso y confirmar → debe pasar a `CONFIRMADA`, generar el plan de cobro y quedar registrado en `/direccion` → Tab 3 Auditoría.
- [ ] **P3.4 Edición controlada y adjuntos privados:** en `/admisiones` → **Directorio**, pulsar **Edición Controlada**, cambiar el teléfono intentando guardar sin motivo (debe exigir motivo), guardar con motivo y verificar descarga protegida del adjunto y la acción **Archivar** (sin borrado físico).

---

## Bloque 4 — Seguimiento Académico (`/seguimiento`)
- [ ] **P4.1 Asistencia móvil versionada:** marcar asistencia en una fecha, guardar (`v1`), cambiar el estado de una alumna ingresando motivo y volver a guardar (`v2`), verificando que no se borra el historial previo.
- [ ] **P4.2 Calificaciones y rectificación auditada:** registrar una nota (ej. `78`), pulsar **Rectificar**, elegir un motivo estandarizado (ej. *Revisión de examen aprobada*), cambiar a `94` con justificación y verificar el incremento de versión (`v2`).
- [ ] **P4.3 Habilidades Blandas permanentes:** registrar una habilidad blanda y abrir **Constancia PDF** en el visor modal seguro.

---

## Bloque 5 — Finanzas, Becas y Caja (`/finanzas`)
- [ ] **P5.1 Beca sin alterar monto bruto:** aplicar una beca de `50%` (o monto fijo) a una matrícula y verificar en **Caja y Abonos** que la columna **Bruto** se mantiene intacta mientras **Beca** y **Saldo** reflejan el descuento.
- [ ] **P5.2 Abono parcial y múltiple + Recibo `REC-YYYY-NNNNN`:** aplicar un abono parcial a la matrícula y otro a la primera mensualidad en un solo pago; abrir **Recibo PDF** en el visor modal.
- [ ] **P5.3 Anulación exclusiva por reverso:** en **Recibos y Reversos**, pulsar **Reversar** con motivo formal; confirmar que el recibo original queda en historial como `REVERSADO`, se genera recibo negativo y se restituye el saldo pendiente en los cargos.
- [ ] **P5.4 Morosidad:** revisar la pestaña **Morosidad** para cargos con fecha vencida.

---

## Bloque 6 — Graduación, Actas y Diplomas QR (`/graduacion`, `/verificar/{token}`)
- [ ] **P6.1 Elegibilidad automática y solvencia:** seleccionar una matrícula aprobada con saldo pendiente. Con un rol distinto de Dirección (ej. Coordinación Académica) la excepción de solvencia no debe permitirse; con **Dirección** o **Super Admin**, autorizar la excepción con motivo (o cancelar el saldo primero).
- [ ] **P6.2 Emisión de Acta y Diploma QR:** ingresar **Tomo**, **Folio** y **Asiento** únicos, emitir y abrir **Acta PDF** y **Diploma QR PDF**. Intentar repetir el mismo Tomo/Folio/Asiento en otra graduación (debe bloquear por unicidad).
- [ ] **P6.3 Verificación pública QR:** pulsar **Verificar QR** (o abrir `/verificar/{token}` en ventana de incógnito) y comprobar el sello verde **CERTIFICADO AUTÉNTICO Y VERIFICADO**.

---

## Bloque 7 — Personal y Contratos Costa Rica (`/personal`)
- [ ] **P7.1 Atestados obligatorios para Profesoras:** intentar registrar una Profesora (`teacher` o `both`) sin adjuntar archivo de Atestados Profesionales → debe bloquearse. Adjuntar PDF/imagen y guardar.
- [ ] **P7.2 Planilla Laboral CR:** crear contrato bajo **Planilla Laboral**, registrar un pago y abrir el **Comprobante PDF** verificando deducciones CCSS (`10.67%`), Banco Popular (`1%`), Renta, Neto y provisiones informativas de Aguinaldo y Vacaciones.
- [ ] **P7.3 Servicios Profesionales CR (Hacienda 50 dígitos + IVA):** crear contrato bajo **Servicios Profesionales** (editable o PDF adjunto), intentar pagar con una clave menor a 50 dígitos (debe bloquear), ingresar 50 dígitos numéricos y verificar en el **Comprobante PDF** el cálculo de IVA sin deducciones CCSS.

---

## Bloque 8 — Talleres, Documentos, Consultas 360°, Portal Alumna y PWA
- [ ] **P8.1 Talleres (`/actividades`):** crear un taller, agregar una alumna y una participante externa, y alternar su asistencia.
- [ ] **P8.2 Repositorio Documental (`/documentos`):** subir documento (`v1`), cargar **Nueva Versión** (`v2`), descargar y probar **Archivar** sin borrado físico.
- [ ] **P8.3 Ventanilla Única 360° (`/consultas`):** buscar por cédula, código `MAT-...` o recibo `REC-...` y abrir la ficha 360° con Planilla PDF, Recibos PDF, Constancia Habilidades PDF, Diploma y bitácora del ciclo de vida.
- [ ] **P8.4 Registro de Alumna (`/registro`):** crear cuenta nueva de alumna y verificar ingreso automático al **Portal de Alumna** en `/panel`.
- [ ] **P8.5 Exportación CSV UTF-8 BOM:** en `/direccion` → Tab 1, descargar `Alumnas CSV` o `Pagos Personal CR CSV` y abrir en Excel verificando tildes y signo `₡`.
- [ ] **P8.6 PWA y Responsividad:**
  - En escritorio: colapsar/expandir el sidebar (debe quedar limpio en `w-20` sin textos cortados y con el botón **Instalar App PWA** visible).
  - En móvil (`390×844` o celular): comprobar barra inferior móvil, menú lateral móvil, inputs `>= 16px` y visor PDF modal (`Vista HTML` y `PDF Blob`).
