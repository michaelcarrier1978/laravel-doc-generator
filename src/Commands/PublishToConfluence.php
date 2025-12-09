<?php

namespace Michaelcarrier\LaravelDocGenerator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Michaelcarrier\LaravelDocGenerator\Parsers\ControllerParser;
use Michaelcarrier\LaravelDocGenerator\Analyzers\ClaudeAnalyzer;
use Michaelcarrier\LaravelDocGenerator\Clients\ConfluenceClient;
use Michaelcarrier\LaravelDocGenerator\Formatters\ConfluenceFormatter;

class PublishToConfluence extends Command
{
    protected static $defaultName = 'publish:confluence';
    
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
        $baseUrl = $_ENV['CONFLUENCE_BASE_URL'] ?? getenv('CONFLUENCE_BASE_URL') ?? '';
        $email = $_ENV['CONFLUENCE_EMAIL'] ?? getenv('CONFLUENCE_EMAIL') ?? '';
        $apiToken = $_ENV['CONFLUENCE_API_TOKEN'] ?? getenv('CONFLUENCE_API_TOKEN') ?? '';
        $spaceKey = $input->getOption('space') ?? $_ENV['CONFLUENCE_SPACE_KEY'] ?? getenv('CONFLUENCE_SPACE_KEY') ?? '';
        
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
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?? '';
        if (!$apiKey) {
            $io->error('ANTHROPIC_API_KEY environment variable not set');
            return Command::FAILURE;
        }

        // Debug: show first and last few characters
        $io->note('API Key loaded: ' . substr($apiKey, 0, 20) . '...' . substr($apiKey, -15));
        
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