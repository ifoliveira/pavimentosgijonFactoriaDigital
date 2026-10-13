# Telegram: presupuestos desde foto

## Configuracion

1. Configura el token del bot en `.env.local` o en el entorno del servidor:

```dotenv
TELEGRAM_PRESUPUESTOS_BOT_TOKEN=<token-del-bot-de-presupuestos>
```

No guardes tokens reales en Git.

El bot de presupuestos usa variables propias. No reutiliza ni modifica `TELEGRAM_BOT_TOKEN`, que queda reservado para la integracion Telegram anterior del ERP.

2. Envia `/start` al bot desde tu Telegram.

3. Obtén tu identificador sin consumir mensajes:

```bash
php bin/console app:telegram:presupuestos:pending-users
```

4. Configura usuarios autorizados y URL publica HTTPS del ERP:

```dotenv
TELEGRAM_PRESUPUESTOS_ALLOWED_USER_IDS=123456789
ERP_PUBLIC_BASE_URL=https://erp.example.com
```

`TELEGRAM_PRESUPUESTOS_ALLOWED_USER_IDS` acepta varios IDs separados por comas. Si esta vacio, no se procesan fotografias ni se crean registros.

5. Ejecuta migraciones:

```bash
php bin/console doctrine:migrations:migrate
```

6. Comprueba que el bot no tenga webhook activo y arranca el consumidor:

```bash
php bin/console app:telegram:presupuestos:consume
```

Para probar un lote y terminar:

```bash
php bin/console app:telegram:presupuestos:consume --once
```

7. Envia una unica imagen JPG o PNG de la ficha. Puede ser foto normal o imagen enviada como documento.

## Operacion

El consumidor usa `getUpdates` con long polling. Debe permanecer ejecutandose para recibir mensajes. En produccion mantenlo activo con el mecanismo que uses habitualmente, por ejemplo systemd, Supervisor o el gestor de procesos del hosting. Este cambio no instala ni configura automaticamente esos servicios.

No uses webhook en esta fase. Si el bot tiene webhook activo, el comando mostrara un error y no lo eliminara.

## Comportamiento

- Solo acepta chats privados de usuarios en `TELEGRAM_PRESUPUESTOS_ALLOWED_USER_IDS`.
- Rechaza albumes, PDF, formatos no JPG/PNG y archivos de mas de 10 MB.
- Una imagen equivale a una solicitud.
- No usa pies de foto como instrucciones.
- Si faltan datos de cliente, conserva foto y extraccion y devuelve un enlace autenticado del ERP para completar la misma recepcion.
- Si BudgetFlow necesita revision o falla despues de crear el documento, conserva proyecto y presupuesto y devuelve enlace de revision.
- No envia PDF por Telegram en esta version.
