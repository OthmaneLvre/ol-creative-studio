<?php

declare(strict_types=1);

final class ProspectScorer
{
    private const QUALIFICATION_THRESHOLD = 60;

    public function score(
        array $prospect,
        array $analysis
    ): array {
        $commercial = $this->scoreCommercialPotential(
            $prospect,
            $analysis
        );

        $websiteGaps = $this->scoreWebsiteGaps(
            $analysis
        );

        $digitalPresence = $this->scoreDigitalPresence(
            $analysis
        );

        $businessFit = $this->scoreBusinessFit(
            $prospect
        );

        $contactability = $this->scoreContactability(
            $prospect,
            $analysis
        );

        $total =
            $commercial['score']
            + $websiteGaps['score']
            + $digitalPresence['score']
            + $businessFit['score']
            + $contactability['score'];

        $total = min(100, max(0, $total));

        return [
            'score' => $total,

            'qualified' =>
                $total >= self::QUALIFICATION_THRESHOLD,

            'threshold' =>
                self::QUALIFICATION_THRESHOLD,

            'breakdown' => [
                'commercial_potential' => $commercial,
                'website_gaps' => $websiteGaps,
                'digital_presence' => $digitalPresence,
                'business_fit' => $businessFit,
                'contactability' => $contactability,
            ],

            'scored_at' =>
                (new DateTimeImmutable())->format(
                    DATE_ATOM
                ),
        ];
    }

    /**
     * Potentiel commercial : 30 points.
     *
     * Mesure si le prospect représente une vraie
     * opportunité commerciale exploitable.
     *
     * La joignabilité est volontairement exclue ici
     * car elle possède sa propre catégorie.
     */
    private function scoreCommercialPotential(
        array $prospect,
        array $analysis
    ): array {
        $score = 0;
        $reasons = [];

        if (
            !empty($prospect['company_name'])
            && !empty($prospect['city'])
            && !empty($prospect['postal_code'])
        ) {
            $score += 8;

            $reasons[] =
                'Entreprise locale clairement identifiée.';
        }

        if (
            !empty($prospect['website_url'])
            && ($analysis['website_reachable'] ?? false)
        ) {
            $score += 7;

            $reasons[] =
                'Site web existant et accessible.';
        }

        $gapCount = $this->countWebsiteGaps(
            $analysis
        );

        if ($gapCount >= 6) {
            $score += 10;

            $reasons[] =
                'Nombreuses améliorations concrètes détectées.';
        } elseif ($gapCount >= 4) {
            $score += 8;

            $reasons[] =
                'Plusieurs améliorations concrètes détectées.';
        } elseif ($gapCount >= 2) {
            $score += 5;

            $reasons[] =
                'Plusieurs axes d’amélioration détectés.';
        } elseif ($gapCount === 1) {
            $score += 2;

            $reasons[] =
                'Un axe d’amélioration détecté.';
        }

        return [
            'score' => min(30, $score),
            'max' => 30,
            'reasons' => $reasons,
        ];
    }

    /**
     * Lacunes du site : 25 points.
     *
     * Plus le site présente de lacunes objectivement
     * détectables, plus le potentiel d'intervention est fort.
     */
    private function scoreWebsiteGaps(
        array $analysis
    ): array {
        $score = 0;
        $reasons = [];

        $title = strtolower(
            trim(
                (string) (
                    $analysis['title']
                    ?? ''
                )
            )
        );

        if (
            $title === ''
            || in_array(
                $title,
                [
                    'home',
                    'accueil',
                    'homepage',
                    'index',
                ],
                true
            )
        ) {
            $score += 6;

            $reasons[] =
                'Titre de page absent ou trop générique.';
        }

        if (
            !($analysis['has_meta_description'] ?? false)
        ) {
            $score += 5;

            $reasons[] =
                'Meta description absente.';
        }

        if (
            !($analysis['has_contact_page'] ?? false)
        ) {
            $score += 4;

            $reasons[] =
                'Aucune page contact dédiée détectée.';
        }

        if (
            !($analysis['has_social_links'] ?? false)
        ) {
            $score += 3;

            $reasons[] =
                'Aucun réseau social détecté sur le site.';
        }

        if (
            !($analysis['has_favicon'] ?? false)
        ) {
            $score += 2;

            $reasons[] =
                'Favicon non détecté.';
        }

        if (
            !($analysis['has_open_graph'] ?? false)
        ) {
            $score += 2;

            $reasons[] =
                'Métadonnées Open Graph absentes.';
        }

        if (
            !($analysis['has_json_ld'] ?? false)
        ) {
            $score += 3;

            $reasons[] =
                'Données structurées JSON-LD absentes.';
        }

        return [
            'score' => min(25, $score),
            'max' => 25,
            'reasons' => $reasons,
        ];
    }

