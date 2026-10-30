<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

use RoyaltyFusion\DianPhp\Catalog\TipoDocumentoEquivalente;

/**
 * Documento equivalente electrónico (Resolución DIAN 000165 de 2023), e.g.
 * the tiquete de máquina registradora con sistema P.O.S. (tipo 20).
 *
 * It is an UBL Invoice with:
 *   - cbc:InvoiceTypeCode = tipo de documento equivalente (20 POS, 60 SPD, …)
 *   - CUDE (computed with the PIN of the documento equivalente software,
 *     not with a clave técnica)
 *   - numbering control of its own resolution
 *   - for POS, three extra UBLExtensions: FabricanteSoftware,
 *     BeneficiosComprador and PuntoVenta (información de la caja)
 *
 * Sales to people who do not ask for identification go to the consumidor
 * final: use {@see Client::consumidorFinal()}.
 */
class EquivalentDocument extends Invoice
{
    /** ProfileID per tipo, as used in production by DIAN-enabled software. */
    private const PROFILES = [
        '20' => 'DIAN 2.1: Documento Equivalente POS',
        '35' => 'DIAN 2.1: Documento Equivalente Tiquete de Transporte Terrestre de Pasajeros',
        '60' => 'DIAN 2.1: Documento Equivalente SPD',
    ];

    private string $tipo = '20';
    private ?string $profileId = null;
    /** @var array<string,string>|null */
    private ?array $fabricanteSoftware = null;
    /** @var array<string,string>|null */
    private ?array $beneficiosComprador = null;
    /** @var array<string,string>|null */
    private ?array $puntoVenta = null;

    public function __construct(TipoDocumentoEquivalente|string $tipo = TipoDocumentoEquivalente::POS)
    {
        $this->setTipo($tipo);
    }

    public function setTipo(TipoDocumentoEquivalente|string $tipo): self
    {
        $this->tipo = $tipo instanceof TipoDocumentoEquivalente ? $tipo->value : $tipo;
        return $this;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function getInvoiceTypeCode(): string
    {
        return $this->tipo;
    }

    public function isPos(): bool
    {
        return $this->tipo === TipoDocumentoEquivalente::POS->value;
    }

    /** Overrides the cbc:ProfileID (types without a known profile use a generic one). */
    public function setProfileId(string $profileId): self
    {
        $this->profileId = $profileId;
        return $this;
    }

    public function getProfileId(): string
    {
        return $this->profileId ?? self::PROFILES[$this->tipo] ?? 'DIAN 2.1: Documento Equivalente Electrónico';
    }

    public function getUuidSchemeName(): string
    {
        return 'CUDE-SHA384';
    }

    /** Fabricante del software POS (persona y razón social) y nombre del software. */
    public function setFabricanteSoftware(string $nombreApellido, string $razonSocial, string $nombreSoftware): self
    {
        $this->fabricanteSoftware = [
            'NombreApellido' => $nombreApellido,
            'RazonSocial'    => $razonSocial,
            'NombreSoftware' => $nombreSoftware,
        ];
        return $this;
    }

    /** Programa de beneficios del comprador (puntos acumulados). */
    public function setBeneficiosComprador(string $codigo, string $nombresApellidos, string|int|float $puntos): self
    {
        $this->beneficiosComprador = [
            'Codigo'           => $codigo,
            'NombresApellidos' => $nombresApellidos,
            'Puntos'           => (string) $puntos,
        ];
        return $this;
    }

    /**
     * Información de la caja de venta.
     *
     * @param float|null $subtotal null = valor bruto calculado del documento
     */
    public function setPuntoVenta(
        string $placaCaja,
        string $ubicacionCaja,
        string $cajero,
        string $tipoCaja,
        string $codigoVenta,
        ?float $subtotal = null
    ): self {
        $this->puntoVenta = [
            'PlacaCaja'     => $placaCaja,
            'UbicaciónCaja' => $ubicacionCaja,
            'Cajero'        => $cajero,
            'TipoCaja'      => $tipoCaja,
            'CódigoVenta'   => $codigoVenta,
            'SubTotal'      => $subtotal === null ? '' : number_format($subtotal, 2, '.', ''),
        ];
        return $this;
    }

    /** @return array<string,string>|null */
    public function getFabricanteSoftware(): ?array { return $this->fabricanteSoftware; }
    /** @return array<string,string>|null */
    public function getBeneficiosComprador(): ?array { return $this->beneficiosComprador; }
    /** @return array<string,string>|null */
    public function getPuntoVenta(): ?array { return $this->puntoVenta; }

    /** POS extensions first, then any extension added with addExtension(). */
    public function getExtensions(): array
    {
        $extensions = [];
        if ($this->fabricanteSoftware !== null) {
            $extensions[] = CustomExtension::nameValues('FabricanteSoftware', 'InformacionDelFabricanteDelSoftware', $this->fabricanteSoftware);
        }
        if ($this->beneficiosComprador !== null) {
            $extensions[] = CustomExtension::nameValues('BeneficiosComprador', 'InformacionBeneficiosComprador', $this->beneficiosComprador);
        }
        if ($this->puntoVenta !== null) {
            $caja = $this->puntoVenta;
            if ($caja['SubTotal'] === '') {
                $caja['SubTotal'] = number_format(\RoyaltyFusion\DianPhp\Xml\DocumentCalculator::totals($this)['lineExtensionAmount'], 2, '.', '');
            }
            $extensions[] = CustomExtension::nameValues('PuntoVenta', 'InformacionCajaVenta', $caja);
        }

        return array_merge($extensions, parent::getExtensions());
    }
}
