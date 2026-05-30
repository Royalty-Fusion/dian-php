<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\AdditionalDocumentReference;
use RoyaltyFusion\DianPhp\Model\Address;
use RoyaltyFusion\DianPhp\Model\AllowanceCharge;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Contact;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Note;
use RoyaltyFusion\DianPhp\Model\Prepayment;

final class RichDocumentFieldsTest extends TestCase
{
    public function testCompanyAcceptsAddressContactAndIndustryCode(): void
    {
        $address = (new Address())
            ->setLine('Calle 100 # 10-20')
            ->setCityCode('11001')
            ->setCityName('Bogotá D.C.')
            ->setDepartmentCode('11')
            ->setDepartmentName('Bogotá')
            ->setCountryCode('CO')
            ->setCountryName('Colombia');

        $contact = (new Contact())
            ->setName('Daniel Muñoz')
            ->setTelephone('+57 300 0000000')
            ->setElectronicMail('contacto@example.test');

        $company = (new Company())
            ->setNit('900123456')
            ->setIndustryClassificationCode('6201')
            ->setMunicipalityCode('11001');

        $company->setAddress($address);
        $company->setContact($contact);

        $this->assertSame($address, $company->getAddress());
        $this->assertSame($contact, $company->getContact());
        $this->assertSame('6201',   $company->getIndustryClassificationCode());
        $this->assertSame('11001',  $company->getMunicipalityCode());
        $this->assertSame(900123456, (int) $company->getNit());
        $this->assertGreaterThanOrEqual(0, $company->getNitDv());
        $this->assertLessThanOrEqual(9, $company->getNitDv());
    }

    public function testClientAcceptsAddress(): void
    {
        $client = (new Client())->setNumeroDocumento('1010101010');
        $client->setAddress((new Address())->setLine('Cra 7 # 71-21')->setCityName('Bogotá D.C.'));
        $this->assertSame('Cra 7 # 71-21', $client->getAddress()->getLine());
    }

    public function testInvoiceAccumulatesAllowanceChargesPrepaymentsNotes(): void
    {
        $invoice = new Invoice();

        $discount = (new AllowanceCharge())
            ->setId(1)
            ->setChargeIndicator(false)
            ->setReasonCode('00')
            ->setReason('Descuento general')
            ->setAmount(5000.00)
            ->setBaseAmount(100000.00);

        $charge = (new AllowanceCharge())
            ->setId(2)
            ->setChargeIndicator(true)
            ->setReasonCode('01')
            ->setReason('Cargo logístico')
            ->setAmount(2500.00)
            ->setBaseAmount(100000.00);

        $prepayment = (new Prepayment())
            ->setId('ANT-001')
            ->setPaidAmount(20000.00)
            ->setPaidDate(new \DateTimeImmutable('2026-05-20'));

        $invoice
            ->addAllowanceCharge($discount)
            ->addAllowanceCharge($charge)
            ->addPrepayment($prepayment)
            ->addAdditionalDocumentReference(
                (new AdditionalDocumentReference())
                    ->setType('OrderReference')
                    ->setId('OC-12345')
                    ->setIssueDate(new \DateTimeImmutable('2026-05-15'))
            )
            ->addNote('Observación de prueba')
            ->addNote(new Note('Segunda nota'));

        $this->assertCount(2, $invoice->getAllowanceCharges());
        $this->assertTrue($invoice->getAllowanceCharges()[0]->isDiscount());
        $this->assertTrue($invoice->getAllowanceCharges()[1]->isCharge());
        $this->assertCount(1, $invoice->getPrepayments());
        $this->assertCount(1, $invoice->getAdditionalDocumentReferences());
        $this->assertCount(2, $invoice->getNotes());
        $this->assertSame('Observación de prueba', $invoice->getNotes()[0]->getText());
    }
}
