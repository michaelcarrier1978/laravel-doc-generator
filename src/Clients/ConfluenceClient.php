<?php

namespace LaravelDocs\Generator\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ConfluenceClient
{
    private $client;
    private $baseUrl;
    private $email;
    private $apiToken;
    
    public function __construct(string $baseUrl, string $email, string $apiToken)
    {
        if (empty($baseUrl) || empty($email) || empty($apiToken)) {
            throw new \InvalidArgumentException('Confluence credentials (base URL, email, API token) cannot be empty');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->email = $email;
        $this->apiToken = $apiToken;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'auth' => [$this->email, $this->apiToken],
            'timeout' => 30,
        ]);
    }
    
    /**
     * Create a new page in Confluence
     */
    public function createPage(string $spaceKey, string $title, string $content, ?string $parentId = null): array
    {
        $body = [
            'type' => 'page',
            'title' => $title,
            'space' => ['key' => $spaceKey],
            'body' => [
                'storage' => [
                    'value' => $content,
                    'representation' => 'storage',
                ],
            ],
        ];
        
        if ($parentId) {
            $body['ancestors'] = [['id' => $parentId]];
        }
        
        try {
            $response = $this->client->post('/wiki/rest/api/content', [
                'json' => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $message = $e->getMessage();

            if (strpos($message, '401') !== false) {
                throw new \RuntimeException('Unauthorized: Invalid Confluence credentials. Check CONFLUENCE_EMAIL and CONFLUENCE_API_TOKEN', 0, $e);
            } elseif (strpos($message, '403') !== false) {
                throw new \RuntimeException('Forbidden: You do not have permission to create pages in this space', 0, $e);
            } elseif (strpos($message, '404') !== false) {
                throw new \RuntimeException('Not Found: Space key "' . $spaceKey . '" does not exist', 0, $e);
            }

            throw new \RuntimeException("Failed to create Confluence page: " . $message, 0, $e);
        }
    }
    
    /**
     * Update an existing page
     */
    public function updatePage(string $pageId, string $title, string $content, int $version): array
    {
        $body = [
            'version' => ['number' => $version + 1],
            'title' => $title,
            'type' => 'page',
            'body' => [
                'storage' => [
                    'value' => $content,
                    'representation' => 'storage',
                ],
            ],
        ];
        
        try {
            $response = $this->client->put("/wiki/rest/api/content/{$pageId}", [
                'json' => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $message = $e->getMessage();

            if (strpos($message, '409') !== false) {
                throw new \RuntimeException('Version conflict: The page has been modified. Please refresh and try again', 0, $e);
            } elseif (strpos($message, '401') !== false) {
                throw new \RuntimeException('Unauthorized: Invalid Confluence credentials', 0, $e);
            } elseif (strpos($message, '403') !== false) {
                throw new \RuntimeException('Forbidden: You do not have permission to update this page', 0, $e);
            }

            throw new \RuntimeException("Failed to update Confluence page: " . $message, 0, $e);
        }
    }
    
    /**
     * Get a page by title in a space
     */
    public function getPageByTitle(string $spaceKey, string $title): ?array
    {
        try {
            $response = $this->client->get('/wiki/rest/api/content', [
                'query' => [
                    'spaceKey' => $spaceKey,
                    'title' => $title,
                    'expand' => 'version,body.storage',
                ],
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!empty($data['results'])) {
                return $data['results'][0];
            }
            
            return null;
        } catch (GuzzleException $e) {
            throw new \Exception("Failed to get Confluence page: " . $e->getMessage());
        }
    }
    
    /**
     * Get page content by ID
     */
    public function getPage(string $pageId): array
    {
        try {
            $response = $this->client->get("/wiki/rest/api/content/{$pageId}", [
                'query' => [
                    'expand' => 'version,body.storage',
                ],
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \Exception("Failed to get Confluence page: " . $e->getMessage());
        }
    }
}