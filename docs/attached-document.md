# AttachedDocument — Contenedor B2B

DIAN exige que cuando un emisor envía una factura a su cliente, lo haga
dentro de un **AttachedDocument** que empaqueta:

1. La factura firmada (XML completo, embebido en `<![CDATA[...]]>`)
2. El **`ApplicationResponse`** firmado por DIAN (la prueba de aprobación)
3. Opcionalmente, una firma XAdES adicional del facturador tecnológico

Es el formato que verás en cualquier email de factura electrónica B2B.

## Caso 1: tu ERP recibe una factura

```php
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocumentParser;

$xml = file_get_contents($emailAttachment);
$parser = new AttachedDocumentParser();
$doc = $parser->parse($xml);

if (!$parser->isAccepted($doc)) {
    throw new \RuntimeException('Factura NO aprobada por DIAN — rechazar.');
}

echo "CUFE:        {$doc->getId()}\n";
echo "Factura ID:  {$doc->getParentDocumentId()}\n";

// Guarda el XML firmado como prueba legal
file_put_contents(
    "storage/dian/{$doc->getParentDocumentId()}.xml",
    $doc->getSignedInvoiceXml()
);
```

## Caso 2: tu ERP envía una factura a un cliente

```php
use RoyaltyFusion\DianPhp\AttachedDocument\{AttachedDocument, AttachedDocumentBuilder};

$ad = (new AttachedDocument())
    ->setId($result->getCufe())
    ->setParentDocumentId('FV-67890')
    ->setIssueDate(new DateTimeImmutable('now'))
    ->setSender($miEmpresa)
    ->setReceiver($cliente)
    ->setSignedInvoiceXml($dian->getSignedXml($invoice))
    ->setApplicationResponseXml($status->getApplicationResponseXml());

$xmlEnvelope = (new AttachedDocumentBuilder())->build($ad);
$mailer->send($cliente->getEmail(), 'Factura electrónica FV-67890', attachments: [$xmlEnvelope]);
```

## Estructura UBL

```
<AttachedDocument>
  <ext:UBLExtensions>
    <ext:UBLExtension>
      <ext:ExtensionContent>
        <ds:Signature/>      ← firma del facturador tecnológico (opcional)
      </ext:ExtensionContent>
    </ext:UBLExtension>
  </ext:UBLExtensions>
  <cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>
  <cbc:CustomizationID>Documentos adjuntos</cbc:CustomizationID>
  <cbc:ProfileID>Factura Electrónica de Venta</cbc:ProfileID>
  <cbc:ID>{CUFE}</cbc:ID>
  <cbc:ParentDocumentID>FV5</cbc:ParentDocumentID>
  <cac:SenderParty>...</cac:SenderParty>
  <cac:ReceiverParty>...</cac:ReceiverParty>
  <cac:Attachment>
    <cac:ExternalReference>
      <cbc:Description><![CDATA[<Invoice>...</Invoice>]]></cbc:Description>
    </cac:ExternalReference>
  </cac:Attachment>
  <cac:ParentDocumentLineReference>
    <cac:DocumentReference>
      <cbc:DocumentType>ApplicationResponse</cbc:DocumentType>
      <cac:Attachment>
        <cac:ExternalReference>
          <cbc:Description><![CDATA[<ApplicationResponse>...</ApplicationResponse>]]></cbc:Description>
        </cac:ExternalReference>
      </cac:Attachment>
    </cac:DocumentReference>
  </cac:ParentDocumentLineReference>
</AttachedDocument>
```

## Golden master

`resources/fixtures/siigo-blindacces-FV5.xml` es un AttachedDocument real,
aprobado por DIAN, generado por **Siigo Nube** para **BLINDACCES SAS**.
Lo usamos en `tests/Unit/AttachedDocument/AttachedDocumentParserTest` como
regresión contra producción.
