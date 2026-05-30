# Instalación

## Requisitos

| Componente | Versión |
|---|---|
| PHP | 8.1 o superior (recomendado 8.3+) |
| Extensiones | `dom`, `libxml`, `openssl`, `zip`, `json` |
| Composer | 2.x |

## Instalación vía Composer

```bash
composer require royaltyfusion/dian-php
```

## Certificado digital DIAN

Necesitas un certificado X.509 emitido por una autoridad acreditada en Colombia
(Andes SCD, Certicámara, GSE) en formato PKCS#12 (`.p12` / `.pfx`).

Guárdalo fuera del repositorio. Por ejemplo:

```
$ mkdir -p /var/secrets/dian
$ chmod 700 /var/secrets/dian
$ cp empresa.p12 /var/secrets/dian/
```

## Variables de entorno (recomendado)

Configura los secretos vía `.env` o el secret manager de tu plataforma:

```
DIAN_ENV=habilitacion
DIAN_CERT_PATH=/var/secrets/dian/empresa.p12
DIAN_CERT_PASSWORD=••••••••
DIAN_SOFT_ID=00000000-0000-0000-0000-000000000000
DIAN_SOFT_PIN=00000
DIAN_SOFT_PROVIDER_NIT=900123456
```

> El SDK **nunca** logea el contenido del certificado ni el PIN. Si vas a versionar
> ejemplos públicos, usa el set de pruebas oficial de DIAN para no exponer credenciales.

## Set de pruebas (Habilitación)

DIAN entrega un `testSetId` por cada empresa que solicita habilitación. Asígnalo en
el documento antes de enviarlo:

```php
$invoice->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d');
```

El SDK detecta automáticamente el set y usa `SendTestSetAsync`. Cuando ya estés en
producción, simplemente omite el `testSetId` y se usará `SendBillAsync`.

## Comprobar la instalación

```php
<?php
require 'vendor/autoload.php';

use RoyaltyFusion\DianPhp\Model\NitDvCalculator;

echo "DV de la DIAN (800197268): " . NitDvCalculator::compute('800197268') . "\n";
// → DV de la DIAN (800197268): 4
```

Si el script imprime `4`, el autoloader y los namespaces están correctos.
