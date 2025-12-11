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
                    'max_tokens' => 4096,
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
You are a technical documentation expert analyzing Laravel controller code. Provide comprehensive, detailed documentation.

Class: {$className}
Method: {$methodData['name']}
Parameters: {$params}
Return Type: {$methodData['returnType']}

Code:
{$methodData['code']}

Analyze this method deeply and generate a PHPDoc comment with:

1. **Summary**: One clear sentence explaining the primary purpose

2. **Detailed Description**: Write 4-6 sentences covering:
   - What problem this method solves
   - The main steps/algorithm it follows
   - Key business logic or rules it implements
   - Any important side effects or state changes
   - How it handles edge cases or errors

3. **Database Queries**: If there are SQL queries or Eloquent queries:
   - Explain what each query retrieves
   - Describe the relationships and joins
   - Mention any CTEs, subqueries, or complex logic
   - Explain the WHERE conditions and their purpose

4. **Parameter Documentation**: For each parameter, explain:
   - What it represents
   - How it's used in the method
   - Any constraints or expected values

5. **Return Documentation**: Explain:
   - What data structure is returned
   - What the returned data contains
   - Different scenarios that affect the return value

6. **Exception Documentation**: List any exceptions thrown

Be SPECIFIC. Reference actual variable names, table names, conditions, and logic from the code.
Avoid generic phrases like "processes data" - instead say exactly what data and how.

Output ONLY the PHPDoc comment block with /** ... */ formatting.
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