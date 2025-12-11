<?php

namespace LaravelDocs\Generator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use LaravelDocs\Generator\Parsers\ControllerParser;
use LaravelDocs\Generator\Analyzers\ClaudeAnalyzer;
use LaravelDocs\Generator\Clients\ConfluenceClient;
use LaravelDocs\Generator\Formatters\ConfluenceFormatter;

class PublishToConfluence extends Command
{
    protected static $defaultName = 'publish:confluence';
    protected $signature = 'docs:publish {file : Path to controller file} {--space= : Confluence space key} {--parent-id= : Parent page ID}';

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
    
    protected function configure()
    {
        $this
            ->setDescription('Generate and publish controller documentation to Confluence')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to controller file')
            ->addOption('space', 's', InputOption::VALUE_REQUIRED, 'Confluence space key')
            ->addOption('parent-id', 'p', InputOption::VALUE_REQUIRED, 'Parent page ID (optional)');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');
        
        if (!file_exists($filePath)) {
            $io->error("File not found: {$filePath}");
            return Command::FAILURE;
        }
        
        // Get Confluence credentials
        $baseUrl = $this->getConfigValue('confluence.base_url', 'CONFLUENCE_BASE_URL');
        $email = $this->getConfigValue('confluence.email', 'CONFLUENCE_EMAIL');
        $apiToken = $this->getConfigValue('confluence.api_token', 'CONFLUENCE_API_TOKEN');
        $spaceKey = $input->getOption('space') ?? $this->getConfigValue('confluence.space_key', 'CONFLUENCE_SPACE_KEY');
        
        if (!$baseUrl || !$email || !$apiToken || !$spaceKey) {
            $io->error('Missing Confluence credentials or space key');
            return Command::FAILURE;
        }
        
        $io->title('Publish to Confluence');
        
        // Parse controller
        $io->section('Parsing controller...');
        $parser = new ControllerParser();
        $controllerData = $parser->parse($filePath);
        $io->success("Found {$controllerData['className']} with " . count($controllerData['methods']) . " methods");
        
        // Get API key for Claude
        $apiKey = $this->getConfigValue('anthropic.api_key', 'ANTHROPIC_API_KEY');
        if (!$apiKey) {
            $io->error('ANTHROPIC_API_KEY environment variable or config not set');
            return Command::FAILURE;
        }
        
        // Generate documentation
        $io->section('Generating documentation...');
        $analyzer = new ClaudeAnalyzer($apiKey);
        $io->progressStart(count($controllerData['methods']));
        
        $methodDocs = [];
        foreach ($controllerData['methods'] as $method) {
            $docs = $analyzer->analyzeMethod($controllerData['className'], $method);
            $methodDocs[$method['name']] = $docs['phpdoc'];
            $io->progressAdvance();
        }
        
        $io->progressFinish();
        
        // Format for Confluence
        $io->section('Formatting for Confluence...');
        $formatter = new ConfluenceFormatter();
        $confluenceContent = $formatter->formatControllerDocs($controllerData, $methodDocs);
        
        // Publish to Confluence
        $io->section('Publishing to Confluence...');
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
                $io->success("Updated existing page: {$result['_links']['base']}{$result['_links']['webui']}");
            } else {
                // Create new page
                $parentId = $input->getOption('parent-id');
                $result = $confluence->createPage($spaceKey, $pageTitle, $confluenceContent, $parentId);
                $io->success("Created new page: {$result['_links']['base']}{$result['_links']['webui']}");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to publish: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}