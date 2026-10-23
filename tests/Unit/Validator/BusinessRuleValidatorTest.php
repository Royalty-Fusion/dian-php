<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Tests\Support\DocumentFactory;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Validator\BusinessRuleValidator;

final class BusinessRuleValidatorTest extends TestCase
{
    public function testValidInvoicePassesAllChecks(): void
    {
        $validator = new BusinessRuleValidator();
        $result    = $validator->validate($this->makeValidInvoice());

        $this->assertTrue($result->isValid(), implode("\n", $result->messages()));
    }

    public function testCompleteDocumentsFromTheFactoryAreValid(): void
    {
        foreach ([DocumentFactory::invoice(), DocumentFactory::creditNote(), DocumentFactory::creditNote(false), DocumentFactory::debitNote()] as $doc) {
            $result = (new BusinessRuleValidator())->validate($doc);
            $this->assertTrue($result->isValid(), get_class($doc) . ': ' . implode("\n", $result->messages()));
        }
    }

    /** @return array<string,array{callable(Invoice):void,string}> */
    public static function brokenInvoices(): array
    {
        return [
            'municipio inexistente' => [fn (Invoice $i) => $i->getCompany()?->getAddress()?->setCityCode('99999'), 'PARTY_007'],
            'departamento no coincide' => [fn (Invoice $i) => $i->getCompany()?->getAddress()?->setDepartmentCode('05'), 'PARTY_007'],
            'responsabilidad histórica' => [fn (Invoice $i) => $i->getCompany()?->setResponsabilidades('O-48'), 'PARTY_008'],
            'DV incorrecto' => [fn (Invoice $i) => $i->getCompany()?->setNitDv(1), 'PARTY_002'],
            'NIT con DV pegado' => [fn (Invoice $i) => $i->getClient()?->setNumeroDocumento('800199436-4'), 'PARTY_012'],
            'unidad inexistente' => [fn (Invoice $i) => $i->getItems()[0]->setUnitCode('XYZ'), 'ITEM_004'],
            'crédito sin vencimiento' => [fn (Invoice $i) => (new \ReflectionProperty($i, 'payments'))->setValue($i, [(new Payment())->setMethodId('2')->setMeansId('42')]), 'PAY_002'],
            'prefijo distinto a la resolución' => [fn (Invoice $i) => $i->setPrefijo('FE'), 'RES_003'],
            'sin clave técnica' => [fn (Invoice $i) => $i->setTechnicalKey(''), 'SOFT_003'],
            'moneda sin TRM' => [fn (Invoice $i) => $i->setCurrencyCode('USD'), 'CURRENCY_002'],
            'número con letras' => [fn (Invoice $i) => $i->setNumero('A-1'), 'DOC_004'],
            'fecha futura' => [fn (Invoice $i) => $i->setFecha(new \DateTimeImmutable('+3 days')), 'DOC_002'],
        ];
    }

    #[DataProvider('brokenInvoices')]
    public function testDetectsCommonRejectionCauses(callable $break, string $code): void
    {
        $invoice = DocumentFactory::invoice();
        $break($invoice);

        $codes = array_map(fn ($e) => $e->code, (new BusinessRuleValidator())->validate($invoice)->getErrors());
        $this->assertContains($code, $codes);
    }

    public function testNotesRequireConsistentReferenceAndConcept(): void
    {
        $note = DocumentFactory::creditNote()->setTipoOperacion('22');
        $codes = array_map(fn ($e) => $e->code, (new BusinessRuleValidator())->validate($note)->getErrors());
        $this->assertContains('NOTE_005', $codes);

        $note = DocumentFactory::creditNote(false);
        (new \ReflectionProperty($note, 'invoicePeriodStart'))->setValue($note, null);
        $codes = array_map(fn ($e) => $e->code, (new BusinessRuleValidator())->validate($note)->getErrors());
        $this->assertContains('NOTE_004', $codes);
    }

    public function testEnvironmentRules(): void
    {
        $invoice = DocumentFactory::invoice()->setTechnicalKey(BusinessRuleValidator::HABILITACION_TECHNICAL_KEY);
        $codes   = array_map(fn ($e) => $e->code, (new BusinessRuleValidator())->validate($invoice, '1')->getErrors());

        $this->assertContains('ENV_001', $codes);
        $this->assertContains('ENV_003', $codes);
    }

    public function testEmptyInvoiceFailsDoc001AndSoft001(): void
    {
        $validator = new BusinessRuleValidator();
        $invoice   = (new Invoice())
            ->setPrefijo('X')->setNumero('1')
            ->setFecha(new \DateTimeImmutable('-1 day'))
            ->setCompany((new Company())->setNit('900000000'));

        $result = $validator->validate($invoice);

        $codes = array_map(fn ($e) => $e->code, $result->getErrors());
        $this->assertContains('DOC_001', $codes);
        $this->assertContains('SOFT_001', $codes);
    }

    public function testTaxAmountMismatchTriggersTax002(): void
    {
        $invoice = $this->makeValidInvoice();
        // Sneak a wrong amount onto an existing tax
        $invoice->getItems()[0]->getTaxes()[0]->setAmount(1.0); // base*pct/100 would be 18050

        $result = (new BusinessRuleValidator())->validate($invoice);

        $codes = array_map(fn ($e) => $e->code, $result->getErrors());
        $this->assertContains('TAX_002', $codes);
    }

    public function testNumeroOutsideResolutionRangeTriggersRes001(): void
    {
        $invoice = $this->makeValidInvoice();
        $invoice->setNumero('9999999999');

        $result = (new BusinessRuleValidator())->validate($invoice);
        $codes  = array_map(fn ($e) => $e->code, $result->getErrors());
        $this->assertContains('RES_001', $codes);
    }

    private function makeValidInvoice(): Invoice
    {
        return DocumentFactory::invoice()->setTotal(0.0);
    }
}
