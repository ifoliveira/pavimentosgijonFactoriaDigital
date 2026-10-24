# Rectificación total de tickets TPV

Este flujo solo admite ventas que ya existan como `Documento` de tipo `ticket`
con UUID del TPV. No admite Cestas antiguas, presupuestos, facturas ni rectificar
otra rectificativa. La caja enviada debe coincidir con la venta y con la API key.

El ticket original conserva sus importes, líneas, cobro y numeración. Se crea un
Documento `ticket_rectificativo`, con serie anual `RT2026`, vínculo al original,
motivo y las cantidades, bases e impuestos compensados exactamente. La primera
versión solo permite una rectificación total por ticket.

## 1. Emitir la rectificativa

`POST /api/tpv/ventas/{uuidVentaOriginal}/rectificaciones`

Cabeceras: `Content-Type: application/json` y `X-TPV-API-Key`, igual que en ventas.

```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440010",
  "fecha": "2026-10-18T13:00:00+02:00",
  "caja": "CAJA-01",
  "tipo": "total",
  "motivo": "Devolución completa de la compra",
  "numeroTicketLocal": "DEV-000123"
}
```

`numeroTicketLocal` es opcional. Los restantes campos son obligatorios. No se
admiten líneas ni importes: el servidor utiliza los del ticket original. La fecha
debe ser ISO 8601 con zona horaria y no anterior a la fecha del original.

La respuesta usa HTTP 201 al crear y HTTP 200 al repetir la misma operación:

```json
{
  "success": true,
  "idempotente": false,
  "id": 123,
  "uuid": "550e8400-e29b-41d4-a716-446655440010",
  "numeroTicket": "RT2026-0001",
  "numeroTicketLocal": "DEV-000123",
  "tipo": "total",
  "motivo": "Devolución completa de la compra",
  "total": -20,
  "ticketOriginal": {
    "id": 122,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "numeroTicket": "T2026-0042",
    "fecha": "2026-10-18"
  },
  "estadoReembolso": "pendiente",
  "uuidReembolso": null
}
```

La rectificativa por sí sola no crea un movimiento de devolución de dinero.

## 2. Confirmar la devolución realizada

Cuando el TPV haya entregado el efectivo o confirmado la devolución del datáfono:

`POST /api/tpv/ventas/{uuidVentaOriginal}/rectificaciones/{uuidRectificativa}/reembolso`

```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440020",
  "fecha": "2026-10-18T13:15:00+02:00",
  "caja": "CAJA-01",
  "formaPago": "Tarjeta",
  "referencia": "OPERACION-DATAFONO-123"
}
```

El reembolso tiene su propio UUID. `referencia` es opcional; el resto es
obligatorio. Se admiten `Efectivo` y `Tarjeta`, sin distinguir mayúsculas.
Se registra la devolución íntegra mediante un `DocumentoCobro` negativo en la
rectificativa. El cobro original se conserva. En efectivo también se registra
la salida de caja; en tarjeta este endpoint **no ordena ninguna operación al
datáfono ni al banco**, solo registra la confirmación del TPV.

Para tarjeta, la confirmación crea además un Forecast negativo pendiente, con la
fecha del reembolso, categoría `Ventas`, número de rectificativa y referencia de
la operación. Se concilia como salida cuando llegue el cargo bancario. No se
duplica al reintentar ni se crea para efectivo o importes cero. La categoría
`Ventas` debe existir; si falta, devuelve `CONFIGURACION_INCOMPLETA` (409) y se
revierte toda la confirmación. Este cambio se aplica a nuevas confirmaciones;
los reembolsos ya registrados no generan un Forecast retroactivamente.

Devuelve la misma estructura anterior con `estadoReembolso: "realizado"` y
`uuidReembolso`. HTTP 201 para la primera confirmación y 200 para su reintento.

## Reintentos y errores

El TPV debe conservar el UUID y los datos originales de cada operación en su cola
de sincronización. No debe generar otro UUID ni actualizar `fecha` al reintentar.
Un reintento con el mismo UUID pero datos diferentes devuelve un conflicto.

| HTTP | Código | Actuación del TPV |
|---|---|---|
| 400 | `DATOS_INVALIDOS` | Corregir la petición; no se admiten rectificaciones parciales. |
| 401 | Error de autenticación | Comprobar la API key. |
| 403 | `CAJA_NO_AUTORIZADA` o error de API key/caja | Usar la caja y credenciales correspondientes. |
| 409 | `VENTA_NO_SINCRONIZADA` | Sincronizar primero la venta original y reintentar. |
| 409 | `TICKET_NO_ADMITIDO` | El documento no es una venta TPV admitida. |
| 409 | `TICKET_YA_RECTIFICADO` | No crear otra rectificación; recuperar la operación original del TPV. |
| 409 | `UUID_REUTILIZADO` | El UUID pertenece a otra operación o a otros datos. |
| 404 | `RECTIFICATIVA_NO_ENCONTRADA` | Sincronizar la rectificativa correcta antes del reembolso. |
| 409 | `REEMBOLSO_YA_REGISTRADO` | Ya existe otra confirmación; no devolver dinero otra vez. |
| 503 | `REINTENTAR_OPERACION` | Reintentar con el mismo UUID y los mismos datos. |

Los errores de negocio incluyen `success: false`, `error` y `codigo`. Los errores
de autenticación conservan el formato anterior (`success` y `error`). Ante una
interrupción de red o un error temporal del servidor, reenviar la misma operación.

Se serializan las peticiones por ticket y se usan índices únicos para los UUID.
Documento, numeración y, en su caso, reembolso y caja se confirman o revierten
juntos. La consulta del ERP enlaza original y rectificativa. El PDF identifica
el original, su fecha y el motivo. Los formularios genéricos no pueden modificar
ni borrar estos tickets, líneas o cobros; se permite vincular el cobro al banco
para conciliación. El resto de documentos mantiene su comportamiento anterior.

## Migración y pruebas

Requiere el cambio previo de ventas TPV a Documentos. Aplicar solo esta migración:

```sh
php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261018140000' --up --no-interaction
```

Pruebas HTTP reales con bases MySQL temporales, sin ventas en la base de la aplicación:

```sh
TPV_DATABASE_TESTS=1 php vendor/bin/phpunit tests/Integration/Venta/ApiTpvRectificacionTest.php
```

Requieren permiso MySQL para crear/eliminar bases `codex_tpv_test_*` y `pcntl`
para las pruebas de concurrencia. Cubren rectificación, reembolso, autenticación,
exclusión del circuito antiguo, reintentos, conflictos, concurrencia, conservación
del original, bloqueo de modificaciones y reversión de numeración ante fallos.