    /**
     * Présence numérique : 20 points.
     *
     * Ne mesure pas la perfection du site.
     * Mesure seulement son niveau de structuration technique.
     */
    private function scoreDigitalPresence(
        array $analysis
    ): array {
        $score = 0;
        $reasons = [];

        if (
            $analysis['website_reachable']
            ?? false
        ) {
            $score += 2;

            $reasons[] =
                'Site web accessible.';
        }

        if ($analysis['has_ssl'] ?? false) {
            $score += 2;

            $reasons[] =
                'HTTPS actif.';
        }

        /*
        * V1 :
        * présence d'une balise viewport uniquement.
        * Ce signal ne prouve pas à lui seul
        * que le site est réellement responsive.
        */
        if (
            $analysis['is_mobile_friendly']
            ?? false
        ) {
            $score += 2;

            $reasons[] =
                'Configuration viewport mobile détectée.';
        }

        if (
            $analysis['has_canonical']
            ?? false
        ) {
            $score += 1;

            $reasons[] =
                'URL canonique présente.';
        }

        if (
            $analysis['has_open_graph']
            ?? false
        ) {
            $score += 2;

            $reasons[] =
                'Open Graph présent.';
        }

        if (
            $analysis['has_json_ld']
            ?? false
        ) {
            $score += 2;

            $reasons[] =
                'Données structurées présentes.';
        }

        if (
            $analysis['has_contact_form']
            ?? false
        ) {
            $score += 2;

            $reasons[] =
                'Formulaire de contact présent.';
        }

        if (
            ($analysis['robots_txt'] ?? false)
            && ($analysis['sitemap_xml'] ?? false)
        ) {
            $score += 1;

            $reasons[] =
                'robots.txt et sitemap.xml détectés.';
        }

        if (
            $analysis['has_social_links']
            ?? false
        ) {
            $score += 3;

            $reasons[] =
                'Présence de réseaux sociaux reliés au site.';
        }

        if (
            $analysis['has_favicon']
            ?? false
        ) {
            $score += 1;

            $reasons[] =
                'Favicon détecté.';
        }

        $title = strtolower(
            trim(
                (string) (
                    $analysis['title']
                    ?? ''
                )
            )
        );

        if (
            $title !== ''
            && !in_array(
                $title,
                [
                    'home',
                    'accueil',
                    'homepage',
                    'index',
                ],
                true
            )
        ) {
            $score += 1;

            $reasons[] =
                'Titre de page exploitable.';
        }

        if (
            $analysis['has_meta_description']
            ?? false
        ) {
            $score += 1;

            $reasons[] =
                'Meta description présente.';
        }

        return [
            'score' => min(20, $score),
            'max' => 20,
            'reasons' => $reasons,
        ];
    }

    /**
     * Adéquation métier : 15 points.
     */
    private function scoreBusinessFit(
        array $prospect
    ): array {
        $activity = strtolower(
            trim(
                (string) (
                    $prospect['activity']
                    ?? ''
                )
            )
        );

        $score = 0;
        $reasons = [];

        if ($activity === '') {
            return [
                'score' => 0,
                'max' => 15,
                'reasons' => [
                    'Activité non renseignée.',
                ],
            ];
        }

        $highFitCodes = [
            '43.22',
            '43.21',
            '43.29',
            '43.31',
            '43.32',
            '43.33',
            '43.34',
            '43.39',
            '43.91',
            '43.99',
            '41.20',
            '56.',
            '96.02',
            '96.04',
        ];

        foreach ($highFitCodes as $code) {
            if (
                str_starts_with(
                    $activity,
                    strtolower($code)
                )
            ) {
                $score = 15;

                $reasons[] =
                    'Activité locale fortement compatible avec les services proposés.';

                break;
            }
        }

        if ($score === 0) {
            $keywords = [
                'artisan',
                'plomb',
                'chauff',
                'électric',
                'menuis',
                'maçon',
                'peint',
                'coiff',
                'beauté',
                'esthétique',
                'restaurant',
                'commerce',
                'garage',
                'paysag',
                'immobilier',
                'construction',
            ];

            foreach ($keywords as $keyword) {
                if (
                    str_contains(
                        $activity,
                        $keyword
                    )
                ) {
                    $score = 12;

                    $reasons[] =
                        'Métier local compatible avec une acquisition numérique.';

                    break;
                }
            }
        }

        if ($score === 0) {
            $score = 5;

            $reasons[] =
                'Activité exploitable mais non prioritaire.';
        }

        return [
            'score' => min(15, $score),
            'max' => 15,
            'reasons' => $reasons,
        ];
    }

    /**
     * Contactabilité : 10 points.
     *
     * Ici uniquement :
     * peut-on réellement contacter ce prospect ?
     */
    private function scoreContactability(
        array $prospect,
        array $analysis
    ): array {
        $score = 0;
        $reasons = [];

        if (!empty($prospect['email'])) {
            $score += 7;

            $reasons[] =
                'Adresse e-mail professionnelle disponible.';
        }

        if (!empty($prospect['phone'])) {
            $score += 2;

            $reasons[] =
                'Numéro de téléphone disponible.';
        }

        if (
            ($analysis['has_contact_form'] ?? false)
            && empty($prospect['email'])
        ) {
            $score += 1;

            $reasons[] =
                'Formulaire de contact disponible.';
        }

        return [
            'score' => min(10, $score),
            'max' => 10,
            'reasons' => $reasons,
        ];
    }

    private function countWebsiteGaps(
        array $analysis
    ): int {
        $gaps = 0;

        $title = strtolower(
            trim(
                (string) (
                    $analysis['title']
                    ?? ''
                )
            )
        );

        if (
            $title === ''
            || in_array(
                $title,
                [
                    'home',
                    'accueil',
                    'homepage',
                    'index',
                ],
                true
            )
        ) {
            $gaps++;
        }

        if (
            !($analysis['has_meta_description'] ?? false)
        ) {
            $gaps++;
        }

        if (
            !($analysis['has_contact_page'] ?? false)
        ) {
            $gaps++;
        }

        if (
            !($analysis['has_social_links'] ?? false)
        ) {
            $gaps++;
        }

        if (
            !($analysis['has_favicon'] ?? false)
        ) {
            $gaps++;
        }

        if (
            !($analysis['has_open_graph'] ?? false)
        ) {
            $gaps++;
        }

        if (
            !($analysis['has_json_ld'] ?? false)
        ) {
            $gaps++;
        }

        return $gaps;
    }
}
