# Manual de Usuario 03 — Administración y Caja (`/finanzas`, `/personal`)

## 1. Caja y Emisión de Recibos `REC-YYYY-NNNNN` (`/finanzas`)
1. Seleccione la alumna en la pestaña **Caja y Abonos**; se cargarán automáticamente todos los cargos pendientes o parciales (matrícula y mensualidades).
2. Puede aplicar **abonos parciales** editando el monto en una fila o **abonos múltiples** cubriendo varios cargos en un solo recibo.
3. Indique el pagador/patrocinante (si paga un tercero), método de pago (Efectivo, SINPE Móvil, Transferencia, Tarjeta o Depósito) y número de referencia.
4. Al confirmar se emite el recibo consecutivo `REC-YYYY-NNNNN` visualizable e imprimible en el visor PDF modal seguro.

## 2. Becas (Sin Alterar el Monto Bruto)
- En la pestaña **Becas** seleccione la matrícula e indique si la beca es por **Porcentaje (0–100%)** o **Monto Fijo (CRC)** y, opcionalmente, el patrocinante.
- El sistema descuenta el monto de beca del saldo neto **sin modificar jamás el monto bruto original (`gross_amount`)**, registrando el movimiento en el libro mayor append-only (`financial_ledger_entries`).

## 3. Anulación Exclusiva por Reverso Auditado
- Bajo la política **Sin Borrado Físico**, ningún recibo se elimina ni edita.
- Para anular un cobro erróneo, abra la pestaña **Recibos y Reversos**, pulse **Reversar** e ingrese el **motivo formal**. El sistema genera un recibo de reverso negativo, restaura el saldo pendiente de los cargos afectados y registra el asiento en el libro mayor y en auditoría.

## 4. Morosidad y Pagos de Personal CR
- La pestaña **Morosidad** concentra todos los cargos vencidos con fecha de vencimiento y teléfono de contacto.
- En `/personal` (pestaña **Pagos y Comprobantes CR**), registre los pagos diferenciando estrictamente:
  - **Planilla Laboral CR:** calcula automáticamente deducciones CCSS, Banco Popular, Renta y provisiones informativas de Aguinaldo y Vacaciones.
  - **Servicios Profesionales CR:** exige obligatoriamente la **clave numérica de Factura Electrónica del Ministerio de Hacienda (50 dígitos)** y suma el IVA correspondiente, sin cargas sociales.
