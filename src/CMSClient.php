<?php
namespace MagicianNews;

use GuzzleHttp\Client;

// Force deployment update - namespace fixes applied

class CMSClient {
    private Client $client;
    private string $apiUrl;
    private string $apiKey;

    public function __construct() {
        // Check for required environment variables
        if (empty($_ENV['CMS_API_URL'])) {
            throw new \Exception("CMS configuration error: CMS_API_URL is missing");
        }

        if (empty($_ENV['CMS_API_KEY'])) {
            throw new \Exception("CMS configuration error: CMS_API_KEY is missing");
        }

        $this->apiUrl = $_ENV['CMS_API_URL'];
        $this->apiKey = $_ENV['CMS_API_KEY'];

        try {
            $this->client = new Client([
                'base_uri' => $this->apiUrl,
                'timeout' => 10.0,
                'verify' => false, // Disable SSL verification (server lacks CA certs)
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);
        } catch (\Exception $e) {
            throw new \Exception("Failed to initialize CMS client: " . $e->getMessage());
        }
    }

    public function getArticles(int $limit = 10, int $page = 1): array {
        try {
            $url = $this->apiUrl . '/api/articles?limit=' . $limit . '&page=' . $page . '&sort=-createdAt';

            $response = $this->client->get('/api/articles', [
                'query' => [
                    'limit' => $limit,
                    'page' => $page,
                    'sort' => '-createdAt'
                ]
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($data === null) {
                throw new \Exception("Invalid JSON from CMS. URL: $url, Body: " . substr($body, 0, 100));
            }

            return $data;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch articles: " . $e->getMessage());
        }
    }

    public function getArticle(string $id): array {
        try {
            $response = $this->client->get("/api/articles/{$id}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            throw new \Exception("Article not found: " . $e->getMessage());
        }
    }

    public function searchArticles(string $query): array {
        try {
            $response = $this->client->get('/api/articles', [
                'query' => [
                    'where' => [
                        'or' => [
                            ['title' => ['like' => $query]],
                            ['content' => ['like' => $query]]
                        ]
                    ]
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            throw new \Exception("Search failed: " . $e->getMessage());
        }
    }
}
