# Arquitectura

## Disposición de carpetas (estilo Greenter)

```
src/
├── Dian.php                  Facade principal (alias del "See" de Greenter)
├── Model/                    DTOs planos (Invoice, CreditNote, Company, ...)
├── Catalog/                  Enums DIAN (Tributo, FormaPago, Pais, ...)
├── Xml/                      Builder + Calculadora + CUFE + QR + templates Twig
├── Signer/                   XadesSigner (XAdES-EPES + política DIAN v2)
├── Ws/                       Cliente SOAP (SendBill, GetStatus, GetStatusZip)
├── Validator/                Reglas de negocio + validador XSD
├── Report/                   Representación gráfica HTML (PDF-ready)
├── SupportDocument/          DS — scaffold
├── Payroll/                  Nómina Electrónica — scaffold
├── Radian/                   Eventos título valor — scaffold
└── Bridge/Symfony/           Bundle (Configuration, DI, services.yaml, command)
```

## Flujo end-to-end

```
                          $invoice                 ┌──────────────┐
                              │                    │ Validator    │
                              │  validateBeforeSend?│ (opt-in)     │
                              ▼                    └──────────────┘
                       ┌──────────────┐
                       │ CufeGenerator│ → CUFE / CUDE (SHA-384)
                       └──────────────┘
                              │
                              ▼
                       ┌──────────────┐
                       │ QrGenerator  │ → URL QR para VPFE
                       └──────────────┘
                              │
                              ▼
                       ┌──────────────┐
                       │ XmlBuilder   │ → Twig + DocumentCalculator
                       └──────────────┘
                              │
                              ▼
                       ┌──────────────┐
                       │ XadesSigner  │ → XAdES-EPES + política v2
                       └──────────────┘
                              │
                              ▼
                       ┌──────────────┐
                       │ SoapClient   │ → ZIP + SOAP 1.2 + DIAN VPFE
                       └──────────────┘
                              │
                              ▼
                            Result
```

## Por qué Twig

Greenter popularizó el patrón de mantener cada plantilla XML como un archivo
`*.xml.twig` legible — funciona como un XSD comentado al que cualquier dev de
backoffice puede leer sin saber UBL. Los templates viven en
`src/Xml/templates/` y se renderizan con `autoescape: false` porque el XML ya
controla su propio escape.

## Por qué no `ext-soap`

`ext-soap` es brittle con WCF/Microsoft endpoints (que es lo que expone DIAN),
no soporta SOAP 1.2 con WS-Addressing limpio y mete capas de magia para
firmar. Empaquetamos el ZIP a mano y enviamos el envelope vía
`symfony/http-client` — más simple, más predecible y testeable.

## Inyección de dependencias

Todo es opt-in: el facade `Dian` instancia los colaboradores por defecto, pero
cada uno se puede sobrescribir con `set*()`. El bundle de Symfony los registra
como servicios autowireables.
