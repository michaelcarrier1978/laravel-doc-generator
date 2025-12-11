<?php

namespace LaravelDocs\Generator\Parsers;

use PhpParser\Node;
use PhpParser\NodeFinder;

class QueryParser
{
    private $nodeFinder;
    
    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }
    
    public function extractQueries(Node\Stmt\ClassMethod $methodNode): array
    {
        $queries = [];
        
        // Find all method calls in the method body
        $methodCalls = $this->nodeFinder->findInstanceOf($methodNode, Node\Expr\MethodCall::class);
        $staticCalls = $this->nodeFinder->findInstanceOf($methodNode, Node\Expr\StaticCall::class);
        
        // Process static calls (like User::create, User::where)
        foreach ($staticCalls as $call) {
            $query = $this->parseStaticCall($call);
            if ($query) {
                $queries[] = $query;
            }
        }
        
        return $queries;
    }
    
    private function parseStaticCall(Node\Expr\StaticCall $call): ?array
    {
        // Get the model name
        if (!$call->class instanceof Node\Name) {
            return null;
        }
        
        $model = $call->class->toString();
        $method = $call->name->toString();
        
        // Build query chain
        $chain = $this->buildQueryChain($call);
        
        return [
            'model' => $model,
            'method' => $method,
            'chain' => $chain,
        ];
    }
    
    private function buildQueryChain($node, $chain = []): array
    {
        // Get current method name
        if ($node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\MethodCall) {
            $methodName = $node->name->toString();
            $args = $this->extractArguments($node->args);
            
            array_unshift($chain, [
                'method' => $methodName,
                'args' => $args,
            ]);
            
            // If this is a chained call, continue building
            if ($node instanceof Node\Expr\MethodCall && $node->var) {
                return $this->buildQueryChain($node->var, $chain);
            }
        }
        
        return $chain;
    }
    
    private function extractArguments(array $args): array
    {
        $extracted = [];
        
        foreach ($args as $arg) {
            $value = $this->getArgumentValue($arg->value);
            if ($value !== null) {
                $extracted[] = $value;
            }
        }
        
        return $extracted;
    }
    
    private function getArgumentValue(Node\Expr $expr)
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        } elseif ($expr instanceof Node\Expr\ConstFetch) {
            return $expr->name->toString();
        } elseif ($expr instanceof Node\Scalar\LNumber || $expr instanceof Node\Scalar\DNumber) {
            return $expr->value;
        } elseif ($expr instanceof Node\Expr\Array_) {
            return '[array]';
        }
        
        return null;
    }
}