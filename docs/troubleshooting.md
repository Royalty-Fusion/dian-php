# Troubleshooting

Empiece siempre por:

```bash
vendor/bin/dian-doctor --cert=empresa.p12 --password='***' --nit=900123456 --software-id=<SoftwareID>
```

y por `$dian->preflight($documento)->toText()`. La
[guía de integración](./integration-guide.md#12-errores-y-cómo-resolverlos)
tiene la tabla completa de errores.

## Certificado

### `No fue posible abrir el certificado PKCS#12. El archivo usa cifrado heredado…`

El `.p12` usa RC2/3DES y OpenSSL 3 no lo abre por defecto. Conviértalo:

```bash
openssl pkcs12 -legacy -in empresa.p12 -nodes -out tmp.pem
openssl pkcs12 -export -in tmp.pem -out empresa-nuevo.p12
rm tmp.pem
```

### `Verifique la contraseña y que el archivo sea PKCS#12`

Contraseña incorrecta o archivo dañado. Pruébelo con
`openssl pkcs12 -info -in empresa.p12 -nokeys`.

### SOAP Fault `InvalidSecurity`

La DIAN rechazó la cabecera WS-Security: certificado vencido, revocado, no
asociado al facturador, o reloj del servidor desfasado (el *timestamp* va
firmado). Sincronice el reloj (NTP) y revise el certificado con `dian-doctor`.

## Documento

### `XML_010`: el CUFE del XML no coincide con el recalculado

La DIAN rechaza estos documentos (CUFE mal calculado). Causas: clave técnica
de otra resolución, PIN distinto al registrado, o ambiente cruzado (CUFE de
habilitación enviado a producción). Obtenga la clave correcta con
`$dian->getNumberingRanges($nit, $softwareId)`.

`CufeGenerator::buildString($doc, $clave, $ambiente)` devuelve la cadena exacta
que se firma con SHA-384, útil para compararla con la que reporta la DIAN.

### Rechazo por documento procesado anteriormente

Ya existe un documento con ese número. No reenvíe: consulte el estado con
`$dian->getStatus($cufe)` y continúe con el siguiente número.

### Notificaciones del RUT (nombre o NIT del adquiriente)

Son avisos: el documento se acepta, pero los datos del cliente no coinciden
con el RUT. Aparecen en `$result->getNotifications()`.

### El set de pruebas no avanza

* Verifique que envía con `setTestSetId()` (si no, el preflight avisa `ENV_002`).
* Los envíos del set son asíncronos: consulte cada `ZipKey` con
  `$dian->waitForResult($zipKey)` y revise los rechazos.
* Use el prefijo `SETP` y números del rango `990000000–995000000`.

## Conexión

### `Error de conexión con la DIAN`

El servidor no llega a `vpfe-hab.dian.gov.co` / `vpfe.dian.gov.co`
(firewall, proxy o DNS). Verifique con:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl
```

Los errores de red y HTTP 5xx se reintentan automáticamente 2 veces.

## Cómo pedir ayuda

1. `$report->toArray()` del preflight.
2. El XML firmado (`$result->getSignedXml()`) y `$result->getErrors()`.
3. La respuesta completa: `$result->getResponse()?->getRaw()`.

> Nunca publique el `.p12`, su contraseña ni el PIN del software. Anonimice
> los datos de clientes reales antes de compartir un XML.
