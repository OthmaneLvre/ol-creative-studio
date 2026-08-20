<?php
// admin/partials/sidebar.php

$activePage = $adminActivePage ?? '';

?>

<aside class="admin-sidebar">

    <div class="admin-sidebar__brand">

        <a
            href="/admin/dashboard.php"
            class="admin-sidebar__logo"
            aria-label="Administration OL Creative Studio"
        >
            OL Creative Studio
        </a>

        <span class="admin-sidebar__badge">
            Admin
        </span>

    </div>


    <nav
        class="admin-sidebar__nav"
        aria-label="Navigation administration"
    >

        <a
            href="/admin/dashboard.php"
            class="admin-sidebar__link <?= $activePage === 'dashboard' ? 'is-active' : '' ?>"
        >
            <span>Vue d’ensemble</span>
        </a>

        <a
            href="/admin/portfolio.php"
            class="admin-sidebar__link <?= $activePage === 'portfolio' ? 'is-active' : '' ?>"
        >
            <span>Portfolio</span>
        </a>

        <a
            href="/admin/avis.php"
            class="admin-sidebar__link <?= $activePage === 'avis' ? 'is-active' : '' ?>"
        >
            <span>Avis clients</span>
        </a>

    </nav>


    <div class="admin-sidebar__footer">

        <a
            href="/"
            target="_blank"
            rel="noopener noreferrer"
            class="admin-sidebar__secondary-link"
        >
            Voir le site
            <span aria-hidden="true">↗</span>
        </a>

        <a
            href="/admin/logout.php"
            class="admin-sidebar__logout"
        >
            Déconnexion
        </a>

    </div>

</aside>
