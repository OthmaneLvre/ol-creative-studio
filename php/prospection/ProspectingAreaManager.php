<?php

declare(strict_types=1);

final class ProspectingAreaManager
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Retourne la prochaine zone active à prospecter.
     *
     * Priorité :
     * 1. zones jamais analysées ;
     * 2. zone analysée le moins récemment.
     */
    public function getNextArea(): ?array
    {
        $sql = <<<SQL
            SELECT
                id,
                city,
                postal_code,
                department,
                latitude,
                longitude,
                is_active,
                last_scanned_at
            FROM prospecting_areas
            WHERE is_active = 1
            ORDER BY
                last_scanned_at IS NOT NULL ASC,
                last_scanned_at ASC,
                id ASC
            LIMIT 1
        SQL;

        $statement = $this->pdo->query($sql);

        $area = $statement->fetch(PDO::FETCH_ASSOC);

        return $area !== false ? $area : null;
    }

    /**
     * Marque une zone comme venant d'être analysée.
     */
    public function markAsScanned(int $areaId): bool
    {
        $sql = <<<SQL
            UPDATE prospecting_areas
            SET last_scanned_at = CURRENT_TIMESTAMP
            WHERE id = :id
        SQL;

        $statement = $this->pdo->prepare($sql);

        return $statement->execute([
            'id' => $areaId,
        ]);
    }

    /**
     * Retourne toutes les zones actives.
     */
    public function getActiveAreas(): array
    {
        $sql = <<<SQL
            SELECT
                id,
                city,
                postal_code,
                department,
                latitude,
                longitude,
                last_scanned_at
            FROM prospecting_areas
            WHERE is_active = 1
            ORDER BY
                city ASC,
                postal_code ASC
        SQL;

        $statement = $this->pdo->query($sql);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
