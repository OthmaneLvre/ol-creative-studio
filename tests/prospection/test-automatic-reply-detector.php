<?php

declare(strict_types=1);

require __DIR__
    . '/../../php/prospection/AutomaticReplyDetector.php';

$detector =
    new AutomaticReplyDetector();

$tests = [
    [
        'name' => 'Sujet absence du bureau',
        'subject' => 'Réponse automatique : absence du bureau',
        'body' => '',
        'expected' => true,
    ],
    [
        'name' => 'Sujet anglais out of office',
        'subject' => 'Out of Office: Re: Votre site internet',
        'body' => '',
        'expected' => true,
    ],
    [
        'name' => 'Corps absence français',
        'subject' => 'Re: Votre site internet',
        'body' =>
            'Bonjour, je suis actuellement absent du bureau jusqu’au 12 septembre.',
        'expected' => true,
    ],
    [
        'name' => 'Accusé de réception',
        'subject' => 'Re: Votre demande',
        'body' =>
            'Nous avons bien reçu votre message et reviendrons vers vous prochainement.',
        'expected' => true,
    ],
    [
        'name' => 'Automatic reply anglais',
        'subject' => 'Re: Website',
        'body' =>
            'This is an automatic reply. I am currently out of the office.',
        'expected' => true,
    ],
    [
        'name' => 'Réponse humaine positive',
        'subject' => 'Re: Votre site internet',
        'body' =>
            'Bonjour, votre proposition peut m’intéresser. Pouvez-vous me rappeler ?',
        'expected' => false,
    ],
    [
        'name' => 'Réponse humaine négative',
        'subject' => 'Re: Votre site internet',
        'body' =>
            'Bonjour, merci mais nous ne sommes pas intéressés pour le moment.',
        'expected' => false,
    ],
    [
        'name' => 'Mention du mot automatique sans auto-réponse',
        'subject' => 'Re: Votre site internet',
        'body' =>
            'Nous avons déjà mis en place un système automatique sur notre site.',
        'expected' => false,
    ],
    [
        'name' => 'Mention absence sans auto-réponse',
        'subject' => 'Re: Votre site internet',
        'body' =>
            'Notre ancienne webmaster est absente mais vous pouvez me contacter directement.',
        'expected' => false,
    ],
];

$failed = 0;

foreach ($tests as $test) {
    $actual =
        $detector->isAutomaticReply(
            [
                'subject' =>
                    $test['subject'],
            ],
            $test['body']
        );

    $passed =
        $actual === $test['expected'];

    echo sprintf(
        "[%s] %-45s attendu=%s obtenu=%s\n",
        $passed ? 'OK' : 'FAIL',
        $test['name'],
        $test['expected']
            ? 'true'
            : 'false',
        $actual
            ? 'true'
            : 'false'
    );

    if (!$passed) {
        $failed++;
    }
}

echo "\n";

if ($failed > 0) {
    echo "ECHEC : {$failed} test(s).\n";
    exit(1);
}

echo "Tous les tests sont OK.\n";
