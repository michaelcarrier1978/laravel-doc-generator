<?php

namespace LaravelDocs\Generator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use LaravelDocs\Generator\Clients\ConfluenceClient;

class TestConfluence extends Command
{
    protected $signature = 'docs:test-confluence';
    protected $description = 'Test Confluence API connection';

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
        $this->setDescription('Test Confluence API connection');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $baseUrl = $this->getConfigValue('confluence.base_url', 'CONFLUENCE_BASE_URL');
        $email = $this->getConfigValue('confluence.email', 'CONFLUENCE_EMAIL');
        $apiToken = $this->getConfigValue('confluence.api_token', 'CONFLUENCE_API_TOKEN');
        
        if (!$baseUrl || !$email || !$apiToken) {
            $io->error('Missing Confluence credentials in environment variables');
            return Command::FAILURE;
        }
        
        try {
            $client = new ConfluenceClient($baseUrl, $email, $apiToken);
            
            // Test by getting a page
            $spaceKey = $this->getConfigValue('confluence.space_key', 'CONFLUENCE_SPACE_KEY');
            if ($spaceKey) {
                $page = $client->getPageByTitle($spaceKey, 'Test Page');
                if ($page) {
                    $io->success("Connected! Found page: {$page['title']}");
                } else {
                    $io->success("Connected! (No test page found, but connection works)");
                }
            } else {
                $io->success("Connection established! (Set CONFLUENCE_SPACE_KEY to test further)");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Connection failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}