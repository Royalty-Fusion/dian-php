# Arquitectura

## Carpetas

```
src/
├── Dian.php                  Facade (preflight, send, consultas, AttachedDocument)
├── Model/                    Modelos planos (Invoice, CreditNote, Company, Item, Tax…)
├── Catalog/                  Enums y registros DIAN/DANE
├── Xml/                      DocumentCalculator, CufeGenerator, QrGenerator, XmlBuilder,
│                             TwigFactory, plantillas Twig + parciales compartidos
├── Signer/                   Certificate, XadesSigner, SignatureVerifier
├── Ws/                       SoapClient, WsSecurity, DianResponse, StatusResult, FileNameGenerator
├── Validator/                BusinessRuleValidator, XsdValidator, XmlConsistencyChecker,
│                             CertificateChecker, PreflightReport
├── SupportDocument/          Documento soporte y nota de ajuste
├── Payroll/                  Nómina electrónica
├── Radian/                   Eventos RADIAN (ApplicationResponse)
├── AttachedDocument/         Contenedor para el adquiriente
├── Report/                   Representación gráfica HTML
├── Tools/                    Doctor (diagnóstico de entorno)
└── Bridge/Symfony/           Bundle, configuración, comandos
```

## Flujo de `Dian::send()`

```
 documento PHP
      │
      ▼
 BusinessRuleValidator ──── errores ──▶ Result fallido (nada se envía)
      │
      ▼
 DocumentCalculator  (única fuente de totales para XML, CUFE y QR)
      │
      ├──▶ CufeGenerator (CUFE / CUDE)      QrGenerator (contenido del QR)
      ▼
 XmlBuilder  (Twig con escape XML)  ──▶  XadesSigner (XAdES-EPES)
      │
      ▼
 XsdValidator + SignatureVerifier + XmlConsistencyChecker ── errores ──▶ Result fallido
      │
      ▼
 SoapClient  (ZIP + WS-Security + WS-Addressing, reintentos)
      │
      ▼
 DianResponse ──▶ Result (isSuccess / isPending + ZipKey / errores y notificaciones)
```

## Decisiones

**Una sola fuente de cálculo.** La DIAN recalcula el CUFE con los valores
del XML. `DocumentCalculator` produce todos los montos (redondeados a 2
decimales) y tanto las plantillas como `CufeGenerator` y `QrGenerator` los
toman de ahí, así que no pueden divergir. `XmlConsistencyChecker` lo
comprueba desde el XML final, como haría la DIAN.

**Twig con escape XML.** Las plantillas son legibles como un XSD comentado y
comparten parciales (`templates/partials`). `TwigFactory` escapa todo valor y
elimina caracteres de control, de modo que textos como "Pérez & Cía" nunca
rompen el documento.

**Firma propia sobre DOM.** `XadesSigner` construye la firma con la misma
estructura de los documentos aprobados por la DIAN (`resources/fixtures`) y
`SignatureVerifier` la verifica; ambos se prueban contra esos documentos.

**SOAP sin `ext-soap`.** El servicio de la DIAN es WCF con SOAP 1.2,
WS-Addressing y WS-Security. `WsSecurity` arma y firma el sobre y
`symfony/http-client` lo envía; las respuestas se leen por `local-name()`
porque WCF cambia los prefijos.

**Validación sin red.** Los XSD de UBL 2.1 + DIAN se incluyen en
`resources/xsd` con rutas locales.

## Inyección de dependencias

El facade crea sus colaboradores con la configuración correcta del ambiente;
cada uno se puede reemplazar (`setSoapClient`, `setValidator`, `setLogger`…).
El bundle de Symfony los registra como servicios.
