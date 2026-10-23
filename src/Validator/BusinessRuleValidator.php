<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

use RoyaltyFusion\DianPhp\Catalog\CurrencyRegistry;
use RoyaltyFusion\DianPhp\Catalog\MunicipioRegistry;
use RoyaltyFusion\DianPhp\Catalog\PaymentMethodRegistry;
use RoyaltyFusion\DianPhp\Catalog\TipoDocumentoIdentificacion;
use RoyaltyFusion\DianPhp\Catalog\TipoFactura;
use RoyaltyFusion\DianPhp\Catalog\TipoOperacion;
use RoyaltyFusion\DianPhp\Catalog\Tributo;
use RoyaltyFusion\DianPhp\Catalog\UnitMeasureRegistry;
use RoyaltyFusion\DianPhp\Model\Address;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\NitDvCalculator;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocument;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocumentAdjustmentNote;
use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;

/**
 * Pre-send business rules: the problems DIAN would reject (or flag) on
 * reception, detected locally with a code and a message in Spanish.
 *
 * severity "error" blocks the send; "warning" is informative.
 *
 *   DOC_001  sin líneas                     DOC_002  fecha de emisión futura
 *   DOC_003  fecha con más de 10 días       DOC_004  prefijo/número inválido
 *   DOC_005  setTotal() ≠ total calculado
 *   PARTY_001..014  emisor / adquiriente (NIT, DV, tipo doc, DANE, responsabilidades, correo)
 *   ITEM_001..006   líneas (descripción, cantidad, precio, unidad, regalo, base del impuesto)
 *   TAX_001..006    tributos (código, cálculo, tarifas, retenciones en notas, consistencia)
 *   TOTAL_002       valor a pagar negativo
 *   CURRENCY_001/002 moneda y TRM
 *   PAY_001..004    medios y formas de pago
 *   RES_000..005    resolución de numeración
 *   SOFT_001..004   software, PIN y clave técnica
 *   NOTE_001..005   notas crédito/débito y nota de ajuste
 *   INV_001..003    tipo de factura y de operación
 *   DS_001..003     documento soporte
 *   ENV_001..003    coherencia con el ambiente (habilitación / producción)
 */
final class BusinessRuleValidator
{
    /** Clave técnica pública del ambiente de habilitación (set de pruebas). */
    public const HABILITACION_TECHNICAL_KEY = 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c';

    /** Responsabilidades vigentes (Resolución 000042 de 2020, Anexo 1.9 tabla 13.2.6.1). */
    private const RESPONSABILIDADES = ['O-13', 'O-15', 'O-23', 'O-47', 'R-99-PN'];

    private const AMOUNT_TOLERANCE = 1.00;

    /**
     * @param string|null $environment '1' producción, '2' habilitación, null = skip ENV_* rules
     */
    public function validate(Invoice|CreditNote|DebitNote|SupportDocument $document, ?string $environment = null): ValidationResult
    {
        $result = new ValidationResult();

        $this->validateHeader($document, $result);
        $this->validateItems($document, $result);
        $this->validateTaxes($document, $result);
        $this->validateTotals($document, $result);
        $this->validateCurrency($document, $result);
        $this->validatePayments($document, $result);

        if ($document instanceof SupportDocument) {
            $this->validateSupportDocument($document, $result);
        } else {
            $this->validateCompany($document->getCompany(), $result, 'emisor');
            $this->validateClient($document->getClient(), $result, 'adquiriente', $document instanceof Invoice && $document->getInvoiceTypeCode() === '02');
        }

        if ($document instanceof Invoice) {
            $this->validateInvoiceType($document, $result);
            $this->validateResolution($document->getResolution(), $document->getPrefijo(), $document->getNumero(), $document->getFecha(), $result);
            $this->validateSoftware($document, $result);
        } elseif ($document instanceof CreditNote || $document instanceof DebitNote) {
            $this->validateNote($document, $result);
            $this->validateSoftware($document, $result);
        }

        if ($environment !== null) {
            $this->validateEnvironment($document, $environment, $result);
        }

        return $result;
    }

