<?php
namespace MagicianNews;

use GuzzleHttp\Client;

class CMSClient {
    private Client $client;
    private string $apiUrl;
    private string $apiKey;

    public function __construct() {
        // Check for required environment variables
        if (empty($_ENV['CMS_API_URL'])) {
            error_log("CMSClient Error: CMS_API_URL environment variable is not set");
            throw new \Exception("CMS configuration error: CMS_API_URL is missing");
        }

        if (empty($_ENV['CMS_API_KEY'])) {
            error_log("CMSClient Error: CMS_API_KEY environment variable is not set");
            throw new \Exception("CMS configuration error: CMS_API_KEY is missing");
        }

        $this->apiUrl = $_ENV['CMS_API_URL'];
        $this->apiKey = $_ENV['CMS_API_KEY'];

        try {
            $this->client = new Client([
                'base_uri' => $this->apiUrl,
                'timeout' => 10.0,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (\Exception $e) {
            error_log("CMSClient Error: Failed to initialize Guzzle client - " . $e->getMessage());
            throw new \Exception("Failed to initialize CMS client");
        }
    }

    public function getArticles(int $limit = 10, int $page = 1): array {
        try {
            $response = $this->client->get('/api/articles', [
                'query' => [
                    'limit' => $limit,
                    'page' => $page,
                    'sort' => '-createdAt'
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            error_log("CMS API Error: " . $e->getMessage());
            throw new \Exception("Failed to fetch articles");
        }
    }

    public function getArticle(string $id): array {
        try {
            $response = $this->client->get("/api/articles/{$id}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            error_log("CMS API Error: " . $e->getMessage());
            throw new \Exception("Article not found");
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
            error_log("CMS API Error: " . $e->getMessage());
            throw new \Exception("Search failed");
        }
    }
}
