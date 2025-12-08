<?php

use PHPUnit\Framework\TestCase;
use Michaelcarrier\LaravelDocGenerator\Analyzers\ClaudeAnalyzer;

class ClaudeAnalyzerTest extends TestCase
{
    public function testAnalyzeMethod()
    {
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');

        if (!$apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }
        
        $analyzer = new ClaudeAnalyzer($apiKey);
        
        $methodData = [
            'name' => 'store',
            'params' => [
                ['name' => 'request', 'type' => 'UserRequest']
            ],
            'returnType' => 'JsonResponse',
            'code' => 'public function store(UserRequest $request): JsonResponse { ... }',
        ];
        
        $result = $analyzer->analyzeMethod('UserController', $methodData);
        
        $this->assertArrayHasKey('phpdoc', $result);
        $this->assertStringContainsString('/**', $result['phpdoc']);
    }
}