<?php

namespace LaravelDocs\Generator\Formatters;

class ConfluenceFormatter
{
    /**
     * Convert controller data to Confluence storage format
     */
    public function formatControllerDocs(array $controllerData, array $methodDocs): string
    {
        $content = "<h1>{$controllerData['className']}</h1>";
        $content .= "<p><strong>Namespace:</strong> {$controllerData['namespace']}</p>";
        
        $content .= "<h2>Methods</h2>";
        
        foreach ($controllerData['methods'] as $method) {
            $methodName = $method['name'];
            $content .= $this->formatMethod($method, $methodDocs[$methodName] ?? null);
        }
        
        return $content;
    }
    
    /**
     * Format a single method
     */
    private function formatMethod(array $method, ?string $phpDoc): string
    {
        $content = "<h3>{$method['name']}()</h3>";

        // Add PHPDoc description if available
        if ($phpDoc) {
            $parsedDoc = $this->parsePhpDoc($phpDoc);

            if ($parsedDoc['summary']) {
                $content .= "<ac:structured-macro ac:name=\"info\">";
                $content .= "<ac:rich-text-body>";
                $content .= "<p><strong>Summary:</strong> {$parsedDoc['summary']}</p>";
                $content .= "</ac:rich-text-body>";
                $content .= "</ac:structured-macro>";
            }

            if ($parsedDoc['description']) {
                $content .= "<p>{$parsedDoc['description']}</p>";
            }
        }

        // Method signature
        $signature = $this->buildMethodSignature($method);
        $content .= "<ac:structured-macro ac:name=\"code\">";
        $content .= "<ac:parameter ac:name=\"language\">php</ac:parameter>";
        $content .= "<ac:parameter ac:name=\"linenumbers\">false</ac:parameter>";
        $content .= "<ac:plain-text-body><![CDATA[{$signature}]]></ac:plain-text-body>";
        $content .= "</ac:structured-macro>";

        // Parameters table
        if (!empty($method['params'])) {
            $content .= "<h4>Parameters</h4>";
            $content .= "<table>";
            $content .= "<thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>";
            $content .= "<tbody>";

            foreach ($method['params'] as $param) {
                $type = $param['type'] ?? 'mixed';
                $paramDesc = $phpDoc ? $this->extractParamDescription($phpDoc, $param['name']) : '';
                $content .= "<tr>";
                $content .= "<td><code>\${$param['name']}</code></td>";
                $content .= "<td><code>{$type}</code></td>";
                $content .= "<td>{$paramDesc}</td>";
                $content .= "</tr>";
            }

            $content .= "</tbody></table>";
        }

        // Return type
        if ($method['returnType']) {
            $returnDesc = $phpDoc ? $this->extractReturnDescription($phpDoc) : '';
            $content .= "<p><strong>Returns:</strong> <code>{$method['returnType']}</code>";
            if ($returnDesc) {
                $content .= " - {$returnDesc}";
            }
            $content .= "</p>";
        }

        // Code implementation (collapsible)
        $content .= "<ac:structured-macro ac:name=\"expand\">";
        $content .= "<ac:parameter ac:name=\"title\">View Full Implementation</ac:parameter>";
        $content .= "<ac:rich-text-body>";
        $content .= "<ac:structured-macro ac:name=\"code\">";
        $content .= "<ac:parameter ac:name=\"language\">php</ac:parameter>";
        $content .= "<ac:parameter ac:name=\"linenumbers\">true</ac:parameter>";
        $content .= "<ac:plain-text-body><![CDATA[";
        $content .= htmlspecialchars(trim($method['code']));
        $content .= "]]></ac:plain-text-body>";
        $content .= "</ac:structured-macro>";
        $content .= "</ac:rich-text-body>";
        $content .= "</ac:structured-macro>";

        $content .= "<hr />";

        return $content;
    }

    /**
     * Build method signature
     */
    private function buildMethodSignature(array $method): string
    {
        $params = [];
        foreach ($method['params'] as $param) {
            $type = $param['type'] ? $param['type'] . ' ' : '';
            $params[] = $type . '$' . $param['name'];
        }

        $returnType = $method['returnType'] ? ': ' . $method['returnType'] : '';

        return "public function {$method['name']}(" . implode(', ', $params) . "){$returnType}";
    }

    /**
     * Parse PHPDoc into structured data
     */
    private function parsePhpDoc(string $phpDoc): array
    {
        $phpDoc = preg_replace('/^\/\*\*|\*\/$/', '', $phpDoc);
        $lines = explode("\n", $phpDoc);

        $summary = '';
        $description = [];
        $inDescription = false;

        foreach ($lines as $line) {
            $line = preg_replace('/^\s*\*\s?/', '', $line);
            $line = trim($line);

            if (empty($line)) {
                $inDescription = true;
                continue;
            }

            if (strpos($line, '@') === 0) {
                break;
            }

            if (!$inDescription && empty($summary)) {
                $summary = $line;
            } elseif ($inDescription) {
                $description[] = $line;
            }
        }

        return [
            'summary' => $summary,
            'description' => implode(' ', $description),
        ];
    }

    /**
     * Extract parameter description from PHPDoc
     */
    private function extractParamDescription(string $phpDoc, string $paramName): string
    {
        $pattern = '/@param\s+[\w\|\\\\]+\s+\$' . preg_quote($paramName) . '\s+(.+)/';
        if (preg_match($pattern, $phpDoc, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Extract return description from PHPDoc
     */
    private function extractReturnDescription(string $phpDoc): string
    {
        $pattern = '/@return\s+[\w\|\\\\]+\s+(.+)/';
        if (preg_match($pattern, $phpDoc, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
    
    /**
     * Extract description from PHPDoc
     */
    private function extractDescription(string $phpDoc): string
    {
        // Remove /** and */
        $phpDoc = preg_replace('/^\/\*\*|\*\/$/', '', $phpDoc);
        
        // Split into lines
        $lines = explode("\n", $phpDoc);
        $description = [];
        
        foreach ($lines as $line) {
            // Remove leading * and whitespace
            $line = preg_replace('/^\s*\*\s?/', '', $line);
            
            // Stop at @tags
            if (strpos($line, '@') === 0) {
                break;
            }
            
            if (trim($line)) {
                $description[] = trim($line);
            }
        }
        
        return implode(' ', $description);
    }
}