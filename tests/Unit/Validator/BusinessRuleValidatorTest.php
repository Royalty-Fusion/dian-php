<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Validator;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
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
        $taxes = $invoice->getTaxes();
        $taxes[0]->setAmount(1.0); // base*pct/100 would be 19000

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
        $tax = (new Tax())
            ->setCode('01')->setName('IVA')->setPercent(19.0)
            ->setBase(100000.0)->setAmount(19000.0);

        $item = (new Item())
            ->setDescripcion('Demo')
            ->setCantidad(1.0)
            ->setPrecio(100000.0)
            ->addTax($tax);

        return (new Invoice())
            ->setPrefijo('SETT')->setNumero('990000001')
            ->setFecha(new \DateTimeImmutable('2026-05-15'))
            ->setCompany(
                (new Company())
                    ->setNit('800197268')
                    ->setRazonSocial('Demo S.A.S')
                    ->setTipoDocumento('31')
            )
            ->setClient(
                (new Client())
                    ->setTipoDocumento('13')
                    ->setNumeroDocumento('1010101010')
                    ->setRazonSocial('Cliente')
            )
            ->setSoftware(
                (new Software())
                    ->setId('uuid-1')->setPin('00000')->setProviderNit('800197268')
            )
            ->setResolution(
                (new Resolution())
                    ->setNumber('18760000001')
                    ->setPrefix('SETT')
                    ->setFrom('990000000')
                    ->setTo('995000000')
                    ->setDateFrom(new \DateTimeImmutable('2026-01-01'))
                    ->setDateTo(new \DateTimeImmutable('2026-12-31'))
            )
            ->setTotal(119000.0)
            ->addItem($item)
            ->addTax($tax);
    }
}