    private function validateHeader(Invoice|CreditNote|DebitNote|SupportDocument $doc, ValidationResult $r): void
    {
        if (count($doc->getItems()) === 0) {
            $r->add('DOC_001', 'El documento no tiene líneas.');
        }

        $date = $doc->getFecha();
        if ($date === null) {
            $r->add('DOC_002', 'Falta la fecha de emisión (setFecha).', '/*/cbc:IssueDate');
        } else {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
            if ($date > $now->modify('+1 hour')) {
                $r->add('DOC_002', 'La fecha de emisión está en el futuro; la DIAN la rechaza.', '/*/cbc:IssueDate');
            } elseif ($date < $now->modify('-10 days')) {
                $r->add('DOC_003', 'La fecha de emisión tiene más de 10 días; la DIAN puede notificarla o rechazarla.', '/*/cbc:IssueDate', 'warning');
            }
        }

        if ($doc->getNumero() === '' || preg_match('/^\d+$/', $doc->getNumero()) !== 1) {
            $r->add('DOC_004', "El número del documento debe ser numérico (recibido '{$doc->getNumero()}'); el prefijo va en setPrefijo().", '/*/cbc:ID');
        }
        if (strlen($doc->getPrefijo()) > 4) {
            $r->add('DOC_004', "El prefijo '{$doc->getPrefijo()}' supera 4 caracteres.", '/*/cbc:ID');
        }

        if ($doc->getTotal() > 0 && $doc->getItems() !== []) {
            $payable = DocumentCalculator::totals($doc)['payableAmount'];
            if (abs($payable - $doc->getTotal()) > 0.01) {
                $r->add('DOC_005', sprintf(
                    'setTotal(%.2f) no coincide con el valor a pagar calculado (%.2f). El SDK usa el calculado; revise sus cifras.',
                    $doc->getTotal(),
                    $payable
                ), '/*/cac:LegalMonetaryTotal/cbc:PayableAmount', 'warning');
            }
        }
    }

    private function validateCompany(?Company $company, ValidationResult $r, string $role): void
    {
        if ($company === null) {
            $r->add('PARTY_001', "Falta el {$role} (setCompany).");
            return;
        }
        if (preg_match('/^\d{5,15}$/', $company->getNit()) !== 1) {
            $r->add('PARTY_001', "NIT del {$role} vacío o con caracteres no numéricos (sin DV ni guiones).");
        }
        $computedDv = NitDvCalculator::compute($company->getNit());
        if ($company->getNitDv() !== $computedDv) {
            $r->add('PARTY_002', "El DV configurado del {$role} ({$company->getNitDv()}) no corresponde al NIT (DV calculado {$computedDv}).");
        }
        if ($company->getTipoDocumento() !== '' && !TipoDocumentoIdentificacion::hasCode($company->getTipoDocumento())) {
            $r->add('PARTY_003', "Tipo de documento del {$role} desconocido: {$company->getTipoDocumento()}.");
        }
        if (trim($company->getRazonSocial()) === '') {
            $r->add('PARTY_005', "Falta la razón social del {$role}.");
        }
        if (!in_array($company->getTipoOrganizacion(), ['1', '2'], true)) {
            $r->add('PARTY_009', "Tipo de organización del {$role} debe ser 1 (jurídica) o 2 (natural).");
        }
        $this->validateResponsabilidades($company->getResponsabilidades(), $r, $role);

        $address = $company->getAddress();
        if ($address === null) {
            $r->add('PARTY_006', "Falta la dirección del {$role} (municipio DANE, departamento, país).");
        } else {
            $this->validateAddress($address, $r, $role);
        }
        if ($company->getContact() === null || $company->getContact()->getElectronicMail() === '') {
            $r->add('PARTY_010', "El {$role} no tiene correo de contacto.", '', 'warning');
        }
    }

    private function validateClient(?Client $client, ValidationResult $r, string $role, bool $export = false): void
    {
        if ($client === null) {
            $r->add('PARTY_011', "Falta el {$role} (setClient).");
            return;
        }
        if (!TipoDocumentoIdentificacion::hasCode($client->getTipoDocumento())) {
            $r->add('PARTY_004', "Tipo de documento del {$role} desconocido: '{$client->getTipoDocumento()}'.");
        }
        $number = $client->getNumeroDocumento();
        if ($number === '') {
            $r->add('PARTY_012', "Falta el número de documento del {$role}.");
        } elseif ($client->getTipoDocumento() === '31' && preg_match('/^\d{5,15}$/', $number) !== 1) {
            $r->add('PARTY_012', "El NIT del {$role} debe ser numérico y sin DV ('{$number}').");
        }
        if (trim($client->getRazonSocial()) === '') {
            $r->add('PARTY_005', "Falta el nombre o razón social del {$role}.");
        }
        if (!in_array($client->getTipoOrganizacion(), ['1', '2'], true)) {
            $r->add('PARTY_009', "Tipo de organización del {$role} debe ser 1 (jurídica) o 2 (natural).");
        }
        $this->validateResponsabilidades($client->getResponsabilidades() ?: 'R-99-PN', $r, $role);

        $email = $client->getContact()?->getElectronicMail() ?: $client->getEmail();
        if ($email === '') {
            $r->add('PARTY_013', "El {$role} no tiene correo: no podrá recibir la factura (AttachedDocument).", '', 'warning');
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $r->add('PARTY_013', "Correo del {$role} inválido: {$email}.");
        }

        if ($client->getAddress() !== null) {
            $this->validateAddress($client->getAddress(), $r, $role);
        }
        if ($export && $client->getAddress() !== null && $client->getAddress()->getCountryCode() === 'CO') {
            $r->add('INV_002', 'Factura de exportación con adquiriente ubicado en Colombia.', '', 'warning');
        }
    }

