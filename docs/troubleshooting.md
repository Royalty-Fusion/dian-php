# Troubleshooting

## Errores más comunes que devuelve la DIAN

| Código | Descripción | Causa típica | Cómo arreglarlo |
|---|---|---|---|
| FAJ24 | El campo `cbc:UUID` no tiene la longitud correcta | CUFE/CUDE no es SHA-384 (96 hex chars) | Asegúrate de pasar `$envFlag` consistente entre `CufeGenerator` y la plantilla. |
| FAB01b | El `schemeID` del DV no coincide | El DV calculado vs el real difieren | Quita `setNitDv()` y deja que `NitDvCalculator` lo calcule. |
| FAD06b | `cbc:CompanyID` con esquema inválido | `tipoDocumento` no está en la tabla DIAN | Usa `TipoDocumentoIdentificacion::CC->code()`. |
| FAJ48 | `cbc:Percent` con formato incorrecto | El `Tax::setPercent()` recibió string en vez de float | Usa `19.0` no `'19'`. |
| FAK14 | El total `cbc:PayableAmount` no coincide | `DocumentCalculator` y `$invoice->setTotal()` difieren | Llama a `validateBeforeSend(true)` para detectarlo antes de enviar. |
| FAB14 | La fecha de la resolución está vencida | Resolución fuera del rango `dateFrom..dateTo` | Solicita una nueva resolución en el portal MUISCA. |

## Errores del SDK

### `Could not read the certificate. Check password or format.`

El `.p12` está corrupto o la contraseña es incorrecta. Pruébalo con OpenSSL:

```bash
openssl pkcs12 -info -in empresa.p12 -nokeys
```

### `XSD file not found at resources/xsd/...`

Los XSDs oficiales no están vendor-eados. Descarga el zip oficial de DIAN
(`UBL-2.1.zip`), descomprímelo en `resources/xsd/` y `XsdValidator` los usará
automáticamente.

### `HTTP Error: 500. SOAP Response: ...`

El endpoint de habilitación cae con cierta frecuencia. Reintenta con backoff
exponencial (próximamente como middleware del `SoapClient`).

## Cómo pedir ayuda

1. Captura el XML firmado (`$dian->getSignedXml($invoice)`).
2. Captura la respuesta SOAP completa.
3. Si es posible, captura un `tcpdump`/HAR del intercambio.
4. Abre un issue con la triada: doc → respuesta → log.

> Por seguridad: **nunca** publiques tu `.p12` ni tu PIN. Si la respuesta contiene
> datos de un cliente real, anonimizalos antes de compartir.
