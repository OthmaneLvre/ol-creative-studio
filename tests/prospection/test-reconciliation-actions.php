<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$repository =
    new ProspectRepository(
        $pdo
    );

$prospectIds = [];
$emailIds = [];

function assertTest(
    bool $condition,
    string $successMessage,
    string $failureMessage
): void {
    if (!$condition) {
        throw new RuntimeException(
            $failureMessage
        );
    }

    echo $successMessage . PHP_EOL;
}

function createReconciliationFixture(
    PDO $pdo,
    ProspectRepository $repository,
    string $suffix
): array {
    /*
    |--------------------------------------------------------------------------
    | Prospect temporaire
    |--------------------------------------------------------------------------
    */

    $prospectStatement = $pdo->prepare(
        '
        INSERT INTO prospects (
            company_name,
            email,
            email_source_url,
            email_type,
            email_confidence,
            source,
            status,
            scheduled_at
        ) VALUES (
            :company_name,
            :email,
            :email_source_url,
            :email_type,
            :email_confidence,
            :source,
            :status,
            :scheduled_at
        )
        '
    );

    $scheduledAt = (new DateTimeImmutable())
        ->modify('-1 hour')
        ->format('Y-m-d H:i:s');

    $prospectStatement->execute([
        'company_name' =>
            'TEST RECONCILIATION ' . $suffix,

        'email' =>
            'reconciliation-' . strtolower($suffix)
            . '@example.com',

        'email_source_url' =>
            'https://example.com/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'test',

        'status' =>
            'scheduled',

        'scheduled_at' =>
            $scheduledAt,
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | E-mail temporaire programmé
    |--------------------------------------------------------------------------
    */

    $emailStatement = $pdo->prepare(
        '
        INSERT INTO prospect_emails (
            prospect_id,
            email_type,
            recipient_email,
            subject,
            body_text,
            status,
            approved_at,
            scheduled_at
        ) VALUES (
            :prospect_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_text,
            :status,
            :approved_at,
            :scheduled_at
        )
        '
    );

    $emailStatement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'initial',

        'recipient_email' =>
            'reconciliation-' . strtolower($suffix)
            . '@example.com',

        'subject' =>
            'Test réconciliation ' . $suffix,

        'body_text' =>
            'Message temporaire de test.',

        'status' =>
            'scheduled',

        'approved_at' =>
            $scheduledAt,

        'scheduled_at' =>
            $scheduledAt,
    ]);

    $emailId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Claim normal
    |--------------------------------------------------------------------------
    */

    $messageId =
        '<reconciliation-'
        . strtolower($suffix)
        . '-'
        . bin2hex(random_bytes(8))
        . '@olcreativestudio.fr>';

    $repository->claimScheduledInitialEmail(
        $emailId,
        $messageId,
        3
    );

    /*
    |--------------------------------------------------------------------------
    | Simulation d'un sending stale
    |--------------------------------------------------------------------------
    */

    $staleAt = (new DateTimeImmutable())
        ->modify('-20 minutes')
        ->format('Y-m-d H:i:s');

    $staleStatement = $pdo->prepare(
        '
        UPDATE prospect_emails
        SET updated_at = :updated_at
        WHERE id = :email_id
        '
    );

    $staleStatement->execute([
        'updated_at' => $staleAt,
        'email_id' => $emailId,
    ]);

    $repository
        ->markStaleInitialEmailForReconciliation(
            $emailId,
            new DateTimeImmutable(),
            15
        );

    return [
        'prospect_id' => $prospectId,
        'email_id' => $emailId,
        'message_id' => $messageId,
    ];
}

