<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Radian;

/**
 * RADIAN — Eventos sobre Factura Electrónica como Título Valor.
 *
 * Each event is sent as an ApplicationResponse signed by the *receiver*
 * (not the original issuer) and registered against the CUFE.
 *
 * Event codes (Anexo Técnico RADIAN):
 *   030  Acuse de recibo
 *   031  Reclamo / Rechazo de la factura
 *   032  Recibo del bien o prestación del servicio
 *   033  Aceptación expresa
 *   034  Aceptación tácita
 *   035  Mandato
 *   036  Endoso en propiedad
 *   037  Endoso en garantía
 *   038  Endoso en procuración
 *   039  Limitación de circulación
 *   040  Notificación de pago
 *   042  Pago de la factura
 *   045  Avales
 *
 * Build them with the named constructors (acuseRecibo(), reclamo()…) and send
 * them with Dian::sendEvent() or, for a received invoice, Dian::acknowledge().
 */
class ApplicationResponseEvent
{
    public const ACUSE_RECIBO          = '030';
    public const RECLAMO               = '031';
    public const RECIBO_BIEN_SERVICIO  = '032';
    public const ACEPTACION_EXPRESA    = '033';
    public const ACEPTACION_TACITA     = '034';
    public const MANDATO               = '035';
    public const ENDOSO_PROPIEDAD      = '036';
    public const ENDOSO_GARANTIA       = '037';
    public const ENDOSO_PROCURACION    = '038';
    public const LIMITACION_CIRCULACION = '039';
    public const NOTIFICACION_PAGO     = '040';
    public const PAGO                  = '042';
    public const AVALES                = '045';

    /** Conceptos de reclamo (evento 031), cbc:ResponseCode/@listID. */
    public const RECLAMOS = [
        '01' => 'Documento con inconsistencias',
        '02' => 'Mercancía no entregada totalmente',
        '03' => 'Mercancía no entregada parcialmente',
        '04' => 'Servicio no prestado',
    ];

    private const DESCRIPTIONS = [
        self::ACUSE_RECIBO         => 'Acuse de recibo de Factura Electrónica de Venta',
        self::RECLAMO              => 'Reclamo de la Factura Electrónica de Venta',
        self::RECIBO_BIEN_SERVICIO => 'Recibo del bien y/o prestación del servicio',
        self::ACEPTACION_EXPRESA   => 'Aceptación expresa',
        self::ACEPTACION_TACITA    => 'Aceptación tácita',
    ];

    private string $code = '';
    private string $cufe = '';
    private string $description = '';
    private string $rejectionCode = '';

    public function __construct(string $code = '', string $description = '')
    {
        $this->code        = $code;
        $this->description = $description !== '' ? $description : (self::DESCRIPTIONS[$code] ?? '');
    }

    public static function acuseRecibo(): self
    {
        return new self(self::ACUSE_RECIBO);
    }

    /** @param string $concepto key of RECLAMOS (01..04) */
    public static function reclamo(string $concepto): self
    {
        if (!isset(self::RECLAMOS[$concepto])) {
            throw new \InvalidArgumentException("Concepto de reclamo '{$concepto}' inválido (01, 02, 03, 04).");
        }
        $event = new self(self::RECLAMO);
        $event->rejectionCode = $concepto;

        return $event;
    }

    public static function reciboBienServicio(): self
    {
        return new self(self::RECIBO_BIEN_SERVICIO);
    }

    public static function aceptacionExpresa(): self
    {
        return new self(self::ACEPTACION_EXPRESA);
    }

    public static function aceptacionTacita(): self
    {
        return new self(self::ACEPTACION_TACITA);
    }

    /** Concepto del reclamo (01..04) for event 031. */
    public function getRejectionCode(): string
    {
        return $this->rejectionCode;
    }

    public function getRejectionName(): string
    {
        return self::RECLAMOS[$this->rejectionCode] ?? '';
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCufe(string $cufe): self
    {
        $this->cufe = $cufe;
        return $this;
    }

    public function getCufe(): string
    {
        return $this->cufe;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