    private function validateResponsabilidades(string $value, ValidationResult $r, string $role): void
    {
        $codes = array_filter(array_map('trim', explode(';', $value)));
        if ($codes === []) {
            $r->add('PARTY_008', "Faltan las responsabilidades fiscales del {$role} (ej. 'O-13;O-15' o 'R-99-PN').");
            return;
        }
        foreach ($codes as $code) {
            if (!in_array($code, self::RESPONSABILIDADES, true)) {
                $r->add('PARTY_008', "Responsabilidad fiscal '{$code}' del {$role} no es válida en el Anexo 1.9 (use O-13, O-15, O-23, O-47 o R-99-PN).");
            }
        }
    }

    private function validateAddress(Address $address, ValidationResult $r, string $role): void
    {
        if ($address->getCountryCode() !== 'CO') {
            return;
        }
        $city = $address->getCityCode();
        if (!MunicipioRegistry::has($city)) {
            $r->add('PARTY_007', "Código de municipio DANE '{$city}' del {$role} no existe.");
        } elseif ($address->getDepartmentCode() !== '' && MunicipioRegistry::departmentCode($city) !== $address->getDepartmentCode()) {
            $r->add('PARTY_007', "El departamento '{$address->getDepartmentCode()}' del {$role} no corresponde al municipio {$city}.");
        }
        if (trim($address->getLine()) === '') {
            $r->add('PARTY_014', "Falta la línea de dirección del {$role}.", '', 'warning');
        }
    }

    private function validateItems(Invoice|CreditNote|DebitNote|SupportDocument $doc, ValidationResult $r): void
    {
        foreach ($doc->getItems() as $i => $item) {
            $n = $i + 1;
            if (trim($item->getDescripcion()) === '') {
                $r->add('ITEM_001', "Línea {$n}: falta la descripción.");
            }
            if ($item->getCantidad() <= 0) {
                $r->add('ITEM_002', "Línea {$n}: la cantidad debe ser mayor que cero.");
            }
            if ($item->getPrecio() < 0) {
                $r->add('ITEM_003', "Línea {$n}: el precio no puede ser negativo.");
            }
            if (!UnitMeasureRegistry::has($item->getUnitCode())) {
                $r->add('ITEM_004', "Línea {$n}: unidad de medida '{$item->getUnitCode()}' no existe (UN/ECE Rec 20, ej. 94, EA, KGM).");
            }
            if ($item->getPrecio() == 0.0 && $item->getReferencePrice() <= 0) {
                $r->add('ITEM_005', "Línea {$n}: línea gratuita sin precio de referencia (setReferencePrice).");
            }
            $lineAmount = DocumentCalculator::lineExtensionAmount($item);
            foreach ($item->getTaxes() as $tax) {
                if (!$tax->isRetention() && $tax->getBase() > 0 && abs($tax->getBase() - $lineAmount) > self::AMOUNT_TOLERANCE && $tax->getCode() !== '22') {
                    $r->add('ITEM_006', sprintf('Línea %d: la base del tributo %s (%.2f) difiere del valor de la línea (%.2f).', $n, $tax->getCode(), $tax->getBase(), $lineAmount), '', 'warning');
                }
            }
        }
    }

