<?php
declare(strict_types=1);

namespace Reut\Router;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Support\ProjectPath;

/**
 * SchemaController
 * Renders the database schema viewer as an endpoint (like /docs).
 * Enable/disable via REUT_SCHEMA_ENABLED env variable.
 */
class SchemaController
{
    public function index(Request $request, Response $response): Response
    {
        $projectRoot = ProjectPath::root();
        $configPath = $projectRoot . '/config.php';
        
        if (!file_exists($configPath)) {
            $response->getBody()->write(json_encode(['error' => 'config.php not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        
        // config.php sets $config variable, doesn't return it
        require $configPath;
        if (!isset($config) || !is_array($config)) {
            $response->getBody()->write(json_encode(['error' => 'config.php must define $config array']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        
        $modelsDir = $projectRoot . '/models';
        $modelsNamespace = 'Reut\\Models\\';
        
        $tables = [];
        $errors = [];
        
        if (is_dir($modelsDir)) {
            $files = array_filter(glob($modelsDir . '/*.php') ?: [], fn($f) => str_ends_with($f, '.php'));
            foreach ($files as $modelFile) {
                $metadata = $this->loadModelMetadata($modelFile, $modelsNamespace, $config, $errors);
                if ($metadata !== null) {
                    $tables[] = $metadata;
                }
            }
            usort($tables, fn($a, $b) => $b['filemtime'] <=> $a['filemtime']);
        } else {
            $errors[] = "Models directory not found at {$modelsDir}";
        }
        
        $format = $request->getQueryParams()['format'] ?? 'html';
        
        if ($format === 'json') {
            $response->getBody()->write(json_encode([
                'tables' => $tables,
                'errors' => $errors,
                'total' => count($tables),
                'generated' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        $html = $this->generateHtml($tables, $errors);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
    
    private function loadModelMetadata(string $filePath, string $modelsNamespace, array $config, array &$errors): ?array
    {
        $className = $modelsNamespace . pathinfo($filePath, PATHINFO_FILENAME);
        $mtime = filemtime($filePath);
        
        if (!class_exists($className)) {
            require_once $filePath;
        }
        if (!class_exists($className)) {
            $errors[] = "Unable to load model class {$className}.";
            return null;
        }
        
        try {
            $instance = new $className($config);
        } catch (\Throwable $e) {
            $errors[] = "Failed to instantiate {$className}: " . $e->getMessage();
            return null;
        }
        
        $columns = [];
        $foreignKeys = method_exists($instance, 'getForeignKeys') ? $instance->getForeignKeys() : [];
        
        foreach ($instance->columns ?? [] as $name => $definition) {
            $definitionSql = method_exists($definition, 'getSql') ? $definition->getSql() : 'N/A';
            $isPrimary = method_exists($definition, 'isPrimaryKey') ? $definition->isPrimaryKey() : false;
            
            $foreignKey = null;
            foreach ($foreignKeys as $fk) {
                if ($fk['column'] === $name) {
                    $foreignKey = $fk;
                    break;
                }
            }
            
            $columns[] = [
                'name' => $name,
                'definition' => $definitionSql,
                'isPrimary' => $isPrimary,
                'foreignKey' => $foreignKey
            ];
        }
        
        $hasRelationships = method_exists($instance, 'hasRelationships') ? $instance->hasRelationships() : (bool)($instance->relationships ?? false);
        $relationshipCount = method_exists($instance, 'getRelationshipCount') ? $instance->getRelationshipCount() : (int)($instance->relationships ?? 0);
        
        $traits = class_uses($className) ?: [];
        $hasDeletedAt = in_array('deleted_at', array_column($columns, 'name'));
        $hasTimestamps = in_array('created_at', array_column($columns, 'name')) && in_array('updated_at', array_column($columns, 'name'));
        
        // Extract disabled routes and auth status
        $disabledRoutes = $instance->disabledRoutes ?? [];
        $requiresAuth = $instance->requiresAuth ?? false;
        
        return [
            'class' => $className,
            'table' => $instance->tableName ?? pathinfo($filePath, PATHINFO_FILENAME),
            'columns' => $columns,
            'hasRelationships' => $hasRelationships,
            'relationshipCount' => $relationshipCount,
            'traits' => $traits,
            'hasDeletedAt' => $hasDeletedAt,
            'hasTimestamps' => $hasTimestamps,
            'disabledRoutes' => $disabledRoutes,
            'requiresAuth' => $requiresAuth,
            'filemtime' => $mtime,
            'modifiedAgo' => time() - $mtime
        ];
    }
    
    private function generateHtml(array $tables, array $errors): string
    {
        $generated = date('Y-m-d H:i:s');
        $tableCount = count($tables);
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>REUT Schema Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --bg: #f8fafc; --surface: #ffffff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --primary: #3b82f6; --success: #10b981; --warning: #f59e0b; --purple: #8b5cf6; }
        .dark { --bg: #0f172a; --surface: #1e293b; --text: #e2e8f0; --muted: #94a3b8; --border: #334155; --light-pill: #312e81e7; }
        * { box-sizing: border-box; margin:0; padding:0; }
        body { font-family: "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; padding: 2rem; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        h1 { font-size: 2rem; }
        .header-right { display: flex; gap: 1rem; align-items: center; }
        .btn { background: var(--primary); color: white; border: none; padding: .6rem 1.2rem; border-radius: 12px; cursor: pointer; font-weight: 500; text-decoration: none; }
        .btn:hover { background: #2563eb; }
        .search-bar { max-width: 720px; margin: 0 auto 2rem; }
        #table-search { width: 100%; padding: 1rem 1.4rem; font-size: 1.1rem; border: 1px solid var(--border); border-radius: 16px; background: var(--surface); color: var(--text); }
        .notice { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 12px; padding: 1rem; margin-bottom: 2rem; }
        .notice h2 { color: #92400e; font-size: 1rem; margin-bottom: 0.5rem; }
        .notice ul { margin-left: 1.5rem; color: #92400e; }
        .empty-state { text-align: center; padding: 3rem; color: var(--muted); }
        .card { background: var(--surface); border-radius: 18px; padding: 1.8rem; margin-bottom: 2rem; box-shadow: 0 15px 35px rgba(15,23,42,.08); transition: .3s; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 25px 50px rgba(15,23,42,.15); }
        .card__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .card h2 { font-size: 1.5rem; }
        .muted { color: var(--muted); font-size: 0.9rem; }
        .badge-group { display: flex; flex-wrap: wrap; gap: .6rem; }
        .badge { padding: .45rem .95rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .badge--success { background: #d1fae5; color: #065f46; }
        .badge--warning { background: #ffebc2; color: #d97706; }
        .badge--info { background: #dbeafe; color: #4338ca; }
        .badge--purple { background: #e9d5ff; color: #6b21a8; }
        .badge--recent { background: #f87171; color: white; }
        .columns-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1.3rem; margin: 1rem 0; }
        .column-card { border: 1px solid var(--border); border-radius: 14px; padding: 1.2rem; background: var(--surface); transition: .25s; }
        .column-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,.12); border-color: var(--primary); }
        .column-card__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .column-name { font-weight: 600; }
        .chip { padding: .3rem .8rem; border-radius: 999px; font-size: .75rem; font-weight: 600; color: white; }
        .chip--primary { background: var(--primary); }
        .chip--fk { background: var(--success); }
        .pills { display: flex; flex-wrap: wrap; gap: .55rem; margin: .9rem 0 .6rem; }
        .pill { background: #eef2ff; color: #4f46e5; padding: .35rem .75rem; border-radius: 999px; font-size: .84rem; font-family: ui-monospace, monospace; font-weight: 500; }
        .dark .pill { background: var(--light-pill); color: rgb(206, 205, 205); }
        .fk-info { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; margin-top: .8rem; padding: .7rem; border-radius: 12px; background: rgba(16,185,129,.1); }
        .fk-arrow { color: var(--success); font-weight: bold; font-size: 1.2em; }
        .status { text-align: center; margin: 2rem 0; color: var(--muted); }
        @media (max-width: 640px) { .columns-grid { grid-template-columns: 1fr; } .header { flex-direction: column; text-align: center; } }
    </style>
</head>
<body>
<div class="container">
    <header class="header">
        <div>
            <h1>🗃️ REUT Schema Viewer</h1>
            <p class="muted">Realtime snapshot of your model definitions ({$tableCount} tables)</p>
        </div>
        <div class="header-right">
            <a href="?format=json" class="btn">JSON</a>
            <button id="theme-toggle" class="btn">Dark Mode</button>
        </div>
    </header>
    
    <div class="search-bar">
        <input type="text" id="table-search" placeholder="Search tables, columns..." autocomplete="off">
    </div>
HTML;

        if (!empty($errors)) {
            $html .= '<section class="notice"><h2>⚠️ Warnings</h2><ul>';
            foreach ($errors as $error) {
                $html .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            $html .= '</ul></section>';
        }
        
        if (empty($tables)) {
            $html .= '<section class="empty-state"><p>No models found. Create a model to see it here.</p></section>';
        } else {
            foreach ($tables as $table) {
                $tableName = htmlspecialchars($table['table']);
                $className = htmlspecialchars($table['class']);
                $tableId = strtolower($table['table']);
                
                $html .= "<section class=\"card\" id=\"table-{$tableId}\">";
                $html .= '<div class="card__header"><div><h2>' . $tableName . '</h2><p class="muted">' . $className . '</p></div>';
                $html .= '<div class="badge-group">';
                
                if ($table['relationshipCount'] > 0) {
                    $html .= '<span class="badge badge--success">Relations: ' . $table['relationshipCount'] . '</span>';
                }
                if ($table['hasDeletedAt']) {
                    $html .= '<span class="badge badge--warning">Soft Deletes</span>';
                }
                if ($table['hasTimestamps']) {
                    $html .= '<span class="badge badge--info">Timestamps</span>';
                }
                if (isset($table['requiresAuth']) && $table['requiresAuth']) {
                    $html .= '<span class="badge badge--purple">Auth Required</span>';
                }
                if (isset($table['disabledRoutes']) && !empty($table['disabledRoutes'])) {
                    $disabledList = in_array('all', $table['disabledRoutes']) ? 'all' : implode(', ', $table['disabledRoutes']);
                    $html .= '<span class="badge badge--warning">Disabled: ' . htmlspecialchars($disabledList) . '</span>';
                }
                if ($table['modifiedAgo'] < 3600) {
                    $ago = $table['modifiedAgo'] < 300 ? 'Just now' : round($table['modifiedAgo'] / 60) . 'm ago';
                    $html .= '<span class="badge badge--recent">' . $ago . '</span>';
                }
                
                $html .= '</div></div>';
                $html .= '<h3>Columns</h3><div class="columns-grid">';
                
                foreach ($table['columns'] as $column) {
                    $colName = htmlspecialchars($column['name']);
                    $html .= '<article class="column-card"><div class="column-card__header">';
                    $html .= '<strong class="column-name">' . $colName . '</strong><div>';
                    if ($column['isPrimary']) {
                        $html .= '<span class="chip chip--primary">PK</span> ';
                    }
                    if ($column['foreignKey']) {
                        $html .= '<span class="chip chip--fk">FK</span>';
                    }
                    $html .= '</div></div>';
                    
                    $html .= '<div class="pills">';
                    $def = trim($column['definition']);
                    if ($def && $def !== 'N/A') {
                        foreach (preg_split('/\s+/', $def) as $part) {
                            $html .= '<span class="pill">' . htmlspecialchars($part) . '</span>';
                        }
                    } else {
                        $html .= '<span class="pill">N/A</span>';
                    }
                    $html .= '</div>';
                    
                    if ($column['foreignKey']) {
                        $fk = $column['foreignKey'];
                        $html .= '<div class="fk-info">';
                        $html .= '<span class="fk-arrow">→</span> ';
                        $html .= '<strong>' . htmlspecialchars($fk['referenced_table']) . '.</strong>';
                        $html .= '<code>' . htmlspecialchars($fk['referenced_column']) . '</code>';
                        $html .= ' <span class="muted">ON DELETE ' . strtoupper($fk['on_delete']) . '</span>';
                        $html .= '</div>';
                    }
                    
                    $html .= '</article>';
                }
                
                $html .= '</div></section>';
            }
        }
        
        $html .= <<<HTML
    <div class="status">
        <p>Generated: {$generated}</p>
    </div>
</div>

<script>
const themeKey = 'reut-schema-theme';
const savedTheme = localStorage.getItem(themeKey);
if (savedTheme === 'dark') document.documentElement.classList.add('dark');
else if (window.matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');

document.getElementById('theme-toggle').addEventListener('click', () => {
    document.documentElement.classList.toggle('dark');
    const isDark = document.documentElement.classList.contains('dark');
    localStorage.setItem(themeKey, isDark ? 'dark' : 'light');
    document.getElementById('theme-toggle').textContent = isDark ? 'Light Mode' : 'Dark Mode';
});

document.getElementById('table-search').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('.card').forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(term) ? 'block' : 'none';
    });
});
</script>
</body>
</html>
HTML;

        return $html;
    }
}

