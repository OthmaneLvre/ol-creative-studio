<?php

declare(strict_types=1);

final class ProspectScheduler
{
    private string $sendTime;
    private int $maxDailySends;
    private bool $businessDaysOnly;

    public function __construct(
        array $config
    ) {
        $sending = $config['sending'] ?? [];

        $this->sendTime =
            (string) ($sending['send_time'] ?? '09:30');

        $this->maxDailySends =
            (int) ($sending['max_daily_sends'] ?? 5);

        $this->businessDaysOnly =
            (bool) ($sending['business_days_only'] ?? true);

        if (
            !preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $this->sendTime
            )
        ) {
            throw new InvalidArgumentException(
                'Heure d’envoi invalide.'
            );
        }

        if ($this->maxDailySends < 1) {
            throw new InvalidArgumentException(
                'La limite quotidienne doit être supérieure à zéro.'
            );
        }
    }

    /**
     * Calcule le premier créneau disponible pour un prospect validé.
     *
     * @param callable $countScheduledForDate
     *        Fonction recevant une date Y-m-d et retournant le nombre
     *        de premiers e-mails déjà programmés ce jour-là.
     */
    public function findNextAvailableSlot(
        DateTimeImmutable $approvedAt,
        callable $countScheduledForDate
    ): DateTimeImmutable {
        $timezone = $approvedAt->getTimezone();

        [$hour, $minute] = array_map(
            'intval',
            explode(':', $this->sendTime)
        );

        /*
        |--------------------------------------------------------------------------
        | Premier jour candidat
        |--------------------------------------------------------------------------
        |
        | Le cahier des charges impose un envoi le prochain jour ouvré
        | après validation. On ne programme donc jamais le jour même.
        |
        */

        $candidate = $approvedAt
            ->modify('+1 day')
            ->setTime($hour, $minute, 0);

        /*
        |--------------------------------------------------------------------------
        | Recherche du premier jour disposant encore d'une place
        |--------------------------------------------------------------------------
        */

        for ($i = 0; $i < 366; $i++) {
            if (
                $this->businessDaysOnly
                && !$this->isBusinessDay($candidate)
            ) {
                $candidate = $candidate
                    ->modify('+1 day')
                    ->setTime($hour, $minute, 0);

                continue;
            }

            $date = $candidate->format('Y-m-d');

            $scheduledCount =
                (int) $countScheduledForDate($date);

            if ($scheduledCount < $this->maxDailySends) {
                return $candidate;
            }

            $candidate = $candidate
                ->modify('+1 day')
                ->setTime($hour, $minute, 0);
        }

        throw new RuntimeException(
            'Aucun créneau d’envoi disponible dans les 366 prochains jours.'
        );
    }

    private function isBusinessDay(
        DateTimeImmutable $date
    ): bool {
        $dayOfWeek = (int) $date->format('N');

        return $dayOfWeek >= 1
            && $dayOfWeek <= 5;
    }
}
