# Blueprint REST API para tu ERP

El SDK no expone una API REST por sí mismo — es una librería. Pero este
documento es la **referencia recomendada** para que tu ERP (o cualquier
integración) exponga la facturación electrónica DIAN como endpoints HTTP.

El diseño está inspirado en [`soenac/api-dian`](https://github.com/soenac/api-dian)
(LGPL-3.0) — estudiamos su REST API y aquí proponemos un equivalente que
encaja en cualquier app Symfony / Laravel / framework PHP plano.

## Endpoints recomendados

### Configuración inicial (una vez por empresa)

| Método | Ruta | Cuerpo | Acción |
|---|---|---|---|
| POST | `/api/dian/config/company/{nit}/{dv?}` | `{razon_social, regimen, ...}` | Crear/actualizar empresa |
| PUT  | `/api/dian/config/certificate` | `{path, password}` | Subir certificado P12 |
| PUT  | `/api/dian/config/software` | `{id, pin, provider_nit}` | Datos del software DIAN |
| PUT  | `/api/dian/config/resolution` | `{number, prefix, from, to, date_from, date_to}` | Resolución de numeración |
| PUT  | `/api/dian/config/environment` | `{environment: "habilitacion" \| "produccion"}` | Cambiar ambiente |

### Emisión

| Método | Ruta | Cuerpo | Acción |
|---|---|---|---|
| POST | `/api/dian/invoice/{testSetId?}` | Invoice DTO | Emite y envía factura. `testSetId` opcional para Habilitación. |
| POST | `/api/dian/credit-note/{testSetId?}` | CreditNote DTO | Emite y envía NC |
| POST | `/api/dian/debit-note/{testSetId?}` | DebitNote DTO | Emite y envía ND |
| POST | `/api/dian/support-document` | SupportDocument DTO | Emite DS |
| POST | `/api/dian/payroll` | PayrollDocument DTO | Emite Nómina |
| POST | `/api/dian/radian/event` | EventDocument DTO | Emite evento RADIAN |

### Consulta

| Método | Ruta | Acción |
|---|---|---|
| GET  | `/api/dian/status/{trackId}` | Consultar estado de un documento |
| GET  | `/api/dian/status/{trackId}/zip` | Descargar ApplicationResponse |
| GET  | `/api/dian/xml/{trackId}` | Descargar XML firmado original |
| GET  | `/api/dian/numbering-range` | Rangos de numeración autorizados |

### Catálogos (consulta — útiles para autocomplete del UI)

| Método | Ruta | Acción |
|---|---|---|
| GET | `/api/dian/catalog/municipalities` | Lista DANE de municipios (~1,100) |
| GET | `/api/dian/catalog/countries` | ISO 3166-1 |
| GET | `/api/dian/catalog/currencies` | ISO 4217 |
| GET | `/api/dian/catalog/units` | UN/ECE Rec 20 |
| GET | `/api/dian/catalog/liabilities` | Responsabilidades DIAN |
| GET | `/api/dian/catalog/payment-methods` | UN/CEFACT 4461 |

## Implementación rápida en Symfony

```php
// src/Controller/Api/DianInvoiceController.php
namespace App\Controller\Api;

use RoyaltyFusion\DianPhp\Dian;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use RoyaltyFusion\DianPhp\Model\Invoice;

#[Route('/api/dian/invoice', name: 'api_dian_invoice_')]
final class DianInvoiceController extends AbstractController
{
    public function __construct(
        private readonly Dian $dian,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('/{testSetId?}', name: 'send', methods: ['POST'])]
    public function send(Request $request, ?string $testSetId = null): JsonResponse
    {
        /** @var Invoice $invoice */
        $invoice = $this->serializer->deserialize($request->getContent(), Invoice::class, 'json');
        if ($testSetId !== null) {
            $invoice->setTestSetId($testSetId);
        }

        $result = $this->dian->send($invoice);

        return new JsonResponse([
            'success' => $result->isSuccess(),
            'cufe'    => $result->getCufe(),
            'error'   => $result->getErrorMessage(),
        ], $result->isSuccess() ? 200 : 422);
    }
}
```

Para el catálogo de municipios:

```php
use RoyaltyFusion\DianPhp\Catalog\MunicipioRegistry;

#[Route('/api/dian/catalog/municipalities', methods: ['GET'])]
public function municipalities(): JsonResponse
{
    return new JsonResponse(MunicipioRegistry::all());
}
```

## Convención de errores

```json
{
  "success": false,
  "error_code": "TAX_002",
  "error_message": "Tax amount mismatch for code 01: expected 19000.00, got 1.00",
  "validation_errors": [
    {"code": "TAX_002", "xpath": "/Invoice/cac:TaxTotal[1]/cbc:TaxAmount", "message": "..."}
  ]
}
```

Cuando `validateBeforeSend(true)` esté activo, los errores del
`BusinessRuleValidator` salen serializados aquí.

## Autenticación

Recomendaciones:
* **OAuth2** (Symfony Lexik JWT Authentication Bundle) para clientes externos
* **Token estático** para llamadas server-to-server dentro de tu ERP
* **Rate limiting** con `symfony/rate-limiter` — la DIAN ya limita los WS
  por NIT, conviene no rebotar contra ese límite

## Versionado

Usa `/api/v1/dian/...` desde el día uno. Cuando DIAN cambie el Anexo (suele
pasar cada 1-2 años) podrás introducir `/api/v2/...` sin romper integraciones.
