<?php

namespace Michaelcarrier\LaravelDocGenerator\Formatters;

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
            $description = $this->extractDescription($phpDoc);
            if ($description) {
                $content .= "<p>{$description}</p>";
            }
        }
        
        // Parameters
        if (!empty($method['params'])) {
            $content .= "<h4>Parameters</h4>";
            $content .= "<table><tbody>";
            $content .= "<tr><th>Name</th><th>Type</th></tr>";
            
            foreach ($method['params'] as $param) {
                $type = $param['type'] ?? 'mixed';
                $content .= "<tr>";
                $content .= "<td><code>\${$param['name']}</code></td>";
                $content .= "<td><code>{$type}</code></td>";
                $content .= "</tr>";
            }
            
            $content .= "</tbody></table>";
        }
        
        // Return type
        if ($method['returnType']) {
            $content .= "<p><strong>Returns:</strong> <code>{$method['returnType']}</code></p>";
        }
        
        // Code example
        $content .= "<h4>Implementation</h4>";
        $content .= "<ac:structured-macro ac:name=\"code\">";
        $content .= "<ac:parameter ac:name=\"language\">php</ac:parameter>";
        $content .= "<ac:plain-text-body><![CDATA[";
        $content .= htmlspecialchars($method['code']);
        $content .= "]]></ac:plain-text-body>";
        $content .= "</ac:structured-macro>";
        
        return $content;
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