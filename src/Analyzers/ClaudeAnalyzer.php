<?php

namespace Michaelcarrier\LaravelDocGenerator\Analyzers;

use GuzzleHttp\Client;

class ClaudeAnalyzer
{
    private Client $client;
    private string $apiKey;
    
    public function __construct(string $apiKey)
    {
        $this->apiKey = trim($apiKey);

        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
        ]);
    }
    
    public function analyzeMethod(string $className, array $methodData): array
    {
        $prompt = $this->buildMethodPrompt($className, $methodData);
        
        $response = $this->client->post('/v1/messages', [
            'json' => [
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ],
        ]);
        
        $result = json_decode($response->getBody()->getContents(), true);
        
        return $this->parseResponse($result['content'][0]['text']);
    }
    
    private function buildMethodPrompt(string $className, array $methodData): string
    {
        $params = implode(', ', array_map(
            fn($p) => ($p['type'] ?? '') . ' $' . $p['name'],
            $methodData['params']
        ));
        
        return <<<PROMPT
Analyze this Laravel controller method and provide documentation.

Class: {$className}
Method: {$methodData['name']}
Parameters: {$params}
Return Type: {$methodData['returnType']}

Code:
{$methodData['code']}

Generate a PHPDoc comment including:
1. One-sentence summary (what this method does)
2. Detailed description (2-3 sentences about the logic and flow)
3. @param tags for each parameter with description
4. @return tag with description
5. @throws tags for any exceptions (if applicable)

Output ONLY the PHPDoc comment block, formatted correctly with proper indentation.
PROMPT;
    }
    
    private function parseResponse(string $response): array
    {
        // Extract the PHPDoc comment from response
        // For now, just return the raw response
        // You'll refine this to parse structured output
        
        return [
            'phpdoc' => trim($response),
        ];
    }
}