    private function validateTaxes(Invoice|CreditNote|DebitNote|SupportDocument $doc, ValidationResult $r): void
    {
        $all = $doc->getTaxes();
        foreach ($doc->getItems() as $item) {
            $all = array_merge($all, $item->getTaxes());
        }

        foreach ($all as $tax) {
            if (!Tributo::hasCode($tax->getCode())) {
                $r->add('TAX_001', "Código de tributo desconocido: '{$tax->getCode()}'.");
                continue;
            }
            if ($tax->getAmount() != 0.0 && $tax->getBase() > 0 && $tax->getPercent() > 0) {
                $expected = $tax->getBase() * $tax->getPercent() / 100;
                if (abs($expected - $tax->getAmount()) > self::AMOUNT_TOLERANCE) {
                    $r->add('TAX_002', sprintf(
                        'Tributo %s: el valor %.2f no corresponde a base %.2f × %.2f%% = %.2f.',
                        $tax->getCode(),
                        $tax->getAmount(),
                        $tax->getBase(),
                        $tax->getPercent(),
                        $expected
                    ));
                }
            }
            if ($tax->getCode() === '01' && !in_array((float) $tax->getPercent(), [0.0, 5.0, 19.0], true)) {
                $r->add('TAX_003', "Tarifa de IVA inusual: {$tax->getPercent()}% (vigentes: 0, 5, 19).", '', 'warning');
            }
            if ($tax->isRetention() && ($doc instanceof CreditNote || $doc instanceof DebitNote || $doc instanceof SupportDocumentAdjustmentNote)) {
                $r->add('TAX_004', "Las notas no admiten retenciones (WithholdingTaxTotal); el tributo {$tax->getCode()} se omitirá.");
            }
            if ($tax->isRetention() && !in_array($tax->getCode(), ['05', '06', '07'], true)) {
                $r->add('TAX_001', "El tributo {$tax->getCode()} está marcado como retención pero las retenciones son 05, 06 y 07.");
            }
        }

        if ($all === []) {
            $r->add('TAX_005', 'El documento no tiene tributos. Si los productos son excluidos está bien; si no, agregue IVA/INC por línea.', '', 'warning');
        }

        // Explicit document taxes must match the sum of line taxes.
        $lineTotals = [];
        foreach ($doc->getItems() as $item) {
            foreach ($item->getTaxes() as $tax) {
                if (!$tax->isRetention()) {
                    $amount = $tax->getAmount() ?: $tax->getBase() * $tax->getPercent() / 100;
                    $lineTotals[$tax->getCode()] = ($lineTotals[$tax->getCode()] ?? 0.0) + round($amount, 2);
                }
            }
        }
        if ($doc->getTaxes() !== [] && $lineTotals !== []) {
            $docTotals = DocumentCalculator::totals($doc)['taxTotalsByCode'];
            foreach ($lineTotals as $code => $amount) {
                if (abs(($docTotals[$code] ?? 0.0) - $amount) > self::AMOUNT_TOLERANCE) {
                    $r->add('TAX_006', sprintf('El total del tributo %s del documento (%.2f) no coincide con la suma de las líneas (%.2f).', $code, $docTotals[$code] ?? 0.0, $amount));
                }
            }
        }
    }

    private function validateTotals(Invoice|CreditNote|DebitNote|SupportDocument $doc, ValidationResult $r): void
    {
        if ($doc->getItems() === []) {
            return;
        }
        $totals = DocumentCalculator::totals($doc);
        if ($totals['payableAmount'] < 0) {
            $r->add('TOTAL_002', 'El valor a pagar es negativo (anticipos o descuentos mayores que el total).');
        }
    }

    private function validateCurrency(Invoice|CreditNote|DebitNote|SupportDocument $doc, ValidationResult $r): void
    {
        $code = $doc->getCurrencyCode();
        if (!CurrencyRegistry::has($code)) {
            $r->add('CURRENCY_001', "Código de moneda inválido: '{$code}' (ISO 4217).");
        }
        if ($code !== 'COP' && $doc->getPaymentExchangeRate() === null) {
            $r->add('CURRENCY_002', "Moneda {$code} sin tasa de cambio (setPaymentExchangeRate con la TRM).");
        }
    }

    private function validatePayments(Invoice|CreditNote|DebitNote|SupportDocument $doc, ValidationResult $r): void
    {
        if ($doc->getPayments() === []) {
            $r->add('PAY_001', 'Falta el medio de pago (addPayment): forma 1 contado / 2 crédito y medio (10 efectivo, 42 consignación, 47 transferencia, ZZZ otro...).');
            return;
        }
        foreach ($doc->getPayments() as $payment) {
            if (!in_array($payment->getMethodId(), ['1', '2'], true)) {
                $r->add('PAY_003', "Forma de pago '{$payment->getMethodId()}' inválida (1 contado, 2 crédito).");
            }
            if ($payment->getMethodId() === '2' && $payment->getDueDate() === null) {
                $r->add('PAY_002', 'Pago a crédito sin fecha de vencimiento (Payment::setDueDate).');
            }
            if (!PaymentMethodRegistry::has($payment->getMeansId())) {
                $r->add('PAY_004', "Medio de pago '{$payment->getMeansId()}' no existe en el catálogo DIAN.", '', 'warning');
            }
        }
    }

