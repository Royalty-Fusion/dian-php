# Integración con Symfony

El SDK incluye un bundle plug-and-play en `src/Bridge/Symfony/` que registra todos los
servicios en el container y expone los comandos `dian:doctor` y `dian:status`.

## 1. Activar el bundle

```php
// config/bundles.php
return [
    // ...
    RoyaltyFusion\DianPhp\Bridge\Symfony\DianBundle::class => ['all' => true],
];
```

## 2. Configurar

```yaml
# config/packages/dian.yaml
dian:
  environment: '%env(DIAN_ENV)%'   # habilitacion | produccion
  nit: '%env(DIAN_NIT)%'           # NIT del facturador, usado por dian:doctor
  signer_role: supplier            # 'third party' si firma un proveedor tecnológico
  validate_before_send: true       # preflight automático antes de cada envío
  certificate:
    path: '%env(DIAN_CERT_PATH)%'
    password: '%env(DIAN_CERT_PASSWORD)%'
  software:
    id: '%env(DIAN_SOFT_ID)%'
    pin: '%env(DIAN_SOFT_PIN)%'
    provider_nit: '%env(DIAN_SOFT_PROVIDER_NIT)%'
    provider_code: '000'           # PPP del nombre de archivo
  report:
    logo_url: '/img/logo.png'
    accent_color: '#1e3a8a'
```

`.env`:

```
DIAN_ENV=habilitacion
DIAN_CERT_PATH=/var/secrets/dian/empresa.p12
DIAN_CERT_PASSWORD=••••••••
DIAN_SOFT_ID=00000000-0000-0000-0000-000000000000
DIAN_SOFT_PIN=00000
DIAN_SOFT_PROVIDER_NIT=900123456
```

## 3. Inyectar `Dian` en un controller

```php
namespace App\Controller;

use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\Invoice;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class InvoiceController extends AbstractController
{
    public function __construct(private readonly Dian $dian) {}

    #[Route('/api/invoice/send', methods: ['POST'])]
    public function send(Invoice $invoice): JsonResponse
    {
        $result = $this->dian->send($invoice);
        return new JsonResponse([
            'success' => $result->isSuccess(),
            'cufe'    => $result->getCufe(),
            'error'   => $result->getErrorMessage(),
        ]);
    }
}
```

## 4. Comandos de consola

```bash
bin/console dian:doctor                   # entorno, certificado y GetNumberingRange firmado
bin/console dian:status <cufe|zipKey>     # consultar
bin/console dian:status <cufe> --zip      # + ApplicationResponse XML
```

Las consultas usan el certificado configurado para la cabecera WS-Security.
Si la aplicación tiene un logger (Monolog), el cliente SOAP lo recibe
automáticamente.

## 5. Eventos (próximas versiones)

El bundle expondrá `PreSendEvent`, `PostSendEvent` y `ValidationFailedEvent` para
que puedas reaccionar (auditoría, notificaciones, reintento) sin acoplarte al
flujo del `Dian` facade.
