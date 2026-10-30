<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Numbering;

use RoyaltyFusion\DianPhp\Model\Resolution;

/**
 * Consecutivos de facturación: hands out the next number of a DIAN numbering
 * resolution, refusing numbers outside the authorized range or validity, and
 * warns before the range runs out or expires (request a new one in the
 * MUISCA in time: DIAN rejects numbers outside an authorized range).
 *
 *   $numbering = new NumberingManager(new PdoNumberingStore($pdo));
 *   $invoice->setNumero($numbering->next($resolution));
 *   foreach ($numbering->alerts($resolution) as $alert) { … }
 */
final class NumberingManager
{
    public function __construct(
        private readonly NumberingStore $store,
        private readonly int $minRemaining = 100,
        private readonly int $minDays = 30,
    ) {
    }

    /**
     * Next consecutive (without prefix) for the resolution.
     *
     * @throws NumberingException when the range is exhausted or the resolution is not in force on $date
     */
    public function next(Resolution $resolution, ?\DateTimeInterface $date = null): string
    {
        [$from, $to] = self::range($resolution);
        $day = self::day($date);
        if ($resolution->getDateFrom() !== null && $day < $resolution->getDateFrom()->format('Y-m-d')) {
            throw new NumberingException("La resolución {$resolution->getNumber()} rige desde {$resolution->getDateFrom()->format('Y-m-d')}.");
        }
        if ($resolution->getDateTo() !== null && $day > $resolution->getDateTo()->format('Y-m-d')) {
            throw new NumberingException("La resolución {$resolution->getNumber()} venció el {$resolution->getDateTo()->format('Y-m-d')}: solicite una nueva en la DIAN.");
        }

        $number = $this->store->next(self::key($resolution), $from, $to);
        if ($number === null) {
            throw new NumberingException("Se agotó el rango {$resolution->getPrefix()}{$from}-{$to} de la resolución {$resolution->getNumber()}: solicite una nueva en la DIAN.");
        }

        return (string) $number;
    }

    /** Sets the last number already used (migration from another software). */
    public function setLastUsed(Resolution $resolution, int $last): void
    {
        $this->store->set(self::key($resolution), $last);
    }

    /**
     * @return array{prefix:string,from:int,to:int,last:?int,remaining:int,used_percent:float,expires:?string,days_left:?int}
     */
    public function status(Resolution $resolution, ?\DateTimeInterface $date = null): array
    {
        [$from, $to] = self::range($resolution);
        $last      = $this->store->current(self::key($resolution));
        $used      = $last === null ? 0 : max(0, min($last, $to) - $from + 1);
        $total     = $to - $from + 1;
        $expires   = $resolution->getDateTo()?->format('Y-m-d');
        $daysLeft  = $expires === null ? null
            : (int) (new \DateTimeImmutable(self::day($date)))->diff(new \DateTimeImmutable($expires))->format('%r%a');

        return [
            'prefix'       => $resolution->getPrefix(),
            'from'         => $from,
            'to'           => $to,
            'last'         => $last,
            'remaining'    => $total - $used,
            'used_percent' => round($used * 100 / $total, 2),
            'expires'      => $expires,
            'days_left'    => $daysLeft,
        ];
    }

    /**
     * Human readable warnings (Spanish): range about to run out or resolution
     * about to expire / expired. Empty when everything is fine.
     *
     * @return list<string>
     */
    public function alerts(Resolution $resolution, ?\DateTimeInterface $date = null): array
    {
        $s      = $this->status($resolution, $date);
        $name   = "Resolución {$resolution->getNumber()} ({$s['prefix']}{$s['from']}-{$s['to']})";
        $alerts = [];
        if ($s['remaining'] === 0) {
            $alerts[] = "{$name}: rango agotado.";
        } elseif ($s['remaining'] <= $this->minRemaining) {
            $alerts[] = "{$name}: quedan {$s['remaining']} números ({$s['used_percent']}% usado).";
        }
        if ($s['days_left'] !== null && $s['days_left'] < 0) {
            $alerts[] = "{$name}: vencida desde {$s['expires']}.";
        } elseif ($s['days_left'] !== null && $s['days_left'] <= $this->minDays) {
            $alerts[] = "{$name}: vence en {$s['days_left']} días ({$s['expires']}).";
        }

        return $alerts;
    }

    private static function key(Resolution $resolution): string
    {
        return $resolution->getNumber() . '|' . $resolution->getPrefix() . '|' . $resolution->getFrom() . '-' . $resolution->getTo();
    }

    /** @return array{int,int} */
    private static function range(Resolution $resolution): array
    {
        $from = (int) $resolution->getFrom();
        $to   = (int) $resolution->getTo();
        if ($resolution->getFrom() === '' || $resolution->getTo() === '' || $from < 0 || $to < $from) {
            throw new NumberingException("Rango inválido en la resolución {$resolution->getNumber()}: {$resolution->getFrom()}-{$resolution->getTo()}.");
        }

        return [$from, $to];
    }

    private static function day(?\DateTimeInterface $date): string
    {
        $date = $date === null
            ? new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'))
            : \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('America/Bogota'));

        return $date->format('Y-m-d');
    }
}
