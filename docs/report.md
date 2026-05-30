# Representación gráfica (PDF / HTML)

DIAN exige una "representación gráfica" del documento electrónico que se entrega
al adquiriente. El SDK genera HTML limpio y dejas la conversión a PDF a la
librería que prefieras.

## Generar HTML

```php
use RoyaltyFusion\DianPhp\Report\HtmlReport;

$report = new HtmlReport(
    twig:       null,                // null = template por defecto
    logoUrl:    '/img/logo.png',
    accentColor:'#1e3a8a'
);

$html = $report->render($invoice, $result->getCufe(), $qrCodeUrl);
file_put_contents('factura.html', $html);
```

## Convertir a PDF con dompdf

```bash
composer require dompdf/dompdf
```

```php
use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('letter');
$dompdf->render();
file_put_contents('factura.pdf', $dompdf->output());
```

## Convertir a PDF con mPDF

```bash
composer require mpdf/mpdf
```

```php
$mpdf = new \Mpdf\Mpdf(['format' => 'Letter']);
$mpdf->WriteHTML($html);
$mpdf->Output('factura.pdf', \Mpdf\Output\Destination::FILE);
```

## Plantilla personalizada

Si quieres tu propio diseño, pasa un `Environment` Twig con tu loader:

```php
$loader = new \Twig\Loader\FilesystemLoader('templates/dian');
$twig   = new \Twig\Environment($loader, ['autoescape' => 'html']);
$report = new HtmlReport($twig);
```

Tu template recibe las mismas variables que la versión por defecto:

| Variable | Descripción |
|---|---|
| `doc` | El `Invoice` / `CreditNote` / `DebitNote` |
| `uuid` | CUFE o CUDE |
| `qrCodeUrl` | URL del QR DIAN |
| `heading` | "Factura Electrónica de Venta" / "Nota Crédito..." |
| `idLabel` | "CUFE" o "CUDE" |
| `totals` | Salida de `DocumentCalculator::totals()` |
| `logoUrl` | URL del logo configurado |
| `accentColor` | Color hex configurado |
