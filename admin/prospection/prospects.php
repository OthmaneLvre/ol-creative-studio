<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Paramètres
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'discovered_desc'));

/*
|--------------------------------------------------------------------------
| Statuts autorisés
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'discovered',
    'analyzing',
    'analyzed',
    'pending_validation',
    'approved',
    'scheduled',
    'contacted',
    'follow_up',
    'replied',
    'qualified',
    'converted',
    'rejected',
    'unsubscribed',
    'invalid',
];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/*
|--------------------------------------------------------------------------
| Tri
|--------------------------------------------------------------------------
*/

$sortOptions = [
    'discovered_desc' => 'discovered_at DESC',
    'discovered_asc' => 'discovered_at ASC',
    'score_desc' => 'score DESC, discovered_at DESC',
    'score_asc' => 'score ASC, discovered_at DESC',
    'company_asc' => 'company_name ASC',
    'company_desc' => 'company_name DESC',
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'discovered_desc';
}

$orderBy = $sortOptions[$sort];

/*
|--------------------------------------------------------------------------
| Requête
|--------------------------------------------------------------------------
*/

$sql = '
    SELECT
        id,
        company_name,
        activity,
        city,
        postal_code,
        email,
        website_url,
        status,
        score,
        discovered_at,
        contacted_at,
        replied_at
    FROM prospects
    WHERE 1 = 1
';

$params = [];

if ($search !== '') {
    $sql .= '
        AND (
            company_name LIKE :search
            OR activity LIKE :search
            OR city LIKE :search
            OR postal_code LIKE :search
            OR email LIKE :search
        )
    ';

    $params['search'] = '%' . $search . '%';
}

if ($status !== '') {
    $sql .= ' AND status = :status';
    $params['status'] = $status;
}

$sql .= ' ORDER BY ' . $orderBy;

$prospects = [];

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $prospects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $prospects = [];
}

/*
|--------------------------------------------------------------------------
| Compteurs
|--------------------------------------------------------------------------
*/

$totalProspects = 0;
$pendingProspects = 0;
$contactedProspects = 0;
$repliedProspects = 0;

