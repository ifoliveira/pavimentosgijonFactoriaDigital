# Tickets TPV en Documentos

Las ventas nuevas de `POST /api/tpv/ventas` se guardan en `documento` (tipo
`ticket`), `documento_linea` y `documento_cobro`. No se crean `cestas`,
`detallecesta` ni `pagos`. Las ventas en efectivo conservan el movimiento
de caja en `efectivo`, dentro de la misma transacción.

## Compatibilidad de la API

Se mantienen URL, cabeceras de autenticación, cuerpo JSON y campos de respuesta.
Una venta nueva devuelve HTTP 201; un reintento devuelve HTTP 200 con
`idempotente: true`.

- `id`: para ventas nuevas identifica el Documento; para reintentos de ventas
  antiguas conserva el ID de la cesta original.
- `numeroTicket`: numeración de Documento, por ejemplo `T2026-0042`.
  Ya no es un nombre de archivo PDF. Un reintento antiguo conserva su número.
- `numeroTicketLocal`: se conserva tal como lo envía el TPV y se muestra en
  listado y detalle del Documento, junto a la caja.
- `estado`: se conserva el valor externo `2` para ventas finalizadas.
- `uuid`: se guarda normalizado a minúsculas, con índice único. Se consulta
  tanto en Documentos como en Cestas para no duplicar ventas anteriores al cambio.
- `precioAplicado`: PVP unitario con IVA incluido, antes del descuento porcentual.
  Se conserva ese PVP para evitar que recalcular desde un neto unitario redondeado
  altere los céntimos. `tipoIva` sigue siendo opcional, con 21 % por defecto.
- Se conserva la fecha/hora original en `tpvFecha`, además de la fecha de emisión.

El TPV puede seguir imprimiendo su número local antes de sincronizar. No necesita
esperar al número de Documento. La respuesta incluye el número del Documento
por si el TPV lo quiere conservar posteriormente.

Documento, líneas, cobro, efectivo y numeración se confirman o revierten juntos.
Los reenvíos simultáneos del mismo UUID recuperan el resultado existente. El
contador anual se comparte con los demás tickets de Documento.

## ERP e histórico

`/admin/productos/` redirige al catálogo, sin abrir cestas. Desaparece el enlace
«Nuevo» del menú de tickets antiguos. Se bloquean la creación de cestas, su
edición genérica, la adición de líneas y la emisión desde cestas abiertas.
La finalización antigua solo admite cobrar el pendiente de tickets históricos
ya emitidos; no admite una venta nueva.

Se mantienen la consulta y reimpresión histórica. Los documentos nuevos no
requieren cliente ni proyecto y se consultan en Documentos. No se renumeran ni
migran los tickets antiguos.

## Despliegue

Aplicar exclusivamente la migración de este cambio sobre una base que ya tenga
el esquema existente:

```sh
php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261018120000' --up --no-interaction
```

No es necesario ejecutar las migraciones pendientes ajenas a este cambio.
La migración añade campos de trazabilidad TPV y precios con IVA, sin modificar
las ventas antiguas. Aplicarla antes de activar el código nuevo.

## Verificación

Las pruebas MySQL son optativas y crean/eliminan bases aisladas con prefijo
`codex_tpv_test_`; nunca escriben ventas en la base de la aplicación:

```sh
TPV_DATABASE_TESTS=1 php vendor/bin/phpunit tests/Integration/Venta/VentaDocumentoServiceTest.php
```

Cubren persistencia, reintentos antiguos y nuevos, numeración, efectivo, rechazo
de totales inconsistentes, reversión completa y concurrencia real con `pcntl`.
El usuario MySQL de pruebas necesita permiso para crear y eliminar esas bases.

Las rectificaciones y la confirmación de reembolsos se documentan en [tpv-rectificativas.md](tpv-rectificativas.md).
