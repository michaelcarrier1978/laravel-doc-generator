<?php

namespace LaravelDocs\Generator\Analyzers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ClaudeAnalyzer
{
    private Client $client;
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Anthropic API key cannot be empty');
        }

        $this->apiKey = trim($apiKey);

        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    public function analyzeMethod(string $className, array $methodData): array
    {
        try {
            $prompt = $this->buildMethodPrompt($className, $methodData);

            $response = $this->client->post('/v1/messages', [
                'json' => [
                    'model' => 'claude-3-haiku-20240307',
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

            if (!isset($result['content'][0]['text'])) {
                throw new \RuntimeException('Unexpected response format from Claude API');
            }

            return $this->parseResponse($result['content'][0]['text']);
        } catch (GuzzleException $e) {
            $message = $e->getMessage();

            if (strpos($message, '401') !== false) {
                throw new \RuntimeException('Invalid Anthropic API key. Please check your ANTHROPIC_API_KEY in .env file', 0, $e);
            } elseif (strpos($message, '429') !== false) {
                throw new \RuntimeException('Rate limit exceeded. Please wait and try again', 0, $e);
            } elseif (strpos($message, '500') !== false) {
                throw new \RuntimeException('Anthropic API is experiencing issues. Please try again later', 0, $e);
            }

            throw new \RuntimeException('Failed to analyze method with Claude: ' . $message, 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException('Error analyzing method: ' . $e->getMessage(), 0, $e);
        }
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