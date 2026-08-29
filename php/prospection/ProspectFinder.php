<?php

declare(strict_types=1);

final class ProspectFinder
{
    private const API_BASE_URL = 'https://recherche-entreprises.api.gouv.fr/search';

    public function __construct(
        private readonly int $timeout = 10
    ) {
    }

    /**
     * Recherche des entreprises correspondant à une activité
     * dans une commune donnée.
     */
    public function search(
        string $activity,
        string $city,
        ?string $postalCode = null,
        int $limit = 10
    ): array {
        $query = trim($activity . ' ' . $city);

        $parameters = [
            'q' => $query,
            'page' => 1,
            'per_page' => max(1, min($limit, 25)),
        ];

        $url = self::API_BASE_URL . '?' . http_build_query($parameters);

        $response = $this->request($url);

        if (!isset($response['results']) || !is_array($response['results'])) {
            return [];
        }

        $companies = [];

        foreach ($response['results'] as $result) {
            if (!is_array($result)) {
                continue;
            }

            $company = $this->normalizeCompany(
                $result,
                $city,
                $postalCode
            );

            if ($company !== null) {
                $companies[] = $company;
            }
        }

        return $companies;
    }

    /**
     * Normalise une entreprise retournée par l'API.
     */
    private function normalizeCompany(
        array $result,
        string $expectedCity,
        ?string $expectedPostalCode
    ): ?array {
        $siege = isset($result['siege']) && is_array($result['siege'])
            ? $result['siege']
            : [];

        $companyName = $result['nom_complet']
            ?? $result['nom_raison_sociale']
            ?? null;

        if (!is_string($companyName) || trim($companyName) === '') {
            return null;
        }

        $city = $siege['libelle_commune'] ?? null;
        $postalCode = $siege['code_postal'] ?? null;

        /*
         * On évite de conserver un résultat manifestement hors zone.
         */
        if (
            $expectedPostalCode !== null
            && is_string($postalCode)
            && $postalCode !== ''
            && $postalCode !== $expectedPostalCode
        ) {
            return null;
        }

        return [
            'company_name' => trim($companyName),

            'activity' => $result['activite_principale']
                ?? $siege['activite_principale']
                ?? null,

            'city' => is_string($city) && $city !== ''
                ? $city
                : $expectedCity,

            'postal_code' => is_string($postalCode)
                ? $postalCode
                : $expectedPostalCode,

            'siren' => $result['siren'] ?? null,
            'siret' => $siege['siret'] ?? null,

            'address' => $siege['adresse'] ?? null,
            
            'source' => 'api_recherche_entreprises',

            'source_external_id' => isset($siege['siret'])
                ? (string) $siege['siret']
                : (
                    isset($result['siren'])
                        ? (string) $result['siren']
                        : null
                ),

            'source_url' => null,

            /*
             * Ces données seront enrichies dans les étapes suivantes.
             */
            'website_url' => null,
            'email' => null,
            'phone' => null,
        ];
    }

    /**
     * Effectue une requête HTTP GET et décode la réponse JSON.
     */
    private function request(string $url): array
    {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'Impossible d’initialiser la requête HTTP.'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: OL-Creative-Studio-Prospection/1.0',
            ],
        ]);

        $body = curl_exec($curl);

        if ($body === false) {
            $error = curl_error($curl);

            curl_close($curl);

            throw new RuntimeException(
                'Erreur API Recherche d’Entreprises : ' . $error
            );
        }

        $httpCode = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(
                'API Recherche d’Entreprises : HTTP ' . $httpCode
            );
        }

        $decoded = json_decode(
            $body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Réponse JSON invalide.'
            );
        }

        return $decoded;
    }
}
