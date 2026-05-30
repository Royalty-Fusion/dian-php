# Validación

El SDK trae dos validadores complementarios:

| Validador | Lo que cubre |
|---|---|
| `BusinessRuleValidator` | Reglas de negocio que XSD no capta (sumas, DV, rangos de resolución, códigos en catálogo). |
| `XsdValidator` | Validación contra los XSDs oficiales de DIAN (`UBL-2.1.zip`). |

## Uso desde el facade

```php
$dian->validateBeforeSend(true);
$result = $dian->send($invoice);
if (!$result->isSuccess()) {
    // ErrorMessage contiene todas las violaciones separadas por " | "
}
```

## Uso directo

```php
use RoyaltyFusion\DianPhp\Validator\BusinessRuleValidator;

$result = (new BusinessRuleValidator())->validate($invoice);

if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        printf("[%s] %s %s\n", $error->code, $error->message, $error->xpath);
    }
}
```

## Códigos de error del BusinessRuleValidator

| Código | Significado |
|---|---|
| `DOC_001` | Documento sin líneas |
| `DOC_002` | Fecha de emisión en el futuro |
| `PARTY_001` | NIT del emisor faltante |
| `PARTY_002` | DV del NIT no coincide |
| `PARTY_003/004` | Tipo de documento no está en catálogo DIAN |
| `TAX_001` | Código de tributo desconocido |
| `TAX_002` | `amount ≠ base × percent / 100` (tolerancia 1 COP) |
| `TOTAL_002` | `setTotal()` ≠ `PayableAmount` calculado |
| `CURRENCY_001` | Moneda fuera de ISO 4217 |
| `RES_001` | Número fuera del rango de la resolución |
| `RES_002` | Fecha fuera de la vigencia de la resolución |
| `SOFT_001` | Software DIAN (id + pin) faltante |

## Validar contra los XSDs

```bash
# 1. Descarga UBL-2.1.zip desde el portal DIAN
unzip UBL-2.1.zip -d resources/xsd/
```

```php
use RoyaltyFusion\DianPhp\Validator\XsdValidator;

$xml = $dian->getSignedXml($invoice);
$validation = (new XsdValidator('resources/xsd/UBLInvoice-2.1.xsd'))->validate($xml);
```

Si el archivo XSD no existe el validador devuelve un `warning` (no error), para
que CI pueda saltarse esta validación cuando no hay XSDs vendor-eados.
