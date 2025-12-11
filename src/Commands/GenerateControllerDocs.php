<?php

namespace LaravelDocs\Generator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use LaravelDocs\Generator\Parsers\ControllerParser;
use LaravelDocs\Generator\Analyzers\ClaudeAnalyzer;
use LaravelDocs\Generator\Writers\DocumentWriter;
use Symfony\Component\Console\Input\InputOption;

class GenerateControllerDocs extends Command
{
    protected $signature = 'docs:generate {file : Path to controller file} {output? : Output file path} {--dry-run : Preview changes without writing}';
    protected $description = 'Generate documentation for a Laravel controller';

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
        ->setDescription('Generate documentation for a Laravel controller')
        ->addArgument('file', InputArgument::REQUIRED, 'Path to controller file')
        ->addArgument('output', InputArgument::OPTIONAL, 'Output file path (defaults to input file)')
        ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without writing to file');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');
        
        if (!file_exists($filePath)) {
            $io->error("File not found: {$filePath}");
            return Command::FAILURE;
        }
        
        $io->title('Laravel Documentation Generator');
        $io->section('Parsing controller...');
        
        $parser = new ControllerParser();
        $controllerData = $parser->parse($filePath);
        
        $io->success("Found {$controllerData['className']} with " . count($controllerData['methods']) . " methods");

        $apiKey = $this->getConfigValue('anthropic.api_key', 'ANTHROPIC_API_KEY');
        if (!$apiKey) {
            $io->error('ANTHROPIC_API_KEY environment variable or config not set');
            return Command::FAILURE;
        }
        
        $analyzer = new ClaudeAnalyzer($apiKey);
        
        $io->section('Generating documentation...');
        $io->progressStart(count($controllerData['methods']));
        

        $methodDocs = [];
        foreach ($controllerData['methods'] as $method) {
            $docs = $analyzer->analyzeMethod($controllerData['className'], $method);
            $methodDocs[$method['name']] = $docs['phpdoc'];
            $io->progressAdvance();
        }

        $io->progressFinish();

        // Write documentation to file
        $writer = new DocumentWriter();
        $outputPath = $input->getArgument('output');

        if ($input->getOption('dry-run')) {
            $io->section('Dry run - preview of changes:');
            foreach ($methodDocs as $methodName => $docBlock) {
                $io->writeln("<info>Method: {$methodName}</info>");
                $io->writeln($docBlock);
                $io->newLine();
            }
            $io->note('No files were modified (dry-run mode)');
        } else {
            $writer->writeDocumentation($filePath, $methodDocs, $outputPath);
            $io->success('Documentation written to ' . ($outputPath ?? $filePath));
        }
        
        return Command::SUCCESS;
    }
}