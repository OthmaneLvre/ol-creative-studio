<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

/*
|--------------------------------------------------------------------------
| Prospect demandé
|--------------------------------------------------------------------------
*/

$prospectId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$prospectId || $prospectId < 1) {
    header('Location: /admin/prospection/prospects.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Actions de validation
|--------------------------------------------------------------------------
*/

$actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    $action = (string) ($_POST['action'] ?? '');

    if (
        $csrf === ''
        || !hash_equals(
            (string) $_SESSION['csrf_token'],
            $csrf
        )
    ) {
        http_response_code(403);

        $actionError =
            'Jeton de sécurité invalide. Rechargez la page.';
    } else {
        try {
            $repository = new ProspectRepository($pdo);

            if ($action === 'save_email') {
                $subject = (string) (
                    $_POST['subject'] ?? ''
                );

                $bodyText = (string) (
                    $_POST['body_text'] ?? ''
                );

                $repository->updateInitialEmailDraft(
                    $prospectId,
                    $subject,
                    $bodyText
                );

            } elseif ($action === 'approve') {
                $repository->approveInitialEmailDraft(
                    $prospectId
                );

            } elseif ($action === 'reject') {
                $repository->rejectInitialEmailDraft(
                    $prospectId
                );

            } else {
                throw new InvalidArgumentException(
                    'Action inconnue.'
                );
            }

            $_SESSION['csrf_token'] =
                bin2hex(random_bytes(32));

            header(
                'Location: /admin/prospection/prospect.php?id='
                . $prospectId
                . '&updated=1'
            );

            exit;
        } catch (Throwable $exception) {
            $actionError = $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Prospect
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT *
     FROM prospects
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    'id' => $prospectId,
]);

$prospect = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prospect) {
    http_response_code(404);

    $adminPageTitle = 'Prospect introuvable';
    $adminActivePage = 'prospection';
} else {
    $adminPageTitle = (string) $prospect['company_name'];
    $adminActivePage = 'prospection';
}

/*
|--------------------------------------------------------------------------
| Analyse
|--------------------------------------------------------------------------
*/

$analysis = null;

if ($prospect) {
    try {
        $analysisStmt = $pdo->prepare(
            'SELECT *
             FROM prospect_analyses
             WHERE prospect_id = :prospect_id
             ORDER BY id DESC
             LIMIT 1'
        );

        $analysisStmt->execute([
            'prospect_id' => $prospectId,
        ]);

        $analysis = $analysisStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $exception) {
        $analysis = null;
    }
}

/*
|--------------------------------------------------------------------------
| Données détaillées de l'analyse
|--------------------------------------------------------------------------
*/

$rawAnalysis = [];
$scoringDetails = [];
$aiAudit = [];

if ($analysis) {
    try {
        if (!empty($analysis['raw_analysis'])) {
            $decoded = json_decode(
                (string) $analysis['raw_analysis'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (is_array($decoded)) {
                $rawAnalysis = $decoded;
            }
        }

        if (!empty($analysis['scoring_details'])) {
            $decoded = json_decode(
                (string) $analysis['scoring_details'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (is_array($decoded)) {
                $scoringDetails = $decoded;
            }
        }

        if (!empty($analysis['ai_audit_details'])) {
            $decoded = json_decode(
                (string) $analysis['ai_audit_details'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (is_array($decoded)) {
                $aiAudit = $decoded;
            }
        }
    } catch (JsonException $exception) {
        $rawAnalysis = [];
        $scoringDetails = [];
        $aiAudit = [];
    }
}

/*
|--------------------------------------------------------------------------
| Dernier e-mail
|--------------------------------------------------------------------------
*/

$email = null;

if ($prospect) {
    try {
        $emailStmt = $pdo->prepare(
            'SELECT *
             FROM prospect_emails
             WHERE prospect_id = :prospect_id
             ORDER BY id DESC
             LIMIT 1'
        );

        $emailStmt->execute([
            'prospect_id' => $prospectId,
        ]);

        $email = $emailStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $exception) {
        $email = null;
    }
}

/*
|--------------------------------------------------------------------------
| Historique
|--------------------------------------------------------------------------
*/

$events = [];

if ($prospect) {
    try {
        $eventsStmt = $pdo->prepare(
            'SELECT *
             FROM prospect_events
             WHERE prospect_id = :prospect_id
             ORDER BY created_at DESC, id DESC'
        );

        $eventsStmt->execute([
            'prospect_id' => $prospectId,
        ]);

        $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $events = [];
    }
}

/*
|--------------------------------------------------------------------------
| Libellés
|--------------------------------------------------------------------------
*/

$statusLabels = [
    'discovered' => 'Découvert',
    'analyzing' => 'Analyse en cours',
    'analyzed' => 'Analysé',
    'pending_validation' => 'À valider',
    'approved' => 'Validé',
    'scheduled' => 'Programmé',
    'contacted' => 'Contacté',
    'follow_up' => 'Relance',
    'replied' => 'Réponse reçue',
    'qualified' => 'Qualifié',
    'converted' => 'Converti',
    'rejected' => 'Refusé',
    'unsubscribed' => 'Désinscrit',
    'invalid' => 'Invalide',
];

$statusLabel = $prospect
    ? ($statusLabels[$prospect['status']] ?? $prospect['status'])
    : '';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function prospectValue(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function prospectDate(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))
            ->format('d/m/Y à H:i');
    } catch (Exception $exception) {
        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>
        <?= htmlspecialchars(
            $adminPageTitle,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        — OL Creative Studio
    </title>

    <link
        rel="icon"
        type="image/x-icon"
        href="/favicon.ico"
    >

    <link
        rel="stylesheet"
        href="/admin/assets/css/admin.css"
    >

</head>

<body class="admin-body">

<div class="admin-layout">

    <?php
    include_once dirname(__DIR__) . '/partials/sidebar.php';
    ?>

    <main class="admin-main">

        <?php
        include_once dirname(__DIR__) . '/partials/header.php';
        ?>

        <div class="admin-content">

            <?php if (!$prospect): ?>

                <section class="admin-panel">

                    <div class="admin-empty-state">

                        <span class="admin-eyebrow">
                            Erreur
                        </span>

                        <h1>
                            Prospect introuvable
                        </h1>

                        <p>
                            Ce prospect n'existe pas ou a été supprimé.
                        </p>

                        <a
                            href="/admin/prospection/prospects.php"
                            class="admin-button admin-button--primary"
                        >
                            Retour aux prospects
                        </a>

                    </div>

                </section>

            <?php else: ?>

                <!-- =========================
                     INTRO
                     ========================= -->

                <section class="admin-dashboard__intro">

                    <div>

                        <span class="admin-eyebrow">
                            Prospect #<?= (int) $prospect['id'] ?>
                        </span>

                        <h1>
                            <?= htmlspecialchars(
                                $prospect['company_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h1>

                        <p>
                            <?= prospectValue($prospect['activity']) ?>

                            <?php if (!empty($prospect['city'])): ?>
                                ·
                                <?= prospectValue($prospect['city']) ?>
                            <?php endif; ?>
                        </p>

                    </div>

                    <div class="prospect-detail__intro-actions">

                        <span class="admin-badge">
                            <?= htmlspecialchars(
                                $statusLabel,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <a
                            href="/admin/prospection/prospects.php"
                            class="admin-button"
                        >
                            ← Retour à la file
                        </a>

                    </div>

                </section>

                <!-- =========================
                     SCORE + IDENTITÉ
                     ========================= -->

                <section class="prospect-detail__overview">

                    <article class="prospect-score-card">

                        <span class="admin-eyebrow">
                            Score
                        </span>

                        <?php if ($prospect['score'] !== null): ?>

                            <strong class="prospect-score-card__value">
                                <?= (int) $prospect['score'] ?>
                                <small>/100</small>
                            </strong>

                        <?php else: ?>

                            <strong class="prospect-score-card__value">
                                —
                            </strong>

                        <?php endif; ?>

                        <p>
                            Potentiel commercial estimé
                            par le moteur de prospection.
                        </p>

                    </article>

                    <article class="admin-panel prospect-detail__identity">

                        <div class="admin-panel__header">

                            <div>

                                <span class="admin-eyebrow">
                                    Entreprise
                                </span>

                                <h2>
                                    Informations
                                </h2>

                            </div>

                        </div>

                        <dl class="prospect-detail__data">

                            <div>
                                <dt>Activité</dt>
                                <dd>
                                    <?= prospectValue($prospect['activity']) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Commune</dt>
                                <dd>
                                    <?= prospectValue($prospect['city']) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Code postal</dt>
                                <dd>
                                    <?= prospectValue($prospect['postal_code']) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Département</dt>
                                <dd>
                                    <?= prospectValue($prospect['department']) ?>
                                </dd>
                            </div>

                        </dl>

                    </article>

                </section>

                <!-- =========================
                     CONTACT
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Contact
                            </span>

                            <h2>
                                Coordonnées détectées
                            </h2>

                        </div>

                    </div>

                    <dl class="prospect-detail__data prospect-detail__data--three">

                        <div>

                            <dt>E-mail</dt>

                            <dd>
                                <?php if (!empty($prospect['email'])): ?>

                                    <a
                                        href="mailto:<?= htmlspecialchars(
                                            $prospect['email'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                        <?= prospectValue($prospect['email']) ?>
                                    </a>

                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </dd>

                        </div>

                        <div>

                            <dt>Téléphone</dt>

                            <dd>
                                <?= prospectValue($prospect['phone']) ?>
                            </dd>

                        </div>

                        <div>

                            <dt>Site internet</dt>

                            <dd>
                                <?php if (!empty($prospect['website_url'])): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $prospect['website_url'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Visiter le site ↗
                                    </a>

                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </dd>

                        </div>

                    </dl>

                </section>

                <!-- =========================
                     SOURCE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Acquisition
                            </span>

                            <h2>
                                Source du prospect
                            </h2>

                        </div>

                    </div>

                    <dl class="prospect-detail__data prospect-detail__data--three">

                        <div>
                            <dt>Source</dt>
                            <dd>
                                <?= prospectValue($prospect['source']) ?>
                            </dd>
                        </div>

                        <div>

                            <dt>Page source</dt>

                            <dd>

                                <?php if (!empty($prospect['source_url'])): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $prospect['source_url'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Ouvrir ↗
                                    </a>

                                <?php else: ?>
                                    —
                                <?php endif; ?>

                            </dd>

                        </div>

                        <div>
                            <dt>Découvert le</dt>
                            <dd>
                                <?= prospectDate($prospect['discovered_at']) ?>
                            </dd>
                        </div>

                    </dl>

                </section>

                <!-- =========================
                     ANALYSE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Analyse
                            </span>

                            <h2>
                                Audit du site
                            </h2>

                        </div>

                        <?php if ($analysis && $analysis['overall_score'] !== null): ?>

                            <span class="admin-badge">
                                Score <?= (int) $analysis['overall_score'] ?>/100
                            </span>

                        <?php endif; ?>

                    </div>

                    <?php if (!$analysis): ?>

                        <div class="prospect-detail__pending">

                            <strong>
                                Analyse non disponible
                            </strong>

                            <p>
                                Le moteur d'analyse n'a pas encore
                                audité ce prospect.
                            </p>

                        </div>

                    <?php else: ?>

                        <!-- Analyse technique -->

                        <div class="prospect-analysis">

                            <h3>
                                Analyse technique
                            </h3>

                            <dl class="prospect-detail__data prospect-detail__data--three">

                                <div>
                                    <dt>Site accessible</dt>
                                    <dd>
                                        <?= !empty($rawAnalysis['website_reachable'])
                                            ? 'Oui'
                                            : 'Non' ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>HTTPS</dt>
                                    <dd>
                                        <?= !empty($rawAnalysis['has_ssl'])
                                            ? 'Oui'
                                            : 'Non' ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>HTTP</dt>
                                    <dd>
                                        <?= isset($rawAnalysis['http_status'])
                                            ? (int) $rawAnalysis['http_status']
                                            : '—' ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Titre</dt>
                                    <dd>
                                        <?= prospectValue(
                                            isset($rawAnalysis['title'])
                                                ? (string) $rawAnalysis['title']
                                                : null
                                        ) ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Meta description</dt>
                                    <dd>
                                        <?= !empty($rawAnalysis['has_meta_description'])
                                            ? 'Présente'
                                            : 'Absente' ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Viewport</dt>
                                    <dd>
                                        <?= !empty($rawAnalysis['has_viewport'])
                                            ? 'Détecté'
                                            : 'Non détecté' ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Page contact</dt>
                                    <dd>
                                        <?= !empty($rawAnalysis['has_contact_page'])
                                            ? 'Détectée'
                                            : 'Non détectée' ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Formulaire</dt>
                                    <dd>
                                        <?= !empty($rawAnalysis['has_contact_form'])
                                            ? 'Détecté'
                                            : 'Non détecté' ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Réseaux sociaux</dt>
                                    <dd>
                                        <?= !empty($rawAnalysis['has_social_links'])
                                            ? 'Détectés'
                                            : 'Non détectés' ?>
                                    </dd>
                                </div>

                            </dl>

                        </div>

                        <!-- Scoring -->

                        <?php if (!empty($scoringDetails['breakdown'])): ?>

                            <div class="prospect-analysis">

                                <h3>
                                    Détail du score
                                </h3>

                                <dl class="prospect-detail__data prospect-detail__data--three">

                                    <?php
                                    $scoreLabels = [
                                        'commercial_potential' => 'Potentiel commercial',
                                        'website_gaps' => 'Améliorations du site',
                                        'digital_presence' => 'Présence numérique',
                                        'business_fit' => 'Compatibilité activité',
                                        'contactability' => 'Contactabilité',
                                    ];
                                    ?>

                                    <?php foreach ($scoreLabels as $key => $label): ?>

                                        <?php
                                        $scoreItem =
                                            $scoringDetails['breakdown'][$key] ?? null;
                                        ?>

                                        <?php if (is_array($scoreItem)): ?>

                                            <div>

                                                <dt>
                                                    <?= htmlspecialchars(
                                                        $label,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </dt>

                                                <dd>
                                                    <strong>
                                                        <?= (int) ($scoreItem['score'] ?? 0) ?>
                                                        /
                                                        <?= (int) ($scoreItem['max'] ?? 0) ?>
                                                    </strong>
                                                </dd>

                                            </div>

                                        <?php endif; ?>

                                    <?php endforeach; ?>

                                </dl>

                            </div>

                        <?php endif; ?>

                        <!-- Audit IA -->

                        <?php if ($aiAudit): ?>

                            <div class="prospect-ai-audit">

                                <div class="prospect-ai-audit__section">

                                    <span class="admin-eyebrow">
                                        Synthèse IA
                                    </span>

                                    <h3>
                                        Résumé
                                    </h3>

                                    <p>
                                        <?= prospectValue(
                                            isset($aiAudit['summary'])
                                                ? (string) $aiAudit['summary']
                                                : null
                                        ) ?>
                                    </p>

                                </div>

                                <?php
                                $auditSections = [
                                    'strengths' => 'Forces',
                                    'weaknesses' => 'Points d’amélioration',
                                    'opportunities' => 'Opportunités',
                                ];
                                ?>

                                <div class="prospect-ai-audit__grid">

                                    <?php foreach ($auditSections as $key => $label): ?>

                                        <article class="prospect-ai-audit__card">

                                            <h3>
                                                <?= htmlspecialchars(
                                                    $label,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </h3>

                                            <?php if (!empty($aiAudit[$key])): ?>

                                                <ul>

                                                    <?php foreach ($aiAudit[$key] as $item): ?>

                                                        <li>
                                                            <?= htmlspecialchars(
                                                                (string) $item,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </li>

                                                    <?php endforeach; ?>

                                                </ul>

                                            <?php else: ?>

                                                <p>—</p>

                                            <?php endif; ?>

                                        </article>

                                    <?php endforeach; ?>

                                </div>

                                <?php if (!empty($aiAudit['recommended_angle'])): ?>

                                    <div class="prospect-ai-audit__angle">

                                        <span class="admin-eyebrow">
                                            Angle recommandé
                                        </span>

                                        <p>
                                            <?= htmlspecialchars(
                                                (string) $aiAudit['recommended_angle'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </p>

                                    </div>

                                <?php endif; ?>

                                <?php if (!empty($aiAudit['audit_text'])): ?>

                                    <div class="prospect-ai-audit__section">

                                        <h3>
                                            Mini-audit
                                        </h3>

                                        <p>
                                            <?= nl2br(
                                                htmlspecialchars(
                                                    (string) $aiAudit['audit_text'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                            ) ?>
                                        </p>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </section>

                <!-- =========================
                    MESSAGE
                    ========================= -->

                <?php
                $isEditingEmail =
                    isset($_GET['edit'])
                    && $_GET['edit'] === '1'
                    && $prospect['status'] === 'pending_validation'
                    && $email
                    && ($email['status'] ?? '') === 'draft';
                ?>

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                E-mail
                            </span>

                            <h2>
                                Message de prospection
                            </h2>

                        </div>

                        <?php
                        if (
                            $prospect['status'] === 'pending_validation'
                            && $email
                            && ($email['status'] ?? '') === 'draft'
                        ):
                        ?>

                            <?php if (!$isEditingEmail): ?>

                                <a
                                    href="/admin/prospection/prospect.php?id=<?= $prospectId ?>&edit=1"
                                    class="admin-button"
                                >
                                    Modifier le message
                                </a>

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                    <?php if (!$email): ?>

                        <div class="prospect-detail__pending">

                            <strong>
                                Aucun message généré
                            </strong>

                            <p>
                                Le message personnalisé sera généré
                                après l'analyse du prospect.
                            </p>

                        </div>

                    <?php elseif ($isEditingEmail): ?>

                        <form
                            method="post"
                            class="prospect-email-editor"
                        >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="save_email"
                            >

                            <div class="admin-form-group">

                                <label for="email-subject">
                                    Objet
                                </label>

                                <input
                                    type="text"
                                    id="email-subject"
                                    name="subject"
                                    maxlength="190"
                                    required
                                    value="<?= htmlspecialchars(
                                        (string) ($email['subject'] ?? ''),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                            </div>

                            <div class="admin-form-group">

                                <label for="email-body">
                                    Message
                                </label>

                                <textarea
                                    id="email-body"
                                    name="body_text"
                                    rows="14"
                                    required
                                ><?= htmlspecialchars(
                                    (string) ($email['body_text'] ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>

                            <div class="prospect-detail__actions">

                                <button
                                    type="submit"
                                    class="admin-button admin-button--primary"
                                >
                                    Enregistrer les modifications
                                </button>

                                <a
                                    href="/admin/prospection/prospect.php?id=<?= $prospectId ?>"
                                    class="admin-button"
                                >
                                    Annuler
                                </a>

                            </div>

                        </form>

                    <?php else: ?>

                        <div class="prospect-email-preview">

                            <div class="prospect-email-preview__meta">

                                <div>

                                    <span class="prospect-email-preview__label">
                                        Destinataire
                                    </span>

                                    <strong>
                                        <?= prospectValue(
                                            $email['recipient_email'] ?? null
                                        ) ?>
                                    </strong>

                                </div>

                                <div>

                                    <span class="prospect-email-preview__label">
                                        Statut
                                    </span>

                                    <strong>
                                        <?= prospectValue(
                                            $email['status'] ?? null
                                        ) ?>
                                    </strong>

                                </div>

                            </div>

                            <div class="prospect-email-preview__subject">

                                <span class="prospect-email-preview__label">
                                    Objet
                                </span>

                                <strong>
                                    <?= prospectValue(
                                        $email['subject'] ?? null
                                    ) ?>
                                </strong>

                            </div>

                            <?php if (!empty($email['body_text'])): ?>

                                <div class="prospect-email-preview__body">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            (string) $email['body_text'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                </section>

                <!-- =========================
                     ACTIONS
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Décision
                            </span>

                            <h2>
                                Validation
                            </h2>

                        </div>

                    </div>

                    <?php if ($prospect['status'] === 'pending_validation'): ?>

                        <?php if ($actionError !== null): ?>

                            <div class="admin-alert admin-alert--error">
                                <?= htmlspecialchars(
                                    $actionError,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>

                        <?php endif; ?>

                        <div class="prospect-detail__actions">

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= htmlspecialchars(
                                        $_SESSION['csrf_token'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="approve"
                                >

                                <button
                                    type="submit"
                                    class="admin-button admin-button--primary"
                                >
                                    Valider le prospect
                                </button>

                            </form>

                            <form
                                method="post"
                            >

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= htmlspecialchars(
                                        $_SESSION['csrf_token'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="reject"
                                >

                                <button
                                    type="submit"
                                    class="admin-button"
                                >
                                    Refuser
                                </button>

                            </form>

                        </div>

                        <p class="prospect-detail__action-note">
                            La validation n'envoie pas l'e-mail.
                            Elle autorise uniquement sa programmation ultérieure.
                        </p>

                    <?php else: ?>

                        <p>
                            Statut actuel :
                            <strong>
                                <?= htmlspecialchars(
                                    $statusLabel,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </p>

                    <?php endif; ?>

                </section>

                <!-- =========================
                     CHRONOLOGIE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Historique
                            </span>

                            <h2>
                                Chronologie
                            </h2>

                        </div>

                    </div>

                    <div class="prospect-timeline">

                        <article class="prospect-timeline__item">

                            <span class="prospect-timeline__dot"></span>

                            <div>

                                <strong>
                                    Prospect découvert
                                </strong>

                                <span>
                                    <?= prospectDate(
                                        $prospect['discovered_at']
                                    ) ?>
                                </span>

                            </div>

                        </article>

                        <?php foreach ($events as $event): ?>

                            <article class="prospect-timeline__item">

                                <span class="prospect-timeline__dot"></span>

                                <div>

                                    <strong>
                                        <?= prospectValue(
                                            $event['event_type'] ?? 'Événement'
                                        ) ?>
                                    </strong>

                                    <?php if (!empty($event['description'])): ?>

                                        <p>
                                            <?= prospectValue(
                                                $event['description']
                                            ) ?>
                                        </p>

                                    <?php endif; ?>

                                    <span>
                                        <?= prospectDate(
                                            $event['created_at'] ?? null
                                        ) ?>
                                    </span>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php
include dirname(__DIR__) . '/partials/footer.php';
?>

</body>

</html>
