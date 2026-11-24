# Bolsa de gastos futuros

La pantalla `/admin/flujocaja` muestra los vencimientos registrados, las partes de facturas recibidas que aún no tienen vencimiento y la previsión global de compras pendientes de factura. Los costes previstos por línea de obra dejan de sumarse a la tesorería. Se conservan para gestionar cada proyecto.

## Puesta en marcha

La fecha de inicio queda **vacía**. En **Configurar bolsa de gastos** (`/admin/flujocaja/bolsa`), el usuario establece cuándo empezar. La migración no borra ni reconstruye datos anteriores. No hay reinicio anual automático: el saldo continúa hasta que el usuario cambie el corte.

La configuración incluye IVA estimado, plazo de pago, plazo de obra para proyectos sin fecha fin prevista y nombres de proveedores excluidos del consumo. Por defecto se incluyen todas las facturas. Las exclusiones comparan nombres completos normalizando mayúsculas, acentos y espacios; no interpretan descripciones ni productos. Los vencimientos de proveedores excluidos siguen contando como pagos.

Cambiar el corte o las exclusiones recalcula todo el periodo. Una compra mixta no se reparte automáticamente entre obras y tienda. Los costes presupuestados pueden incluir existencias ya adquiridas o mano de obra propia: la bolsa es una estimación global corregible, no contabilidad de compras exacta.

## Cálculo automático

- Las líneas originales de los presupuestos aceptados, convertidos o aplicados, desde el corte hasta hoy, aportan su coste unitario por cantidad sin IVA. No se usan los estados mutables de `ProyectoGasto`.
- Las facturas del periodo consumen por su base sin IVA, independientemente de asignación, revisión de líneas o pago. La fecha de factura determina el periodo, no la fecha de subida.
- Cada documento participa una vez. El cálculo se reconstruye por fechas y no crea registros `Forecast` ni modifica gastos o asignaciones.
- El consumo utiliza las aportaciones anteriores por orden de entrada. Un exceso se muestra, pero nunca genera saldo negativo ni descuenta presupuestos aceptados después. Dentro de un mismo día se ordenan presupuestos, facturas y ajustes, con identificadores numéricos crecientes.
- Los ajustes globales suman o restan una cantidad, con fecha y motivo. Se aplican después de las facturas de su día; no establecen un saldo absoluto. Una reducción se limita a la reserva disponible.
- Los abonos se señalan sin reponer automáticamente reserva: sin referencia a la factura original no se puede saber si habrá una compra sustituta. Si queda una compra pendiente, puede reflejarse con un ajuste.
- Finalizar una obra no elimina su reserva. Asignar o pagar después una factura tampoco vuelve a consumirla.

Los importes se calculan en céntimos. El IVA estimado se aplica solo al saldo residual. Sus fechas parten del fin previsto de la obra más el plazo de pago; si falta ese fin, se usa aceptación más plazo de obra más plazo de pago. Las fechas vencidas se llevan a hoy. La parte posterior al año se informa aparte y no se carga al cierre anual actual.

Una factura con vencimientos incompletos conserva su diferencia como pago de fecha estimada. Se resta del total facturado lo representado por todos sus vencimientos pendientes o conciliados; el importe no reaparece al pagar. Al completar los vencimientos desaparece automáticamente la diferencia. El cálculo mantiene los estados de pago del sistema existente y no corrige conciliaciones bancarias erróneas ni importaciones duplicadas de una misma factura como documentos distintos.

## Instalación

La migración `DoctrineMigrations\Version20261115120000` crea únicamente `bolsa_gastos_configuracion` y `bolsa_gastos_ajuste`. Inserta una configuración sin fecha de inicio. Las aportaciones y consumos se derivan de los documentos originales; solo configuración y ajustes requieren nuevas tablas.

Si existen migraciones antiguas pendientes en la instalación, ejecutar únicamente esta versión, sin aplicar todo el histórico:

```bash
php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261115120000' --up --no-interaction --env=dev
```

## Verificación

```bash
vendor/bin/phpunit --filter 'Bolsa|FlujoCajaBolsa' tests
TPV_DATABASE_TESTS=1 vendor/bin/phpunit tests/Integration/Tesoreria/BolsaGastosDatabaseTest.php
```

La segunda prueba crea y elimina una base MySQL temporal usando la infraestructura de pruebas existente. No utiliza los datos de la aplicación.
