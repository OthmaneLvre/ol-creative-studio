<?php

declare(strict_types=1);

/**
 * Configuration du module de prospection.
 *
 * Les valeurs sensibles (API, SMTP, etc.) ne doivent pas être stockées ici
 * si elles peuvent être placées dans le fichier .env.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Découverte quotidienne
    |--------------------------------------------------------------------------
    */

    'discovery' => [
        'enabled' => true,

        // Nombre de nouveaux prospects à préparer chaque jour.
        'daily_target' => 3,

        // Suspension automatique de la recherche lorsque cette limite
        // de prospects en attente est atteinte.
        'max_pending_prospects' => 30,

        // Les communes doivent être parcourues équitablement.
        'balance_cities_equally' => true,

        // Heure prévue pour lancer la préparation quotidienne.
        'preparation_time' => '09:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Analyse et scoring
    |--------------------------------------------------------------------------
    */

    'analysis' => [

        // Score global attendu entre 0 et 100.
        'score_min' => 0,
        'score_max' => 100,

        // Pondérations utilisées pour le scoring.
        // Total : 100.
        'weights' => [
            'design' => 25,
            'mobile' => 20,
            'seo' => 20,
            'technical' => 20,
            'performance' => 15,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    'validation' => [

        // Les prospects non validés restent dans la file.
        'keep_unvalidated' => true,

        // Aucun envoi automatique sans validation humaine.
        'require_manual_approval' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Envois
    |--------------------------------------------------------------------------
    */

    'sending' => [

        'enabled' => false,

        // Adresse utilisée pour les campagnes de prospection.
        'sender_email' => 'contact@olcreativestudio.fr',

        // Nom affiché comme expéditeur.
        'sender_name' => 'OL Creative Studio',

        // Heure d'envoi des messages validés.
        'send_time' => '09:30',

        // Nombre maximum d'envois par jour.
        'max_daily_sends' => 5,

        // Les envois doivent être programmés uniquement les jours ouvrés.
        'business_days_only' => true,

        // Nombre maximal de tentatives après un échec technique.
        'max_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Relances
    |--------------------------------------------------------------------------
    */

    'follow_up' => [

        'enabled' => true,

        // Délais exprimés en jours.
        'delays' => [
            'follow_up_1' => 4,
            'follow_up_2' => 10,
        ],

        // Dès qu'une réponse est détectée, aucune relance ne doit partir.
        'stop_on_reply' => true,

        // Aucun message pour une adresse désinscrite.
        'stop_on_unsubscribe' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Aperçus visuels personnalisés
    |--------------------------------------------------------------------------
    */

    'visual_previews' => [
        'enabled' => true,

        // Limite définie pour éviter une génération trop coûteuse.
        'max_per_week' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications administrateur
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'email' => true,
        'admin' => true,

        // Notification lorsque les prospects quotidiens sont prêts.
        'notify_when_ready' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conformité
    |--------------------------------------------------------------------------
    */

    'compliance' => [

        // Vérification obligatoire avant tout envoi.
        'check_unsubscribe_list' => true,

        // La provenance du prospect doit être conservée.
        'store_source' => true,

        // Les emails détectés doivent provenir de sources publiques.
        'public_emails_only' => true,
    ],
];
