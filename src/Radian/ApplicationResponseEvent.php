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
 * TODO (Phase 8 follow-up):
 *   - ApplicationResponse Twig template
 *   - ApplicationResponseSigner (separate cert from the supplier's)
 *   - RadianSoapClient.sendEventUpdateStatus()
 *   - Listener helpers to flag transitions on the linked Invoice
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

    private string $code = '';
    private string $cufe = '';
    private string $description = '';

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
