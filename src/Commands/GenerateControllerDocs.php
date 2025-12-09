<?php

namespace Michaelcarrier\LaravelDocGenerator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Michaelcarrier\LaravelDocGenerator\Parsers\ControllerParser;
use Michaelcarrier\LaravelDocGenerator\Analyzers\ClaudeAnalyzer;
use Michaelcarrier\LaravelDocGenerator\Writers\DocumentWriter;
use Symfony\Component\Console\Input\InputOption;

class GenerateControllerDocs extends Command
{
    protected static $defaultName = 'generate:controller';
    
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

        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?? '';
        if (!$apiKey) {
            $io->error('ANTHROPIC_API_KEY environment variable not set');
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