try {
    echo PHP_EOL;
    echo '========================================'
        . PHP_EOL;
    echo 'TEST ACTIONS DE RÉCONCILIATION'
        . PHP_EOL;
    echo '========================================'
        . PHP_EOL;
    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | SCÉNARIO A
    | Confirmer que l'e-mail a été délivré
    |--------------------------------------------------------------------------
    */

    echo '--- Confirmation comme envoyé ---'
        . PHP_EOL;

    $fixtureSent =
        createReconciliationFixture(
            $pdo,
            $repository,
            'SENT'
        );

    $prospectIds[] =
        $fixtureSent['prospect_id'];

    $emailIds[] =
        $fixtureSent['email_id'];

    $confirmedAt =
        new DateTimeImmutable();

    $resultSent =
        $repository
            ->confirmReconciliationAsSent(
                $fixtureSent['email_id'],
                $confirmedAt
            );

    assertTest(
        $resultSent['status'] === 'sent',
        'reconciliation_required -> sent : OK',
        'Échec du passage vers sent.'
    );

    assertTest(
        $resultSent['prospect_status']
            === 'contacted',
        'Prospect -> contacted : OK',
        'Le prospect n’est pas contacted.'
    );

    $checkSent = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.sent_at,
            e.message_id,
            e.send_attempts,
            p.status AS prospect_status,
            p.contacted_at
        FROM prospect_emails e
        INNER JOIN prospects p
            ON p.id = e.prospect_id
        WHERE e.id = :email_id
        LIMIT 1
        '
    );

    $checkSent->execute([
        'email_id' =>
            $fixtureSent['email_id'],
    ]);

    $sentRow =
        $checkSent->fetch(PDO::FETCH_ASSOC);

    assertTest(
        $sentRow !== false
        && $sentRow['email_status'] === 'sent'
        && !empty($sentRow['sent_at'])
        && !empty($sentRow['contacted_at']),
        'Dates d’envoi/contact : OK',
        'Les dates sent_at/contacted_at sont invalides.'
    );

    assertTest(
        $sentRow['message_id']
            === $fixtureSent['message_id'],
        'Message-ID conservé : OK',
        'Le Message-ID a été modifié.'
    );

    assertTest(
        (int) $sentRow['send_attempts'] === 1,
        'Tentatives conservées : OK',
        'Le compteur de tentatives a été modifié.'
    );

    $eventSent = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_events
        WHERE prospect_id = :prospect_id
        AND event_type = :event_type
        '
    );

    $eventSent->execute([
        'prospect_id' =>
            $fixtureSent['prospect_id'],

        'event_type' =>
            'prospect_email_reconciliation_confirmed_sent',
    ]);

    assertTest(
        (int) $eventSent->fetchColumn() === 1,
        'Événement confirmation : OK',
        'Événement de confirmation absent.'
    );

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | SCÉNARIO B
    | Confirmer que l'e-mail n'a pas été délivré
    |--------------------------------------------------------------------------
    */

    echo '--- Reprogrammation manuelle ---'
        . PHP_EOL;

    $fixtureRetry =
        createReconciliationFixture(
            $pdo,
            $repository,
            'RETRY'
        );

    $prospectIds[] =
        $fixtureRetry['prospect_id'];

    $emailIds[] =
        $fixtureRetry['email_id'];

    $newScheduledAt =
        (new DateTimeImmutable())
            ->modify('+1 hour');

    $resultRetry =
        $repository
            ->rescheduleReconciledInitialEmail(
                $fixtureRetry['email_id'],
                $newScheduledAt
            );

    assertTest(
        $resultRetry['status']
            === 'scheduled',
        'reconciliation_required -> scheduled : OK',
        'Échec du retour vers scheduled.'
    );

    assertTest(
        $resultRetry['prospect_status']
            === 'scheduled',
        'Prospect reste scheduled : OK',
        'Le prospect a changé de statut.'
    );

    assertTest(
        $resultRetry['previous_message_id']
            === $fixtureRetry['message_id'],
        'Ancien Message-ID retourné : OK',
        'Ancien Message-ID incorrect.'
    );

    assertTest(
        $resultRetry['message_id'] === null,
        'Nouveau Message-ID en attente : OK',
        'Le Message-ID devrait être NULL.'
    );

    $checkRetry = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.scheduled_at AS email_scheduled_at,
            e.message_id,
            e.send_attempts,
            e.sent_at,
            p.status AS prospect_status,
            p.scheduled_at AS prospect_scheduled_at,
            p.contacted_at
        FROM prospect_emails e
        INNER JOIN prospects p
            ON p.id = e.prospect_id
        WHERE e.id = :email_id
        LIMIT 1
        '
    );

    $checkRetry->execute([
        'email_id' =>
            $fixtureRetry['email_id'],
    ]);

    $retryRow =
        $checkRetry->fetch(PDO::FETCH_ASSOC);

    $expectedScheduledAt =
        $newScheduledAt->format(
            'Y-m-d H:i:s'
        );

    assertTest(
        $retryRow !== false
        && $retryRow['email_status']
            === 'scheduled'
        && $retryRow['prospect_status']
            === 'scheduled',
        'États reprogrammés : OK',
        'Les états après reprogrammation sont invalides.'
    );

    assertTest(
        $retryRow['message_id'] === null,
        'Message-ID BDD réinitialisé : OK',
        'Le Message-ID BDD n’est pas NULL.'
    );

    assertTest(
        (int) $retryRow['send_attempts'] === 1,
        'Tentatives inchangées : OK',
        'Le compteur de tentatives a changé.'
    );

    assertTest(
        $retryRow['sent_at'] === null
        && $retryRow['contacted_at'] === null,
        'Aucun faux envoi enregistré : OK',
        'Une date d’envoi/contact a été enregistrée.'
    );

    assertTest(
        $retryRow['email_scheduled_at']
            === $expectedScheduledAt
        && $retryRow['prospect_scheduled_at']
            === $expectedScheduledAt,
        'Dates de programmation synchronisées : OK',
        'Les dates de programmation ne sont pas synchronisées.'
    );

    $eventRetry = $pdo->prepare(
        '
        SELECT metadata
        FROM prospect_events
        WHERE prospect_id = :prospect_id
        AND event_type = :event_type
        ORDER BY id DESC
        LIMIT 1
        '
    );

    $eventRetry->execute([
        'prospect_id' =>
            $fixtureRetry['prospect_id'],

        'event_type' =>
            'prospect_email_reconciliation_rescheduled',
    ]);

    $eventMetadataRaw =
        $eventRetry->fetchColumn();

    assertTest(
        $eventMetadataRaw !== false,
        'Événement reprogrammation : OK',
        'Événement de reprogrammation absent.'
    );

    $eventMetadata =
        json_decode(
            (string) $eventMetadataRaw,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

    assertTest(
        ($eventMetadata['previous_message_id'] ?? null)
            === $fixtureRetry['message_id'],
        'Ancien Message-ID journalisé : OK',
        'L’ancien Message-ID n’est pas présent dans le journal.'
    );

    echo PHP_EOL;
    echo 'Actions de réconciliation : OK'
        . PHP_EOL;

} finally {
    /*
    |--------------------------------------------------------------------------
    | Nettoyage
    |--------------------------------------------------------------------------
    */

    foreach ($prospectIds as $prospectId) {
        $deleteEvents = $pdo->prepare(
            '
            DELETE FROM prospect_events
            WHERE prospect_id = :prospect_id
            '
        );

        $deleteEvents->execute([
            'prospect_id' => $prospectId,
        ]);

        $deleteEmails = $pdo->prepare(
            '
            DELETE FROM prospect_emails
            WHERE prospect_id = :prospect_id
            '
        );

        $deleteEmails->execute([
            'prospect_id' => $prospectId,
        ]);

        $deleteProspect = $pdo->prepare(
            '
            DELETE FROM prospects
            WHERE id = :prospect_id
            '
        );

        $deleteProspect->execute([
            'prospect_id' => $prospectId,
        ]);
    }

    echo 'Données temporaires supprimées.'
        . PHP_EOL;
}
