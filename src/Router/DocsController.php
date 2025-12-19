<?php
declare(strict_types=1);

namespace Reut\Router;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Support\ProjectPath;

class DocsController
{
    public function index(Request $request, Response $response): Response
    {
        $format = $request->getQueryParams()['format'] ?? 'html';
        $endpoints = DocsRegistry::all();
        
        // Load model metadata to get disabled routes and auth info
        $modelMetadata = $this->loadModelMetadata();

        if ($format === 'json') {
            $response->getBody()->write(json_encode([
                'endpoints' => $endpoints,
                'models' => $modelMetadata,
                'total' => count($endpoints)
            ], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // HTML format
        $html = $this->generateHtml($endpoints, $modelMetadata);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
    
    private function loadModelMetadata(): array
    {
        $projectRoot = ProjectPath::root();
        $configPath = $projectRoot . '/config.php';
        
        if (!file_exists($configPath)) {
            return [];
        }
        
        require $configPath;
        if (!isset($config) || !is_array($config)) {
            return [];
        }
        
        $modelsDir = $projectRoot . '/models';
        $modelsNamespace = 'Reut\\Models\\';
        $metadata = [];
        
        if (is_dir($modelsDir)) {
            $files = array_filter(glob($modelsDir . '/*Table.php') ?: [], fn($f) => str_ends_with($f, 'Table.php'));
            foreach ($files as $modelFile) {
                $modelName = str_replace('Table.php', '', basename($modelFile));
                $className = $modelsNamespace . basename($modelFile, '.php');
                
                if (!class_exists($className)) {
                    require_once $modelFile;
                }
                
                if (class_exists($className)) {
                    try {
                        $instance = new $className($config);
                        $metadata[$modelName] = [
                            'disabledRoutes' => $instance->disabledRoutes ?? [],
                            'requiresAuth' => $instance->requiresAuth ?? false,
                        ];
                    } catch (\Throwable $e) {
                        // Skip models that can't be instantiated
                    }
                }
            }
        }
        
        return $metadata;
    }

    private function generateHtml(array $endpoints, array $modelMetadata): string
    {
        $grouped = [];
        foreach ($endpoints as $endpoint) {
            $group = $endpoint['group'] ?: 'Other';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $endpoint;
        }

        $html = '<!DOCTYPE html><html><head><title>API Documentation</title>';
        $html .= '<style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
            h2 { color: #555; margin-top: 30px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #007bff; color: white; font-weight: bold; }
            tr:hover { background: #f9f9f9; }
            .method { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
            .get { background: #28a745; color: white; }
            .post { background: #007bff; color: white; }
            .put { background: #ffc107; color: #000; }
            .patch { background: #17a2b8; color: white; }
            .delete { background: #dc3545; color: white; }
            .auth-badge { background: #6c757d; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
            .disabled-badge { background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px; }
            .model-info { background: #e7f3ff; border-left: 4px solid #007bff; padding: 10px; margin: 10px 0; border-radius: 4px; }
            .model-info p { margin: 5px 0; }
            .json-link { float: right; color: #007bff; text-decoration: none; }
            .json-link:hover { text-decoration: underline; }
        </style></head><body>';
        $html .= '<div class="container">';
        $html .= '<h1>API Documentation <a href="?format=json" class="json-link">JSON</a></h1>';
        $html .= '<p>Total endpoints: ' . count($endpoints) . '</p>';

        foreach ($grouped as $group => $groupEndpoints) {
            $html .= '<h2>' . htmlspecialchars($group) . '</h2>';
            
            // Show model metadata (disabled routes and auth status)
            if (isset($modelMetadata[$group])) {
                $meta = $modelMetadata[$group];
                $html .= '<div class="model-info">';
                if ($meta['requiresAuth']) {
                    $html .= '<p><strong>🔒 Authentication Required:</strong> All routes for this model require a valid JWT token.</p>';
                }
                if (!empty($meta['disabledRoutes'])) {
                    $disabledList = in_array('all', $meta['disabledRoutes']) ? 'all routes' : implode(', ', $meta['disabledRoutes']);
                    $html .= '<p><strong>⚠️ Disabled Routes:</strong> ' . htmlspecialchars($disabledList) . '</p>';
                }
                $html .= '</div>';
            }
            
            $html .= '<table><thead><tr><th>Method</th><th>Path</th><th>Description</th><th>Auth</th></tr></thead><tbody>';
            
            foreach ($groupEndpoints as $endpoint) {
                $methodClass = strtolower($endpoint['method']);
                $html .= '<tr>';
                $html .= '<td><span class="method ' . $methodClass . '">' . htmlspecialchars($endpoint['method']) . '</span></td>';
                $html .= '<td><code>' . htmlspecialchars($endpoint['path']) . '</code></td>';
                $html .= '<td>' . htmlspecialchars($endpoint['description']) . '</td>';
                $html .= '<td>' . ($endpoint['requiresAuth'] ? '<span class="auth-badge">Required</span>' : '-') . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
        }

        $html .= '</div></body></html>';
        return $html;
    }
}

