<?php

namespace Michaelcarrier\LaravelDocGenerator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Michaelcarrier\LaravelDocGenerator\Clients\ConfluenceClient;

class TestConfluence extends Command
{
    protected static $defaultName = 'test:confluence';
    
    protected function configure()
    {
        $this->setDescription('Test Confluence API connection');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $baseUrl = $_ENV['CONFLUENCE_BASE_URL'] ?? getenv('CONFLUENCE_BASE_URL') ?? '';
        $email = $_ENV['CONFLUENCE_EMAIL'] ?? getenv('CONFLUENCE_EMAIL') ?? '';
        $apiToken = $_ENV['CONFLUENCE_API_TOKEN'] ?? getenv('CONFLUENCE_API_TOKEN') ?? '';
        
        if (!$baseUrl || !$email || !$apiToken) {
            $io->error('Missing Confluence credentials in environment variables');
            return Command::FAILURE;
        }
        
        try {
            $client = new ConfluenceClient($baseUrl, $email, $apiToken);
            
            // Test by getting a page
            $spaceKey = $_ENV['CONFLUENCE_SPACE_KEY'] ?? getenv('CONFLUENCE_SPACE_KEY') ?? '';
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