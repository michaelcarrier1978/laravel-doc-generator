<?php

namespace LaravelDocs\Generator\Commands;

use Illuminate\Console\Command;
use LaravelDocs\Generator\Parsers\ControllerParser;
use LaravelDocs\Generator\Analyzers\ClaudeAnalyzer;
use LaravelDocs\Generator\Clients\ConfluenceClient;
use LaravelDocs\Generator\Formatters\ConfluenceFormatter;

class PublishToConfluence extends Command
{
    protected $signature = 'docs:publish {file : Path to controller file} {--space= : Confluence space key} {--parent-id= : Parent page ID}';
    protected $description = 'Generate and publish controller documentation to Confluence';

    /**
     * Get configuration value (supports both Laravel and standalone)
     */
    protected function getConfigValue(string $key, string $envKey): mixed
    {
        // Try Laravel config first
        if (function_exists('config')) {
            $value = config('laravel-doc-generator.' . $key);
            if ($value !== null) {
                return $value;
            }
        }

        // Fall back to environment variables
        return $_ENV[$envKey] ?? getenv($envKey) ?? '';
    }
    

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }
        
        // Get Confluence credentials
        $baseUrl = $this->getConfigValue('confluence.base_url', 'CONFLUENCE_BASE_URL');
        $email = $this->getConfigValue('confluence.email', 'CONFLUENCE_EMAIL');
        $apiToken = $this->getConfigValue('confluence.api_token', 'CONFLUENCE_API_TOKEN');
        $spaceKey = $this->option('space') ?? $this->getConfigValue('confluence.space_key', 'CONFLUENCE_SPACE_KEY');
        
        if (!$baseUrl || !$email || !$apiToken || !$spaceKey) {
            $this->error('Missing Confluence credentials or space key');
            return Command::FAILURE;
        }
        
        $this->info('Publish to Confluence');
        
        // Parse controller
        $this->line('Parsing controller...');
        $parser = new ControllerParser();
        $controllerData = $parser->parse($filePath);
        $this->info("Found {$controllerData['className']} with " . count($controllerData['methods']) . " methods");
        
        // Get API key for Claude
        $apiKey = $this->getConfigValue('anthropic.api_key', 'ANTHROPIC_API_KEY');
        if (!$apiKey) {
            $this->error('ANTHROPIC_API_KEY environment variable or config not set');
            return Command::FAILURE;
        }
        
        // Generate documentation
        $this->line('Generating documentation...');
        $analyzer = new ClaudeAnalyzer($apiKey);
        $bar = $this->output->createProgressBar(count($controllerData['methods']));
        $bar->start();

        $methodDocs = [];
        foreach ($controllerData['methods'] as $method) {
            $docs = $analyzer->analyzeMethod($controllerData['className'], $method);
            $methodDocs[$method['name']] = $docs['phpdoc'];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        // Format for Confluence
        $this->line('Formatting for Confluence...');
        $formatter = new ConfluenceFormatter();
        $confluenceContent = $formatter->formatControllerDocs($controllerData, $methodDocs);
        
        // Publish to Confluence
        $this->line('Publishing to Confluence...');
        $confluence = new ConfluenceClient($baseUrl, $email, $apiToken);
        
        $pageTitle = $controllerData['className'] . ' Documentation';
        
        try {
            // Check if page already exists
            $existingPage = $confluence->getPageByTitle($spaceKey, $pageTitle);
            
            if ($existingPage) {
                // Update existing page
                $result = $confluence->updatePage(
                    $existingPage['id'],
                    $pageTitle,
                    $confluenceContent,
                    $existingPage['version']['number']
                );
                $this->info("Updated existing page: {$result['_links']['base']}{$result['_links']['webui']}");
            } else {
                // Create new page
                $parentId = $this->option('parent-id');
                $result = $confluence->createPage($spaceKey, $pageTitle, $confluenceContent, $parentId);
                $this->info("Created new page: {$result['_links']['base']}{$result['_links']['webui']}");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to publish: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}