    private function validateResolution(?Resolution $resolution, string $prefix, string $number, ?\DateTimeInterface $date, ValidationResult $r): void
    {
        if ($resolution === null) {
            $r->add('RES_000', 'Falta la resolución de numeración (setResolution).');
            return;
        }
        if ($resolution->getNumber() === '') {
            $r->add('RES_000', 'La resolución no tiene número.');
        }
        if ($resolution->getPrefix() !== $prefix) {
            $r->add('RES_003', "El prefijo del documento '{$prefix}' no coincide con el de la resolución '{$resolution->getPrefix()}'.");
        }
        $from = (int) $resolution->getFrom();
        $to   = (int) $resolution->getTo();
        $num  = (int) $number;
        if ($num < $from || $num > $to) {
            $r->add('RES_001', "El número {$number} está fuera del rango autorizado {$from}-{$to}.");
        } elseif ($to > $from && ($num - $from) / ($to - $from) > 0.9) {
            $r->add('RES_004', 'Se ha usado más del 90% del rango de numeración; solicite una nueva resolución.', '', 'warning');
        }
        if ($date !== null && $resolution->getDateFrom() !== null && $resolution->getDateTo() !== null) {
            $day = $date->format('Y-m-d');
            if ($day < $resolution->getDateFrom()->format('Y-m-d') || $day > $resolution->getDateTo()->format('Y-m-d')) {
                $r->add('RES_002', 'La fecha de emisión está fuera de la vigencia de la resolución.');
            } elseif ($resolution->getDateTo() < (new \DateTimeImmutable())->modify('+30 days')) {
                $r->add('RES_005', 'La resolución vence en menos de 30 días.', '', 'warning');
            }
        }
    }

