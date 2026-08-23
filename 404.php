<?php

declare(strict_types=1);

http_response_code(404);

$pageTitle = 'Page introuvable';

$pageDescription =
    'La page demandée est introuvable. '
    . 'Retournez à l’accueil ou consultez les services '
    . 'et réalisations d’OL Creative Studio.';

$pageRobots = 'noindex, follow';

$pageCssKey = '404';

include_once __DIR__ . '/partials/header.php';

?>

<main class="page-content">

    <section class="error-page">

        <div class="container">

            <div class="error-page__content">

                <span class="error-page__eyebrow">
                    Erreur 404
                </span>

                <h1 class="error-page__title">
                    Cette page
                    <em>n’existe pas.</em>
                </h1>

                <p class="error-page__text">
                    Le lien que vous avez suivi est peut-être ancien,
                    incorrect ou la page a été déplacée.
                </p>

                <div class="error-page__actions">

                    <a
                        href="/"
                        class="button button--primary button--lg"
                    >
                        Retour à l’accueil
                    </a>

                    <a
                        href="/portfolio.php"
                        class="button button--secondary button--lg"
                    >
                        Voir le portfolio
                    </a>

                </div>

            </div>

        </div>

    </section>

</main>

<?php
include_once __DIR__ . '/partials/footer.php';
?>