try {
    $statsStmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'pending_validation') AS pending,
            SUM(status IN ('contacted', 'follow_up')) AS contacted,
            SUM(status IN ('replied', 'qualified', 'converted')) AS replied
         FROM prospects"
    );

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $totalProspects = (int) ($stats['total'] ?? 0);
    $pendingProspects = (int) ($stats['pending'] ?? 0);
    $contactedProspects = (int) ($stats['contacted'] ?? 0);
    $repliedProspects = (int) ($stats['replied'] ?? 0);
} catch (PDOException $exception) {
    // Le module peut être affiché avant installation complète des tables.
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

$sortLabels = [
    'discovered_desc' => 'Plus récents',
    'discovered_asc' => 'Plus anciens',
    'score_desc' => 'Meilleur score',
    'score_asc' => 'Score le plus faible',
    'company_asc' => 'Entreprise A → Z',
    'company_desc' => 'Entreprise Z → A',
];

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Prospects';
$adminActivePage = 'prospection';

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
        Prospects — OL Creative Studio
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

                <!-- =========================
                     INTRO
                     ========================= -->

                <section class="admin-dashboard__intro">

                    <div>

                        <span class="admin-eyebrow">
                            Prospection
                        </span>

                        <h1>
                            File des prospects
                        </h1>

                        <p>
                            Consulte, filtre et ouvre les prospects
                            détectés par le moteur de prospection.
                        </p>

                    </div>

                    <a
                        href="/admin/prospection/"
                        class="admin-button"
                    >
                        Retour au tableau de bord
                    </a>

                </section>

                <!-- =========================
                     STATS
                     ========================= -->

                <section
                    class="admin-stats"
                    aria-label="Statistiques des prospects"
                >

                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Total
                        </span>

                        <strong>
                            <?= $totalProspects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            prospects enregistrés
                        </span>

                    </article>

                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            À valider
                        </span>

                        <strong>
                            <?= $pendingProspects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            en attente de validation
                        </span>

                    </article>

                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Contactés
                        </span>

                        <strong>
                            <?= $contactedProspects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            message envoyé ou relance
                        </span>

                    </article>

                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Réponses
                        </span>

                        <strong>
                            <?= $repliedProspects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            réponse ou conversion
                        </span>

                    </article>

                </section>

                <!-- =========================
                     FILTRES
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Recherche
                            </span>

                            <h2>
                                Filtrer la file
                            </h2>

                        </div>

                    </div>

                    <form
                        method="get"
                        action="/admin/prospection/prospects.php"
                        class="admin-form"
                    >

                        <div class="admin-form__grid">

                            <div class="admin-form__field">

                                <label for="q">
                                    Recherche
                                </label>

                                <input
                                    type="search"
                                    id="q"
                                    name="q"
                                    value="<?= htmlspecialchars(
                                        $search,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    placeholder="Entreprise, activité, ville, e-mail…"
                                >

                            </div>

                            <div class="admin-form__field">

                                <label for="status">
                                    Statut
                                </label>

                                <select
                                    id="status"
                                    name="status"
                                >

                                    <option value="">
                                        Tous les statuts
                                    </option>

                                    <?php foreach ($statusLabels as $value => $label): ?>

                                        <option
                                            value="<?= htmlspecialchars(
                                                $value,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            <?= $status === $value ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $label,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="admin-form__field">

                                <label for="sort">
                                    Trier par
                                </label>

                                <select
                                    id="sort"
                                    name="sort"
                                >

                                    <?php foreach ($sortLabels as $value => $label): ?>

                                        <option
                                            value="<?= htmlspecialchars(
                                                $value,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            <?= $sort === $value ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $label,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                        <div class="admin-form__actions">

                            <button
                                type="submit"
                                class="admin-button admin-button--primary"
                            >
                                Appliquer
                            </button>

                            <?php if ($search !== '' || $status !== '' || $sort !== 'discovered_desc'): ?>

                                <a
                                    href="/admin/prospection/prospects.php"
                                    class="admin-button"
                                >
                                    Réinitialiser
                                </a>

                            <?php endif; ?>

                        </div>

                    </form>

                </section>

                <!-- =========================
                     LISTE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Prospects
                            </span>

                            <h2>
                                <?= count($prospects) ?>
                                résultat<?= count($prospects) > 1 ? 's' : '' ?>
                            </h2>

                        </div>

                    </div>

                    <?php if (empty($prospects)): ?>

                        <div class="admin-empty-state">

                            <p>
                                Aucun prospect ne correspond
                                aux critères sélectionnés.
                            </p>

                            <?php if ($totalProspects === 0): ?>

                                <span class="admin-stat-card__meta">
                                    La file sera alimentée lors de la première
                                    exécution du moteur de prospection.
                                </span>

                            <?php else: ?>

                                <a
                                    href="/admin/prospection/prospects.php"
                                    class="admin-text-link"
                                >
                                    Afficher tous les prospects
                                    <span aria-hidden="true">→</span>
                                </a>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="admin-project-list">

                            <?php foreach ($prospects as $prospect): ?>

                                <?php
                                $prospectStatus = (string) $prospect['status'];
                                $prospectStatusLabel =
                                    $statusLabels[$prospectStatus]
                                    ?? $prospectStatus;
                                ?>

                                <article class="prospect-row">

                                    <div class="prospect-row__main">

                                        <div class="prospect-row__identity">

                                            <span class="prospect-row__location">
                                                <?= htmlspecialchars(
                                                    $prospect['city']
                                                        ?: 'Localisation inconnue',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                                <?php if (!empty($prospect['postal_code'])): ?>
                                                    ·
                                                    <?= htmlspecialchars(
                                                        $prospect['postal_code'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                <?php endif; ?>
                                            </span>

                                            <h3>
                                                <?= htmlspecialchars(
                                                    $prospect['company_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </h3>

                                            <?php if (!empty($prospect['activity'])): ?>

                                                <span class="prospect-row__activity">
                                                    <?= htmlspecialchars(
                                                        $prospect['activity'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                        <span class="admin-badge">
                                            <?= htmlspecialchars(
                                                $prospectStatusLabel,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </div>

                                    <div class="prospect-row__footer">

                                        <div class="prospect-row__score">

                                            <?php if ($prospect['score'] !== null): ?>

                                                <span>Score</span>

                                                <strong>
                                                    <?= (int) $prospect['score'] ?>/100
                                                </strong>

                                            <?php else: ?>

                                                <span>Non scoré</span>

                                            <?php endif; ?>

                                        </div>

                                        <div class="prospect-row__actions">

                                            <?php if (!empty($prospect['website_url'])): ?>

                                                <a
                                                    href="<?= htmlspecialchars(
                                                        $prospect['website_url'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="admin-text-link"
                                                >
                                                    Site
                                                    <span aria-hidden="true">↗</span>
                                                </a>

                                            <?php endif; ?>

                                            <a
                                                href="/admin/prospection/prospect.php?id=<?= (int) $prospect['id'] ?>"
                                                class="admin-text-link"
                                            >
                                                Ouvrir
                                                <span aria-hidden="true">→</span>
                                            </a>

                                        </div>

                                    </div>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </section>

            </div>

        </main>

    </div>

    <?php
    include dirname(__DIR__) . '/partials/footer.php';
    ?>

</body>

</html>
