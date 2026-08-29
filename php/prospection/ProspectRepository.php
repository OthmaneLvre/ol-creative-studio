<?php

declare(strict_types=1);

final class ProspectRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Vérifie si un prospect provenant de cette source
     * existe déjà.
     */
    public function existsByExternalId(
        string $source,
        string $externalId
    ): bool {
        $sql = <<<SQL
            SELECT 1
            FROM prospects
            WHERE source = :source
              AND source_external_id = :external_id
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'source' => $source,
            'external_id' => $externalId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Vérification secondaire :
     * nom d'entreprise + code postal.
     */
    public function existsByCompanyAndPostalCode(
        string $companyName,
        ?string $postalCode
    ): bool {
        if ($postalCode === null || $postalCode === '') {
            return false;
        }

        $sql = <<<SQL
            SELECT 1
            FROM prospects
            WHERE LOWER(company_name) = LOWER(:company_name)
              AND postal_code = :postal_code
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'company_name' => trim($companyName),
            'postal_code' => $postalCode,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Insère un nouveau prospect découvert.
     *
     * Retourne son ID ou null lorsqu'il existe déjà.
     */
    public function createFromDiscovery(array $company): ?int
    {
        $source = trim((string) ($company['source'] ?? ''));

        $externalId = trim(
            (string) ($company['source_external_id'] ?? '')
        );

        if (
            $source !== ''
            && $externalId !== ''
            && $this->existsByExternalId($source, $externalId)
        ) {
            return null;
        }

        $companyName = trim(
            (string) ($company['company_name'] ?? '')
        );

        if ($companyName === '') {
            throw new InvalidArgumentException(
                'Le nom de l’entreprise est obligatoire.'
            );
        }

        $postalCode = isset($company['postal_code'])
            ? trim((string) $company['postal_code'])
            : null;

        if (
            $this->existsByCompanyAndPostalCode(
                $companyName,
                $postalCode
            )
        ) {
            return null;
        }

        try {
            $this->pdo->beginTransaction();

            $sql = <<<SQL
                INSERT INTO prospects (
                    company_name,
                    activity,
                    city,
                    postal_code,
                    website_url,
                    email,
                    phone,
                    source,
                    source_url,
                    source_external_id,
                    status
                ) VALUES (
                    :company_name,
                    :activity,
                    :city,
                    :postal_code,
                    :website_url,
                    :email,
                    :phone,
                    :source,
                    :source_url,
                    :source_external_id,
                    'discovered'
                )
            SQL;

            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                'company_name' => $companyName,
                'activity' => $company['activity'] ?? null,
                'city' => $company['city'] ?? null,
                'postal_code' => $postalCode,
                'website_url' => $company['website_url'] ?? null,
                'email' => $company['email'] ?? null,
                'phone' => $company['phone'] ?? null,
                'source' => $source !== '' ? $source : null,
                'source_url' => $company['source_url'] ?? null,
                'source_external_id' => $externalId !== ''
                    ? $externalId
                    : null,
            ]);

            $prospectId = (int) $this->pdo->lastInsertId();

            $this->addEvent(
                $prospectId,
                'prospect_discovered',
                null,
                'discovered',
                'Prospect découvert automatiquement.',
                [
                    'source' => $source !== '' ? $source : null,
                    'source_external_id' => $externalId !== ''
                        ? $externalId
                        : null,
                    'city' => $company['city'] ?? null,
                    'postal_code' => $postalCode,
                ]
            );

            $this->pdo->commit();

            return $prospectId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Enrichit un prospect existant avec les données
     * récupérées depuis Google Places.
     */
    public function updateEnrichment(
        int $prospectId,
        ?string $websiteUrl,
        ?string $phone,
        ?string $sourceUrl,
        ?string $placeId = null,
        ?string $address = null
    ): void {
        if ($prospectId <= 0) {
            throw new InvalidArgumentException(
                'Identifiant prospect invalide.'
            );
        }

        try {
            $this->pdo->beginTransaction();

            $sql = <<<SQL
                UPDATE prospects
                SET
                    website_url = COALESCE(:website_url, website_url),
                    phone = COALESCE(:phone, phone),
                    source_url = COALESCE(:source_url, source_url),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :prospect_id
            SQL;

            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                'website_url' => $this->nullableTrim($websiteUrl),
                'phone' => $this->nullableTrim($phone),
                'source_url' => $this->nullableTrim($sourceUrl),
                'prospect_id' => $prospectId,
            ]);

            if ($statement->rowCount() === 0) {
                $checkStatement = $this->pdo->prepare(
                    'SELECT 1 FROM prospects WHERE id = :id LIMIT 1'
                );

                $checkStatement->execute([
                    'id' => $prospectId,
                ]);

                if ($checkStatement->fetchColumn() === false) {
                    throw new RuntimeException(
                        'Le prospect à enrichir est introuvable.'
                    );
                }
            }

            $this->addEvent(
                $prospectId,
                'prospect_enriched',
                null,
                null,
                'Prospect enrichi avec des données publiques.',
                [
                    'provider' => 'google_places',
                    'website_url' => $this->nullableTrim($websiteUrl),
                    'phone' => $this->nullableTrim($phone),
                    'source_url' => $this->nullableTrim($sourceUrl),
                    'place_id' => $this->nullableTrim($placeId),
                    'address' => $this->nullableTrim($address),
                ]
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    private function addEvent(
        int $prospectId,
        string $eventType,
        ?string $previousStatus,
        ?string $newStatus,
        ?string $description = null,
        ?array $metadata = null
    ): void {
        $sql = <<<SQL
            INSERT INTO prospect_events (
                prospect_id,
                event_type,
                previous_status,
                new_status,
                description,
                metadata
            ) VALUES (
                :prospect_id,
                :event_type,
                :previous_status,
                :new_status,
                :description,
                :metadata
            )
        SQL;

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'prospect_id' => $prospectId,
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'description' => $description,
            'metadata' => $metadata !== null
                ? json_encode($metadata, JSON_THROW_ON_ERROR)
                : null,
        ]);
    }
}