    private function validateSoftware(Invoice|CreditNote|DebitNote $doc, ValidationResult $r): void
    {
        $software = $doc->getSoftware();
        if ($software === null || $software->getId() === '' || $software->getPin() === '') {
            $r->add('SOFT_001', 'Falta el software DIAN (setSoftware con SoftwareID y PIN).');
        } else {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $software->getId()) !== 1) {
                $r->add('SOFT_002', "El SoftwareID '{$software->getId()}' no tiene formato UUID como el asignado por la DIAN.", '', 'warning');
            }
            if (preg_match('/^\d{5}$/', $software->getPin()) !== 1) {
                $r->add('SOFT_004', 'El PIN del software normalmente es de 5 dígitos; verifique el registrado en la DIAN.', '', 'warning');
            }
        }
        if ($doc instanceof Invoice && preg_match('/^[0-9a-f]{40,64}$/i', $doc->getTechnicalKey()) !== 1) {
            $r->add('SOFT_003', 'Falta la clave técnica de la resolución (setTechnicalKey). Obténgala con Dian::getNumberingRanges().');
        }
    }

    private function validateNote(CreditNote|DebitNote $note, ValidationResult $r): void
    {
        $credit   = $note instanceof CreditNote;
        $expected = $note->getBillingReference() !== null ? ($credit ? '20' : '30') : ($credit ? '22' : '32');
        if ($note->getCustomizationId() !== $expected) {
            $r->add('NOTE_005', "CustomizationID {$note->getCustomizationId()} no corresponde: con referencia a factura use " . ($credit ? '20' : '30') . ', sin referencia ' . ($credit ? '22' : '32') . '.');
        }

        $discrepancy = $note->getDiscrepancyResponse();
        if ($discrepancy === null) {
            $r->add('NOTE_001', 'Falta el concepto de corrección (setDiscrepancyResponse).');
        } else {
            $valid = $credit ? ['1', '2', '3', '4', '5', '6'] : ['1', '2', '3', '4'];
            if (!in_array($discrepancy->getResponseCode(), $valid, true)) {
                $r->add('NOTE_002', "Concepto de corrección '{$discrepancy->getResponseCode()}' inválido (válidos: " . implode(', ', $valid) . ').');
            }
            if (trim($discrepancy->getDescription()) === '') {
                $r->add('NOTE_002', 'El concepto de corrección no tiene descripción.');
            }
        }

        $ref = $note->getBillingReference();
        if ($ref !== null) {
            if ($ref->getNumber() === '' || $ref->getDate() === null) {
                $r->add('NOTE_003', 'La referencia a la factura requiere número y fecha de emisión.');
            }
            if (preg_match('/^[0-9a-f]{96}$/', $ref->getUuid()) !== 1) {
                $r->add('NOTE_003', 'El CUFE de la factura referenciada debe tener 96 caracteres hexadecimales.');
            }
        } elseif ($note->getInvoicePeriodStart() === null) {
            $r->add('NOTE_004', 'Nota sin referencia a factura: indique el periodo facturado (setInvoicePeriod).');
        }
    }

    private function validateInvoiceType(Invoice $invoice, ValidationResult $r): void
    {
        if (!TipoFactura::hasCode($invoice->getInvoiceTypeCode())) {
            $r->add('INV_001', "Tipo de factura '{$invoice->getInvoiceTypeCode()}' inválido (01, 02, 03, 04).");
        }
        $operation = TipoOperacion::tryFrom($invoice->getCustomizationId());
        if ($operation === null || in_array($operation->value, ['20', '22', '30', '32'], true)) {
            $r->add('INV_003', "Tipo de operación '{$invoice->getCustomizationId()}' no aplica a facturas.");
        }
        if ($invoice->getInvoiceTypeCode() === '02' && $invoice->getCurrencyCode() === 'COP') {
            $r->add('INV_002', 'Factura de exportación en COP: normalmente se factura en la moneda extranjera con TRM.', '', 'warning');
        }
    }

    private function validateSupportDocument(SupportDocument $ds, ValidationResult $r): void
    {
        $this->validateCompany($ds->getCompany(), $r, 'adquiriente (emisor del DS)');
        $this->validateClient($ds->getSupplier(), $r, 'vendedor no obligado');

        if (!in_array($ds->getCustomizationId(), [SupportDocument::RESIDENTE, SupportDocument::NO_RESIDENTE], true)) {
            $r->add('DS_001', "CustomizationID '{$ds->getCustomizationId()}' inválido para DS (10 residente, 11 no residente).");
        }
        if (!in_array($ds->getTransmissionCode(), ['1', '2'], true)) {
            $r->add('DS_002', 'Forma de transmisión inválida (1 por operación, 2 acumulado semanal).');
        }
        $supplierType = $ds->getSupplier()?->getTipoDocumento();
        if ($ds->getCustomizationId() === SupportDocument::NO_RESIDENTE && in_array($supplierType, ['13', '31'], true)) {
            $r->add('DS_003', 'Vendedor no residente identificado con documento colombiano; verifique el tipo de documento.', '', 'warning');
        }
        $software = $ds->getSoftware();
        if ($software === null || $software->getId() === '' || $software->getPin() === '') {
            $r->add('SOFT_001', 'Falta el software DIAN del documento soporte (SoftwareID y PIN).');
        }
        if ($ds instanceof SupportDocumentAdjustmentNote) {
            if ($ds->getBillingReference() === null) {
                $r->add('NOTE_003', 'La nota de ajuste debe referenciar el documento soporte (setBillingReference).');
            }
            if ($ds->getDiscrepancyResponse() === null) {
                $r->add('NOTE_001', 'Falta el concepto de la nota de ajuste (setDiscrepancyResponse).');
            }
        } else {
            $this->validateResolution($ds->getResolution(), $ds->getPrefijo(), $ds->getNumero(), $ds->getFecha(), $r);
        }
    }

    private function validateEnvironment(Invoice|CreditNote|DebitNote|SupportDocument $doc, string $environment, ValidationResult $r): void
    {
        $production = $environment === '1';
        if ($doc instanceof Invoice && $production && strtolower($doc->getTechnicalKey()) === self::HABILITACION_TECHNICAL_KEY) {
            $r->add('ENV_001', 'Está usando la clave técnica de habilitación en producción; use la de su resolución real.');
        }
        if ($production && in_array(strtoupper($doc->getPrefijo()), ['SETP', 'SETT', 'SETG'], true)) {
            $r->add('ENV_003', "El prefijo {$doc->getPrefijo()} es del set de pruebas; en producción use el de su resolución.");
        }
        if (!$production && $doc->getTestSetId() === '') {
            $r->add('ENV_002', 'Habilitación sin TestSetId: el documento no contará para el set de pruebas (setTestSetId).', '', 'warning');
        }
    }
}
