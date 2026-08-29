<?php
// admin/partials/header.php

$pageTitle = $adminPageTitle ?? 'Administration';

?>

<header class="admin-header">

    <div class="admin-header__content">

        <div>

            <span class="admin-header__eyebrow">
                Administration
            </span>

            <p class="admin-header__title">
                <?= htmlspecialchars(
                    $pageTitle,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

        </div>


        <div class="admin-header__user">

            <div class="admin-header__user-meta">

                <span class="admin-header__user-label">
                    Connecté en tant que
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $_SESSION['admin_name'] ?? 'Admin',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>

            <span
                class="admin-header__status"
                aria-hidden="true"
            ></span>

        </div>

    </div>

</header>
