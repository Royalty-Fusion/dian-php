# Consecutivos y resoluciones de numeración

La DIAN rechaza números fuera del rango autorizado o de una resolución
vencida, y un número enviado no se puede reutilizar aunque haya sido
rechazado. `NumberingManager` entrega los consecutivos de forma atómica y
avisa antes de que el rango se agote o la resolución venza.

```php
use RoyaltyFusion\DianPhp\Numbering\{NumberingManager, PdoNumberingStore};

$store = new PdoNumberingStore($pdo);      // MySQL/MariaDB, PostgreSQL, SQLite, SQL Server
$store->createTable();                     // una vez (tabla dian_numbering)

$numbering = new NumberingManager($store, minRemaining: 100, minDays: 30);

$invoice->setNumero($numbering->next($resolution, $invoice->getFecha()));
```

* `next()` lanza `NumberingException` si el rango se agotó o la fecha está
  fuera de la vigencia de la resolución.
* Cada resolución (número, prefijo y rango) tiene su propio contador; la
  factura, las notas, el POS y el documento soporte usan resoluciones o
  prefijos distintos.
* Migración desde otro software: `$numbering->setLastUsed($resolution, 1234)`
  (el siguiente será 1235).
* Concurrencia: el incremento es un `UPDATE` condicional dentro de una
  transacción; dos procesos nunca obtienen el mismo número.
* Pruebas o scripts: `InMemoryNumberingStore`. Para otro almacenamiento
  (Redis, API) implemente `NumberingStore`.

## Alertas

```php
$numbering->status($resolution);
// ['prefix' => 'FE', 'from' => 1, 'to' => 5000, 'last' => 4930, 'remaining' => 70,
//  'used_percent' => 98.6, 'expires' => '2026-12-31', 'days_left' => 20]

foreach ($numbering->alerts($resolution) as $alerta) {
    $logger->warning($alerta);   // "Resolución … quedan 70 números (98.6% usado)."
}
```

Programe la revisión a diario junto con `dian-doctor`, y solicite la nueva
resolución en el portal de la DIAN con tiempo.
