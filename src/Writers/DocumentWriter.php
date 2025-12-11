<?php

namespace LaravelDocs\Generator\Writers;

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\Comment\Doc;

class DocumentWriter
{
    private $parser;
    private $printer;
    
    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->printer = new PrettyPrinter\Standard();
    }
    
    public function writeDocumentation(string $filePath, array $methodDocs, ?string $outputPath = null): void
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException("File is not readable: {$filePath}");
        }

        // Read original file
        $code = file_get_contents($filePath);

        if ($code === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to parse PHP file: " . $e->getMessage(), 0, $e);
        }

        if (!$ast) {
            throw new \RuntimeException("Failed to generate AST from file");
        }

        // Traverse and modify AST
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new DocBlockVisitor($methodDocs));
        $modifiedAst = $traverser->traverse($ast);

        // Generate new code
        $newCode = $this->printer->prettyPrintFile($modifiedAst);

        // Write to file
        $output = $outputPath ?? $filePath;

        // Check if output directory exists
        $outputDir = dirname($output);
        if (!is_dir($outputDir)) {
            throw new \RuntimeException("Output directory does not exist: {$outputDir}");
        }

        if (!is_writable($outputDir)) {
            throw new \RuntimeException("Output directory is not writable: {$outputDir}");
        }

        $result = file_put_contents($output, $newCode);

        if ($result === false) {
            throw new \RuntimeException("Failed to write file: {$output}");
        }
    }
}

class DocBlockVisitor extends NodeVisitorAbstract
{
    private $methodDocs;
    
    public function __construct(array $methodDocs)
    {
        $this->methodDocs = $methodDocs;
    }
    
    public function leaveNode(Node $node)
    {
        // Check if it's a class method
        if ($node instanceof Node\Stmt\ClassMethod) {
            $methodName = $node->name->toString();
            
            // If we have docs for this method, add them
            if (isset($this->methodDocs[$methodName])) {
                $docComment = new Doc($this->methodDocs[$methodName]);
                $node->setDocComment($docComment);
            }
        }
        
        return $node;
    }
}