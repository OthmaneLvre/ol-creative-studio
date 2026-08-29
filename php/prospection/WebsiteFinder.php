<?php

declare(strict_types=1);

final class WebsiteFinder
{
    private const API_URL =
        'https://places.googleapis.com/v1/places:searchText';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 10
    ) {
        if (trim($this->apiKey) === '') {
            throw new InvalidArgumentException(
                'La clé Google Places est obligatoire.'
            );
        }
    }

    public function find(
        string $companyName,
        string $city,
        ?string $postalCode = null
    ): ?array {
        $queryParts = [
            trim($companyName),
            trim($city),
        ];

        if ($postalCode !== null && trim($postalCode) !== '') {
            $queryParts[] = trim($postalCode);
        }

        $queryParts[] = 'France';

        $textQuery = implode(
            ' ',
            array_filter($queryParts)
        );

        $payload = [
            'textQuery' => $textQuery,
            'pageSize' => 5,
            'languageCode' => 'fr',
            'regionCode' => 'FR',
        ];

        $response = $this->request($payload);

        if (
            !isset($response['places'])
            || !is_array($response['places'])
        ) {
            return null;
        }

        foreach ($response['places'] as $place) {
            if (!is_array($place)) {
                continue;
            }

            if (
                !$this->isLikelyMatch(
                    $place,
                    $companyName,
                    $city,
                    $postalCode
                )
            ) {
                continue;
            }

            return $this->normalizePlace($place);
        }

        return null;
    }

    private function normalizePlace(array $place): array
    {
        return [
            'place_id' => isset($place['id'])
                ? (string) $place['id']
                : null,

            'name' => isset($place['displayName']['text'])
                ? (string) $place['displayName']['text']
                : null,

            'address' => isset($place['formattedAddress'])
                ? (string) $place['formattedAddress']
                : null,

            'website_url' => isset($place['websiteUri'])
                ? (string) $place['websiteUri']
                : null,

            'phone' => isset($place['nationalPhoneNumber'])
                ? (string) $place['nationalPhoneNumber']
                : null,

            'google_maps_url' => isset($place['googleMapsUri'])
                ? (string) $place['googleMapsUri']
                : null,
        ];
    }

    private function isLikelyMatch(
        array $place,
        string $companyName,
        string $city,
        ?string $postalCode
    ): bool {
        $placeName = isset($place['displayName']['text'])
            ? $this->normalizeText(
                (string) $place['displayName']['text']
            )
            : '';

        $expectedName = $this->normalizeText($companyName);

        $address = isset($place['formattedAddress'])
            ? $this->normalizeText(
                (string) $place['formattedAddress']
            )
            : '';

        $expectedCity = $this->normalizeText($city);

        if (
            $placeName === ''
            || $expectedName === ''
            || $address === ''
        ) {
            return false;
        }

        similar_text(
            $placeName,
            $expectedName,
            $similarity
        );

        if ($similarity < 60.0) {
            return false;
        }

        if (!str_contains($address, $expectedCity)) {
            return false;
        }

        if (
            $postalCode !== null
            && trim($postalCode) !== ''
            && !str_contains(
                $address,
                $this->normalizeText($postalCode)
            )
        ) {
            return false;
        }

        return true;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $transliterated = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $value
        ) ?? $value;

        return trim(
            preg_replace('/\s+/', ' ', $value) ?? $value
        );
    }

    private function request(array $payload): array
    {
        $curl = curl_init(self::API_URL);

        if ($curl === false) {
            throw new RuntimeException(
                'Impossible d’initialiser Google Places.'
            );
        }

        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Goog-Api-Key: ' . $this->apiKey,
                'X-Goog-FieldMask: '
                    . 'places.id,'
                    . 'places.displayName,'
                    . 'places.formattedAddress,'
                    . 'places.websiteUri,'
                    . 'places.nationalPhoneNumber,'
                    . 'places.googleMapsUri',
            ],
        ]);

        $responseBody = curl_exec($curl);

        if ($responseBody === false) {
            $error = curl_error($curl);

            curl_close($curl);

            throw new RuntimeException(
                'Erreur Google Places : ' . $error
            );
        }

        $httpCode = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(
                'Google Places : HTTP '
                . $httpCode
                . ' - '
                . $responseBody
            );
        }

        $decoded = json_decode(
            $responseBody,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return is_array($decoded)
            ? $decoded
            : [];
    }
}
