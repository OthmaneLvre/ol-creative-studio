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

    /**
     * Enregistre un e-mail professionnel public
     * détecté sur le site du prospect.
     */
    public function updateEmailDiscovery(
        int $prospectId,
        string $email,
        string $emailType,
        string $sourceUrl,
        int $confidence = 100
    ): void {
        if ($prospectId <= 0) {
            throw new InvalidArgumentException(
                'Identifiant prospect invalide.'
            );
        }

        $email = strtolower(trim($email));
        $emailType = strtolower(trim($emailType));
        $sourceUrl = trim($sourceUrl);

        if (
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'Adresse e-mail invalide.'
            );
        }

        $allowedTypes = [
            'generic',
            'personal',
            'unknown',
        ];

        if (!in_array($emailType, $allowedTypes, true)) {
            throw new InvalidArgumentException(
                'Type d’adresse e-mail invalide.'
            );
        }

        if ($sourceUrl === '') {
            throw new InvalidArgumentException(
                'La source de l’adresse e-mail est obligatoire.'
            );
        }

        if (
            filter_var(
                $sourceUrl,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'URL source de l’adresse e-mail invalide.'
            );
        }

        if ($confidence < 0 || $confidence > 100) {
            throw new InvalidArgumentException(
                'Le niveau de confiance doit être compris entre 0 et 100.'
            );
        }

        try {
            $this->pdo->beginTransaction();

            $sql = <<<SQL
                UPDATE prospects
                SET
                    email = :email,
                    email_source_url = :email_source_url,
                    email_type = :email_type,
                    email_confidence = :email_confidence,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :prospect_id
            SQL;

            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                'email' => $email,
                'email_source_url' => $sourceUrl,
                'email_type' => $emailType,
                'email_confidence' => $confidence,
                'prospect_id' => $prospectId,
            ]);

            if ($statement->rowCount() === 0) {
                $checkStatement = $this->pdo->prepare(
                    'SELECT 1
                    FROM prospects
                    WHERE id = :id
                    LIMIT 1'
                );

                $checkStatement->execute([
                    'id' => $prospectId,
                ]);

                if ($checkStatement->fetchColumn() === false) {
                    throw new RuntimeException(
                        'Le prospect est introuvable.'
                    );
                }
            }

            $this->addEvent(
                $prospectId,
                'prospect_email_found',
                null,
                null,
                'Adresse e-mail professionnelle publique détectée.',
                [
                    'email' => $email,
                    'email_type' => $emailType,
                    'email_source_url' => $sourceUrl,
                    'email_confidence' => $confidence,
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

    /**
     * Enregistre l'analyse technique du site d'un prospect.
     */
    public function saveWebsiteAnalysis(
        int $prospectId,
        array $analysis
    ): int {
        if ($prospectId <= 0) {
            throw new InvalidArgumentException(
                'Identifiant prospect invalide.'
            );
        }

        $requiredKeys = [
            'website_reachable',
            'has_ssl',
            'is_mobile_friendly',
            'has_contact_page',
            'has_contact_form',
            'has_visible_email',
            'has_visible_phone',
            'has_social_links',
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $analysis)) {
                throw new InvalidArgumentException(
                    'Donnée d’analyse manquante : ' . $key
                );
            }
        }

        try {
            $this->pdo->beginTransaction();

            $statusQuery = $this->pdo->prepare(
                'SELECT status
                FROM prospects
                WHERE id = :prospect_id
                LIMIT 1
                FOR UPDATE'
            );

            $statusQuery->execute([
                'prospect_id' => $prospectId,
            ]);

            $previousStatus = $statusQuery->fetchColumn();

            if ($previousStatus === false) {
                throw new RuntimeException(
                    'Le prospect est introuvable.'
                );
            }

            /*
            * On conserve une seule analyse active par prospect
            * pour cette V1.
            */
            $deleteStatement = $this->pdo->prepare(
                'DELETE FROM prospect_analyses
                WHERE prospect_id = :prospect_id'
            );

            $deleteStatement->execute([
                'prospect_id' => $prospectId,
            ]);

            $sql = <<<SQL
                INSERT INTO prospect_analyses (
                    prospect_id,
                    website_reachable,
                    has_ssl,
                    is_mobile_friendly,
                    has_contact_page,
                    has_social_links,
                    has_visible_email,
                    raw_analysis
                ) VALUES (
                    :prospect_id,
                    :website_reachable,
                    :has_ssl,
                    :is_mobile_friendly,
                    :has_contact_page,
                    :has_social_links,
                    :has_visible_email,
                    :raw_analysis
                )
            SQL;

            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                'prospect_id' => $prospectId,

                'website_reachable' =>
                    $analysis['website_reachable'] ? 1 : 0,

                'has_ssl' =>
                    $analysis['has_ssl'] ? 1 : 0,

                'is_mobile_friendly' =>
                    $analysis['is_mobile_friendly'] ? 1 : 0,

                'has_contact_page' =>
                    (
                        $analysis['has_contact_page']
                        || $analysis['has_contact_form']
                    ) ? 1 : 0,

                'has_social_links' =>
                    $analysis['has_social_links'] ? 1 : 0,

                'has_visible_email' =>
                    $analysis['has_visible_email'] ? 1 : 0,

                'raw_analysis' =>
                    json_encode(
                        $analysis,
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ),
            ]);

            $analysisId =
                (int) $this->pdo->lastInsertId();

            $statusStatement =
                $this->pdo->prepare(
                    <<<SQL
                        UPDATE prospects
                        SET
                            status = 'analyzed',
                            analyzed_at = CURRENT_TIMESTAMP,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :prospect_id
                    SQL
                );

            $statusStatement->execute([
                'prospect_id' => $prospectId,
            ]);

            if ($statusStatement->rowCount() === 0) {
                $checkStatement =
                    $this->pdo->prepare(
                        'SELECT 1
                        FROM prospects
                        WHERE id = :id
                        LIMIT 1'
                    );

                $checkStatement->execute([
                    'id' => $prospectId,
                ]);

                if (
                    $checkStatement->fetchColumn()
                    === false
                ) {
                    throw new RuntimeException(
                        'Le prospect est introuvable.'
                    );
                }
            }

            $this->addEvent(
                $prospectId,
                'prospect_analyzed',
                (string) $previousStatus,
                'analyzed',
                'Analyse technique du site terminée.',
                [
                    'analysis_id' => $analysisId,
                    'website_reachable' =>
                        $analysis['website_reachable'],
                    'http_status' =>
                        $analysis['http_status'] ?? null,
                    'has_ssl' =>
                        $analysis['has_ssl'],
                    'is_mobile_friendly' =>
                        $analysis['is_mobile_friendly'],
                    'has_visible_email' =>
                        $analysis['has_visible_email'],
                    'has_visible_phone' =>
                        $analysis['has_visible_phone'],
                ]
            );

            $this->pdo->commit();

            return $analysisId;

        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Enregistre le résultat du scoring d'un prospect.
     */
    public function saveScoringResult(
        int $prospectId,
        array $scoring
    ): void {
        if ($prospectId <= 0) {
            throw new InvalidArgumentException(
                'Identifiant prospect invalide.'
            );
        }

        if (
            !isset(
                $scoring['score'],
                $scoring['qualified'],
                $scoring['breakdown']
            )
        ) {
            throw new InvalidArgumentException(
                'Résultat de scoring incomplet.'
            );
        }

        $score = (int) $scoring['score'];

        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException(
                'Le score doit être compris entre 0 et 100.'
            );
        }

        $breakdown = $scoring['breakdown'];

        $requiredCategories = [
            'commercial_potential',
            'website_gaps',
            'digital_presence',
            'business_fit',
            'contactability',
        ];

        foreach ($requiredCategories as $category) {
            if (
                !isset(
                    $breakdown[$category]['score']
                )
            ) {
                throw new InvalidArgumentException(
                    'Sous-score manquant : '
                    . $category
                );
            }
        }

        try {
            $this->pdo->beginTransaction();

            /*
            * Verrouillage du prospect et récupération
            * de son statut réel.
            */
            $prospectStatement =
                $this->pdo->prepare(
                    <<<SQL
                        SELECT status
                        FROM prospects
                        WHERE id = :prospect_id
                        LIMIT 1
                        FOR UPDATE
                    SQL
                );

            $prospectStatement->execute([
                'prospect_id' => $prospectId,
            ]);

            $previousStatus =
                $prospectStatement->fetchColumn();

            if ($previousStatus === false) {
                throw new RuntimeException(
                    'Le prospect est introuvable.'
                );
            }

            /*
            * On applique le scoring à la dernière
            * analyse technique disponible.
            */
            $analysisStatement =
                $this->pdo->prepare(
                    <<<SQL
                        SELECT id
                        FROM prospect_analyses
                        WHERE prospect_id = :prospect_id
                        ORDER BY id DESC
                        LIMIT 1
                        FOR UPDATE
                    SQL
                );

            $analysisStatement->execute([
                'prospect_id' => $prospectId,
            ]);

            $analysisId =
                $analysisStatement->fetchColumn();

            if ($analysisId === false) {
                throw new RuntimeException(
                    'Aucune analyse technique disponible.'
                );
            }

            $updateAnalysis =
                $this->pdo->prepare(
                    <<<SQL
                        UPDATE prospect_analyses
                        SET
                            commercial_potential_score =
                                :commercial_potential_score,

                            website_gaps_score =
                                :website_gaps_score,

                            digital_presence_score =
                                :digital_presence_score,

                            business_fit_score =
                                :business_fit_score,

                            contactability_score =
                                :contactability_score,

                            overall_score =
                                :overall_score,

                            scoring_details =
                                :scoring_details,

                            updated_at =
                                CURRENT_TIMESTAMP

                        WHERE id = :analysis_id
                    SQL
                );

            $updateAnalysis->execute([
                'commercial_potential_score' =>
                    (int) $breakdown[
                        'commercial_potential'
                    ]['score'],

                'website_gaps_score' =>
                    (int) $breakdown[
                        'website_gaps'
                    ]['score'],

                'digital_presence_score' =>
                    (int) $breakdown[
                        'digital_presence'
                    ]['score'],

                'business_fit_score' =>
                    (int) $breakdown[
                        'business_fit'
                    ]['score'],

                'contactability_score' =>
                    (int) $breakdown[
                        'contactability'
                    ]['score'],

                'overall_score' =>
                    $score,

                'scoring_details' =>
                    json_encode(
                        $scoring,
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),

                'analysis_id' =>
                    (int) $analysisId,
            ]);

            /*
            * À ce stade :
            *
            * >= 60 → qualified
            * < 60  → reste analyzed
            *
            * Le passage à pending_validation arrivera
            * après génération de l'audit et du message.
            */
            $newStatus =
                $scoring['qualified']
                    ? 'qualified'
                    : 'analyzed';

            $updateProspect =
                $this->pdo->prepare(
                    <<<SQL
                        UPDATE prospects
                        SET
                            score = :score,
                            status = :status,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :prospect_id
                    SQL
                );

            $updateProspect->execute([
                'score' => $score,
                'status' => $newStatus,
                'prospect_id' => $prospectId,
            ]);

            $this->addEvent(
                $prospectId,
                'prospect_scored',
                (string) $previousStatus,
                $newStatus,
                'Score de qualification calculé.',
                [
                    'analysis_id' =>
                        (int) $analysisId,

                    'score' =>
                        $score,

                    'threshold' =>
                        (int) (
                            $scoring['threshold']
                            ?? 60
                        ),

                    'qualified' =>
                        (bool) $scoring['qualified'],

                    'commercial_potential_score' =>
                        (int) $breakdown[
                            'commercial_potential'
                        ]['score'],

                    'website_gaps_score' =>
                        (int) $breakdown[
                            'website_gaps'
                        ]['score'],

                    'digital_presence_score' =>
                        (int) $breakdown[
                            'digital_presence'
                        ]['score'],

                    'business_fit_score' =>
                        (int) $breakdown[
                            'business_fit'
                        ]['score'],

                    'contactability_score' =>
                        (int) $breakdown[
                            'contactability'
                        ]['score'],
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

    public function saveAiAudit(
        int $prospectId,
        array $audit
    ): void {
        if ($prospectId <= 0) {
            throw new InvalidArgumentException(
                'Identifiant prospect invalide.'
            );
        }

        $requiredKeys = [
            'summary',
            'strengths',
            'weaknesses',
            'opportunities',
            'recommended_angle',
            'audit_text',
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $audit)) {
                throw new InvalidArgumentException(
                    'Champ d’audit manquant : ' . $key
                );
            }
        }

        if (
            !is_array($audit['strengths'])
            || !is_array($audit['weaknesses'])
            || !is_array($audit['opportunities'])
        ) {
            throw new InvalidArgumentException(
                'Les listes de l’audit sont invalides.'
            );
        }

        try {
            $this->pdo->beginTransaction();
            
            $prospectStatement = $this->pdo->prepare(
                <<<SQL
                    SELECT status
                    FROM prospects
                    WHERE id = :id
                    LIMIT 1
                    FOR UPDATE
                SQL
            );

            $prospectStatement->execute([
                'id' => $prospectId,
            ]);

            $status = $prospectStatement->fetchColumn();

            if ($status === false) {
                throw new RuntimeException(
                    'Prospect introuvable.'
                );
            }

            $analysisStatement = $this->pdo->prepare(
                <<<SQL
                    SELECT id
                    FROM prospect_analyses
                    WHERE prospect_id = :prospect_id
                    ORDER BY id DESC
                    LIMIT 1
                    FOR UPDATE
                SQL
            );

            $analysisStatement->execute([
                'prospect_id' => $prospectId,
            ]);

            $analysisId = $analysisStatement->fetchColumn();

            if ($analysisId === false) {
                throw new RuntimeException(
                    'Aucune analyse disponible pour ce prospect.'
                );
            }

            $updateStatement = $this->pdo->prepare(
                <<<SQL
                    UPDATE prospect_analyses
                    SET
                        strengths = :strengths,
                        weaknesses = :weaknesses,
                        opportunities = :opportunities,
                        audit_text = :audit_text,
                        ai_audit_details = :ai_audit_details,
                        updated_at = NOW()
                    WHERE id = :analysis_id
                SQL
            );

            $updateStatement->execute([
                'strengths' => json_encode(
                    $audit['strengths'],
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

                'weaknesses' => json_encode(
                    $audit['weaknesses'],
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

                'opportunities' => json_encode(
                    $audit['opportunities'],
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

                'audit_text' => trim(
                    (string) $audit['audit_text']
                ),

                'ai_audit_details' => json_encode(
                    $audit,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

                'analysis_id' => (int) $analysisId,
            ]);

            $this->addEvent(
                $prospectId,
                'prospect_audit_generated',
                (string) $status,
                (string) $status,
                'Audit IA généré à partir des données vérifiées.',
                [
                    'analysis_id' => (int) $analysisId,
                    'summary' => $audit['summary'],
                    'recommended_angle' =>
                        $audit['recommended_angle'],
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

    public function saveInitialEmailDraft(
        int $prospectId,
        array $message
    ): int {
        if ($prospectId <= 0) {
            throw new InvalidArgumentException(
                'Identifiant prospect invalide.'
            );
        }

        foreach (
            ['subject', 'preheader', 'opening', 'body', 'cta']
            as $key
        ) {
            if (
                !isset($message[$key])
                || trim((string) $message[$key]) === ''
            ) {
                throw new InvalidArgumentException(
                    'Champ du message manquant : ' . $key
                );
            }
        }

        try {
            $this->pdo->beginTransaction();

            $prospectStatement = $this->pdo->prepare(
                <<<SQL
                    SELECT
                        status,
                        email,
                        company_name
                    FROM prospects
                    WHERE id = :id
                    LIMIT 1
                    FOR UPDATE
                SQL
            );

            $prospectStatement->execute([
                'id' => $prospectId,
            ]);

            $prospect = $prospectStatement->fetch(
                PDO::FETCH_ASSOC
            );

            if ($prospect === false) {
                throw new RuntimeException(
                    'Prospect introuvable.'
                );
            }

            if ($prospect['status'] !== 'qualified') {
                throw new RuntimeException(
                    'Le prospect doit être qualifié.'
                );
            }

            $recipientEmail = trim(
                (string) ($prospect['email'] ?? '')
            );

            if (
                filter_var(
                    $recipientEmail,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new RuntimeException(
                    'Adresse e-mail du prospect invalide.'
                );
            }

            $bodyText = implode(
                PHP_EOL . PHP_EOL,
                [
                    trim((string) $message['opening']),
                    trim((string) $message['body']),
                    trim((string) $message['cta']),
                    'OL Creative Studio',
                ]
            );

            $bodyHtml = $this->buildInitialEmailHtml(
                $message
            );

            $insertStatement = $this->pdo->prepare(
                <<<SQL
                    INSERT INTO prospect_emails (
                        prospect_id,
                        email_type,
                        recipient_email,
                        subject,
                        body_html,
                        body_text,
                        status
                    ) VALUES (
                        :prospect_id,
                        'initial',
                        :recipient_email,
                        :subject,
                        :body_html,
                        :body_text,
                        'draft'
                    )
                SQL
            );

            $insertStatement->execute([
                'prospect_id' => $prospectId,
                'recipient_email' => $recipientEmail,
                'subject' => trim(
                    (string) $message['subject']
                ),
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
            ]);

            $emailId = (int) $this->pdo->lastInsertId();

            $updateProspect = $this->pdo->prepare(
                <<<SQL
                    UPDATE prospects
                    SET
                        status = 'pending_validation',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :prospect_id
                SQL
            );

            $updateProspect->execute([
                'prospect_id' => $prospectId,
            ]);

            $this->addEvent(
                $prospectId,
                'prospect_email_draft_generated',
                'qualified',
                'pending_validation',
                'Premier e-mail de prospection généré et placé en validation.',
                [
                    'email_id' => $emailId,
                    'email_type' => 'initial',
                    'recipient_email' => $recipientEmail,
                    'subject' => trim(
                        (string) $message['subject']
                    ),
                ]
            );

            $this->pdo->commit();

            return $emailId;

        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function buildInitialEmailHtml(
        array $message
    ): string {
        $subject = htmlspecialchars(
            (string) $message['subject'],
            ENT_QUOTES,
            'UTF-8'
        );

        $preheader = htmlspecialchars(
            (string) $message['preheader'],
            ENT_QUOTES,
            'UTF-8'
        );

        $opening = nl2br(
            htmlspecialchars(
                (string) $message['opening'],
                ENT_QUOTES,
                'UTF-8'
            )
        );

        $body = nl2br(
            htmlspecialchars(
                (string) $message['body'],
                ENT_QUOTES,
                'UTF-8'
            )
        );

        $cta = htmlspecialchars(
            (string) $message['cta'],
            ENT_QUOTES,
            'UTF-8'
        );

        return <<<HTML
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>{$subject}</title>
    </head>
    <body style="margin:0;padding:0;background:#f4f5f6;font-family:Arial,sans-serif;color:#0D1B2A;">
        <div style="display:none;max-height:0;overflow:hidden;">
            {$preheader}
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td align="center" style="padding:32px 16px;">
                    <table width="600" cellpadding="0" cellspacing="0" role="presentation"
                        style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;">
                        <tr>
                            <td style="padding:32px;">
                                <div style="font-size:22px;font-weight:700;margin-bottom:24px;">
                                    OL Creative Studio
                                </div>

                                <p style="font-size:16px;line-height:1.6;">
                                    {$opening}
                                </p>

                                <p style="font-size:16px;line-height:1.6;">
                                    {$body}
                                </p>

                                <p style="font-size:16px;line-height:1.6;font-weight:600;">
                                    {$cta}
                                </p>

                                <p style="margin-top:32px;font-size:14px;line-height:1.6;">
                                    OL Creative Studio<br>
                                    Développement web & design graphique
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    HTML;
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
