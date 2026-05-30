<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

/**
 * Nómina Electrónica de Pago — placeholder model.
 *
 * The NIE schema is fundamentally different from UBL (it uses the
 * NominaIndividual / NominaIndividualDeAjuste XSDs from DIAN) and
 * therefore lives in its own subtree. It also targets a different WS
 * endpoint (apifedi.dian.gov.co/Nomina).
 *
 * TODO (Phase 7 follow-up):
 *   - Devengados model (Basico, Transporte, HEDs/HENs, Vacaciones, etc.)
 *   - Deducciones model (Salud, Pensión, FSP, Sindicato, Libranzas, ...)
 *   - PayrollXmlBuilder + nomina-individual.xml.twig
 *   - PayrollAdjustment (reemplazo / eliminación)
 *   - CuneGenerator (SHA-384 against the NIE-specific concatenation string)
 *   - PayrollSoapClient against the production / habilitación endpoints
 */
class PayrollDocument
{
    private string $cune = '';

    public function getCune(): string
    {
        return $this->cune;
    }

    public function setCune(string $cune): self
    {
        $this->cune = $cune;
        return $this;
    }
}
