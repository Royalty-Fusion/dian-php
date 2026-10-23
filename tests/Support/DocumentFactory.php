<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Support;

use RoyaltyFusion\DianPhp\Model\AdditionalDocumentReference;
use RoyaltyFusion\DianPhp\Model\Address;
use RoyaltyFusion\DianPhp\Model\AllowanceCharge;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Contact;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Prepayment;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;

/**
 * Realistic documents shared by several tests: special characters, line
 * discounts, IVA + INC, retentions, prepayments, global charges, credit terms.
 */
final class DocumentFactory
{
    public static function company(): Company
    {
        return (new Company())
            ->setNit('900123456')
            ->setRazonSocial('Pérez & Cía <Tecnología> S.A.S.')
            ->setCommercialName('Pérez & Cía')
            ->setTipoDocumento('31')
            ->setRegimen('48')
            ->setResponsabilidades('O-13;O-15')
            ->setTipoOrganizacion('1')
            ->setIndustryClassificationCode('6201')
            ->setMatriculaMercantil('123456')
            ->setAddress(self::address('Cra. 7 # 71-21 "Torre A"'))
            ->setContact((new Contact())->setName('Facturación')->setTelephone('6015550000')->setElectronicMail('facturacion@example.test'));
    }

    public static function client(): Client
    {
        return (new Client())
            ->setTipoDocumento('31')
            ->setNumeroDocumento('800199436')
            ->setRazonSocial('Cliente Ñandú & Hijos Ltda.')
            ->setEmail('cliente@example.test')
            ->setRegimen('48')
            ->setResponsabilidades('O-13')
            ->setTipoOrganizacion('1')
            ->setAddress(self::address('Calle 10 # 5-20'));
    }

    public static function address(string $line): Address
    {
        return (new Address())
            ->setLine($line)
            ->setCityCode('11001')->setCityName('Bogotá, D.C.')
            ->setPostalZone('110111')
            ->setDepartmentCode('11')->setDepartmentName('Bogotá')
            ->setCountryCode('CO')->setCountryName('Colombia');
    }

    public static function software(): Software
    {
        return (new Software())
            ->setId('fc8eac42-2eba-16e2-2ffd-8c6f94b3f40a')
            ->setPin('12345')
            ->setProviderNit('900123456');
    }

    public static function resolution(): Resolution
    {
        return (new Resolution())
            ->setNumber('18760000001')
            ->setPrefix('SETP')
            ->setFrom('990000000')
            ->setTo('995000000')
            ->setDateFrom(new \DateTimeImmutable('2019-01-19'))
            ->setDateTo(new \DateTimeImmutable('2030-01-19'));
    }

    public static function invoice(): Invoice
    {
        $iva = (new Tax())->setCode('01')->setName('IVA')->setPercent(19);
        $inc = (new Tax())->setCode('04')->setName('INC')->setPercent(8);
        $reteFuente = (new Tax())->setCode('06')->setName('ReteRenta')->setPercent(2.5)->setIsRetention(true);

        $item1 = (new Item())
            ->setDescripcion('Licencia "Pro" <anual> & soporte')
            ->setCantidad(2)
            ->setPrecio(50000)
            ->setUnitCode('94')
            ->setCode('SKU-001')
            ->setBrandName('Marca & Co')
            ->setModelName('M-1')
            ->setSellersItemIdentification('SKU-001')
            ->addAllowanceCharge((new AllowanceCharge())->setChargeIndicator(false)->setReasonCode('11')->setReason('Descuento')->setAmount(5000)->setBaseAmount(100000))
            ->addTax((clone $iva)->setBase(95000))
            ->addTax((clone $reteFuente)->setBase(95000));

        $item2 = (new Item())
            ->setDescripcion('Almuerzo ejecutivo')
            ->setCantidad(3)
            ->setPrecio(10000.333)
            ->addTax((clone $inc)->setBase(30001.0));

        return (new Invoice())
            ->setPrefijo('SETP')
            ->setNumero('990000001')
            ->setFecha(new \DateTimeImmutable('2026-05-29T10:00:00-05:00'))
            ->setDueDate(new \DateTimeImmutable('2026-06-28'))
            ->setCompany(self::company())
            ->setClient(self::client())
            ->setSoftware(self::software())
            ->setResolution(self::resolution())
            ->setTechnicalKey('fc8eac422eba16e22ffd8c6f94b3f40a6e38162c')
            ->addNote('Observación con & y <etiquetas>')
            ->addAdditionalDocumentReference((new AdditionalDocumentReference())->setType('OrderReference')->setId('OC-77'))
            ->addPayment((new Payment())->setMethodId('2')->setMeansId('42')->setDueDate(new \DateTimeImmutable('2026-06-28')))
            ->addPrepayment((new Prepayment())->setId('ANT-1')->setPaidAmount(10000)->setPaidDate(new \DateTimeImmutable('2026-05-20')))
            ->addAllowanceCharge((new AllowanceCharge())->setChargeIndicator(true)->setReason('Flete')->setAmount(2000)->setBaseAmount(125001))
            ->addItem($item1)
            ->addItem($item2);
    }

    public static function creditNote(bool $withReference = true): CreditNote
    {
        $note = (new CreditNote())
            ->setPrefijo('NC')
            ->setNumero('1')
            ->setFecha(new \DateTimeImmutable('2026-05-30T09:00:00-05:00'))
            ->setCompany(self::company())
            ->setClient(self::client())
            ->setSoftware(self::software())
            ->setDiscrepancyResponse((new DiscrepancyResponse())->setReferenceId('SETP990000001')->setResponseCode('2')->setDescription('Anulación & devolución'))
            ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))
            ->addItem(
                (new Item())->setDescripcion('Devolución licencia')->setCantidad(1)->setPrecio(50000)
                    ->addTax((new Tax())->setCode('01')->setPercent(19)->setBase(50000))
            );
        if ($withReference) {
            $note->setBillingReference(
                (new BillingReference())->setNumber('SETP990000001')->setUuid(str_repeat('a', 96))->setDate(new \DateTimeImmutable('2026-05-29'))
            );
        } else {
            $note->setInvoicePeriod(new \DateTimeImmutable('2026-05-01'), new \DateTimeImmutable('2026-05-31'));
        }

        return $note;
    }

    public static function debitNote(): DebitNote
    {
        return (new DebitNote())
            ->setPrefijo('ND')
            ->setNumero('1')
            ->setFecha(new \DateTimeImmutable('2026-05-30T09:00:00-05:00'))
            ->setCompany(self::company())
            ->setClient(self::client())
            ->setSoftware(self::software())
            ->setBillingReference(
                (new BillingReference())->setNumber('SETP990000001')->setUuid(str_repeat('a', 96))->setDate(new \DateTimeImmutable('2026-05-29'))
            )
            ->setDiscrepancyResponse((new DiscrepancyResponse())->setReferenceId('SETP990000001')->setResponseCode('1')->setDescription('Intereses'))
            ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))
            ->addItem(
                (new Item())->setDescripcion('Intereses de mora')->setCantidad(1)->setPrecio(1000)
                    ->addTax((new Tax())->setCode('01')->setPercent(19)->setBase(1000))
            );
    }
}
