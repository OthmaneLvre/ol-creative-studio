<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Protection MIME sniffing
|--------------------------------------------------------------------------
*/

header(
    'X-Content-Type-Options: nosniff'
);


/*
|--------------------------------------------------------------------------
| Protection contre l'intégration dans une iframe
|--------------------------------------------------------------------------
*/

header(
    'X-Frame-Options: DENY'
);


/*
|--------------------------------------------------------------------------
| Referrer
|--------------------------------------------------------------------------
*/

header(
    'Referrer-Policy: strict-origin-when-cross-origin'
);


/*
|--------------------------------------------------------------------------
| Permissions navigateur
|--------------------------------------------------------------------------
*/

header(
    'Permissions-Policy: '
    . 'camera=(), '
    . 'microphone=(), '
    . 'geolocation=(), '
    . 'payment=(), '
    . 'usb=()'
);


/*
|--------------------------------------------------------------------------
| Content Security Policy
|--------------------------------------------------------------------------
|
| Google Fonts est actuellement utilisé dans l'admin.
| blob: est nécessaire aux previews d'images via URL.createObjectURL().
|
*/

$csp = [
    "default-src 'self'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
    "object-src 'none'",

    "script-src 'self'",

    "style-src 'self' https://fonts.googleapis.com",

    "font-src 'self' https://fonts.gstatic.com",

    "img-src 'self' data: blob:",

    "connect-src 'self'",

    "media-src 'self'",

    "manifest-src 'self'",
];

header(
    'Content-Security-Policy: '
    . implode('; ', $csp)
);


/*
|--------------------------------------------------------------------------
| Cache
|--------------------------------------------------------------------------
|
| On évite que les pages sensibles de l'administration restent
| dans le cache navigateur.
|
*/

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);