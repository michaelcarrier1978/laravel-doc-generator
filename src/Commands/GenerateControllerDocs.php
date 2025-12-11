<?php

namespace LaravelDocs\Generator\Commands;

use Illuminate\Console\Command;
use LaravelDocs\Generator\Parsers\ControllerParser;
use LaravelDocs\Generator\Analyzers\ClaudeAnalyzer;
use LaravelDocs\Generator\Writers\DocumentWriter;

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

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->info('Laravel Documentation Generator');
        $this->line('Parsing controller...');

        $parser = new ControllerParser();
        $controllerData = $parser->parse($filePath);

        $this->info("Found {$controllerData['className']} with " . count($controllerData['methods']) . " methods");

        $apiKey = $this->getConfigValue('anthropic.api_key', 'ANTHROPIC_API_KEY');
        if (!$apiKey) {
            $this->error('ANTHROPIC_API_KEY environment variable or config not set');
            return Command::FAILURE;
        }

        $analyzer = new ClaudeAnalyzer($apiKey);

        $this->line('Generating documentation...');
        $bar = $this->output->createProgressBar(count($controllerData['methods']));
        $bar->start();

        $methodDocs = [];
        foreach ($controllerData['methods'] as $method) {
            $docs = $analyzer->analyzeMethod($controllerData['className'], $method);
            $methodDocs[$method['name']] = $docs['phpdoc'];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Write documentation to file
        $writer = new DocumentWriter();
        $outputPath = $this->argument('output');

        if ($this->option('dry-run')) {
            $this->line('Dry run - preview of changes:');
            foreach ($methodDocs as $methodName => $docBlock) {
                $this->info("Method: {$methodName}");
                $this->line($docBlock);
                $this->newLine();
            }
            $this->warn('No files were modified (dry-run mode)');
        } else {
            $writer->writeDocumentation($filePath, $methodDocs, $outputPath);
            $this->info('Documentation written to ' . ($outputPath ?? $filePath));
        }

        return Command::SUCCESS;
    }
}