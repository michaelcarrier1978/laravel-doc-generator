<?php

namespace Michaelcarrier\LaravelDocGenerator\Parsers;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Michaelcarrier\LaravelDocGenerator\Parsers\QueryParser;

class ControllerParser
{
    private $parser;
    private $nodeFinder;
    private $queryParser;

    
    public function __construct()
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->nodeFinder = new NodeFinder();
        $this->queryParser = new QueryParser();
    }
    
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException("File is not readable: {$filePath}");
        }

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

        $class = $this->nodeFinder->findFirstInstanceOf($ast, Node\Stmt\Class_::class);

        if (!$class) {
            throw new \RuntimeException("No class found in file: {$filePath}");
        }
        
        $className = $class->name->toString();
        $methods = [];
        
        foreach ($class->getMethods() as $method) {
            if ($method->isPublic()) {
                $methods[] = [
                    'name' => $method->name->toString(),
                    'params' => $this->extractParams($method),
                    'returnType' => $this->extractReturnType($method),
                    'code' => $this->extractMethodCode($method, $code),
                    'queries' => $this->queryParser->extractQueries($method), // Add this line
                ];
            }
        }
            
        return [
            'className' => $className,
            'namespace' => $this->extractNamespace($ast),
            'methods' => $methods,
        ];
    }
    
    private function extractParams(Node\Stmt\ClassMethod $method): array
    {
        $params = [];
        foreach ($method->params as $param) {
            $params[] = [
                'name' => $param->var->name,
                'type' => $param->type ? $param->type->toString() : null,
            ];
        }
        return $params;
    }
    
    private function extractReturnType(Node\Stmt\ClassMethod $method): ?string
    {
        return $method->returnType ? $method->returnType->toString() : null;
    }
    
    private function extractMethodCode(Node\Stmt\ClassMethod $method, string $fullCode): string
    {
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        
        $lines = explode("\n", $fullCode);
        return implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
    
    private function extractNamespace($ast): ?string
    {
        $namespace = $this->nodeFinder->findFirstInstanceOf($ast, Node\Stmt\Namespace_::class);
        return $namespace ? $namespace->name->toString() : null;
    }
}