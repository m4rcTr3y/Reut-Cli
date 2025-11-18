<?php
declare(strict_types=1);

/**
 * view.php
 * Spins up the PHP built-in server to render the schema viewer.
 * Usage: php manage.php view [--host=127.0.0.1] [--port=8080]
 */

$projectRoot = dirname(__DIR__);
$viewerDir = $projectRoot . '/viewer';

// Ensure the viewer scaffolding exists even for older projects.
ensureViewerAssets($viewerDir);

$options = parseViewOptions($argv ?? []);
$host = $options['host'] ?? '127.0.0.1';
$port = (string)($options['port'] ?? '8080');
$address = "{$host}:{$port}";

if (!file_exists($viewerDir . '/index.php')) {
    fwrite(STDERR, "Viewer bootstrap failed (missing index.php). Aborting.\n");
    exit(1);
}

echo "Opening REUT Schema Viewer on http://{$address}\n";
echo "Press CTRL+C to stop the server.\n\n";

$command = sprintf(
    '%s -S %s -t %s %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($address),
    escapeshellarg($viewerDir),
    escapeshellarg($viewerDir . '/index.php')
);

passthru($command);

/**
 * Parse host/port flags from the CLI arguments.
 */
function parseViewOptions(array $argv): array
{
    $options = [];
    foreach ($argv as $arg) {
        if (strpos($arg, '--host=') === 0) {
            $options['host'] = substr($arg, 7);
        }
        if (strpos($arg, '--port=') === 0) {
            $options['port'] = (int)substr($arg, 7);
        }
    }
    return $options;
}

/**
 * Create the viewer folder/files when they are missing (legacy projects).
 */
function ensureViewerAssets(string $viewerDir): void
{
    $assetsDir = $viewerDir . '/assets';

    if (!is_dir($viewerDir)) {
        mkdir($viewerDir, 0755, true);
    }

    if (!is_dir($assetsDir)) {
        mkdir($assetsDir, 0755, true);
    }

    $indexPath = $viewerDir . '/index.php';
    $stylePath = $assetsDir . '/style.css';

    if (!file_exists($indexPath)) {
        file_put_contents($indexPath, getViewerIndexTemplate());
    }

    if (!file_exists($stylePath)) {
        file_put_contents($stylePath, getViewerStyleTemplate());
    }
}

/**
 * Template for viewer/index.php (kept inline to avoid extra dependencies).
 */
function getViewerIndexTemplate(): string
{
    return <<<'PHP'
<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';
require $projectRoot . '/config.php';

$modelsDir = $projectRoot . '/models';
$modelsNamespace = 'Reut\\Models\\';
$tables = [];
$errors = [];

$loadModelMetadata = function (string $filePath) use (&$errors, $modelsNamespace, $config) {
    $className = $modelsNamespace . pathinfo($filePath, PATHINFO_FILENAME);

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
    foreach ($instance->columns ?? [] as $name => $definition) {
        $columns[] = [
            'name' => $name,
            'definition' => method_exists($definition, 'getSql') ? $definition->getSql() : 'N/A',
            'isPrimary' => method_exists($definition, 'isPrimaryKey') ? $definition->isPrimaryKey() : false
        ];
    }

    $hasRelationships = method_exists($instance, 'hasRelationships')
        ? $instance->hasRelationships()
        : (bool)($instance->hasRelationships ?? false);

    $relationshipCount = method_exists($instance, 'getRelationshipCount')
        ? $instance->getRelationshipCount()
        : (int)($instance->relationships ?? 0);

    $foreignKeys = method_exists($instance, 'getForeignKeys')
        ? $instance->getForeignKeys()
        : [];

    return [
        'class' => $className,
        'table' => $instance->tableName ?? pathinfo($filePath, PATHINFO_FILENAME),
        'columns' => $columns,
        'hasRelationships' => $hasRelationships,
        'relationshipCount' => $relationshipCount,
        'foreignKeys' => $foreignKeys
    ];
};

if (is_dir($modelsDir)) {
    $files = glob($modelsDir . '/*.php') ?: [];
    sort($files);

    foreach ($files as $modelFile) {
        $metadata = $loadModelMetadata($modelFile);
        if ($metadata !== null) {
            $tables[] = $metadata;
        }
    }
} else {
    $errors[] = "Models directory not found at {$modelsDir}";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>REUT Schema Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header>
        <h1>REUT Schema Viewer</h1>
        <p>Realtime snapshot of your model definitions.</p>
    </header>

    <?php if (!empty($errors)): ?>
        <section class="notice">
            <h2>Warnings</h2>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if (empty($tables)): ?>
        <section class="empty-state">
            <p>No models were discovered. Create one with <code>php manage.php generate:model Users</code> and rerun the viewer.</p>
        </section>
    <?php else: ?>
        <?php foreach ($tables as $table): ?>
            <section class="card">
                <div class="card__header">
                    <div>
                        <h2><?= htmlspecialchars($table['table'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="muted"><?= htmlspecialchars($table['class'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="badge-group">
                        <span class="badge <?= $table['hasRelationships'] ? 'badge--success' : 'badge--muted'; ?>">
                            <?= $table['hasRelationships'] ? 'Relationships' : 'Standalone'; ?>
                        </span>
                        <span class="badge">Relations: <?= (int)$table['relationshipCount']; ?></span>
                    </div>
                </div>

                <div class="card__body">
                    <h3>Columns</h3>
                    <div class="columns-grid">
                        <?php foreach ($table['columns'] as $column): ?>
                            <article class="column-card">
                                <div class="column-card__title">
                                    <strong><?= htmlspecialchars($column['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if ($column['isPrimary']): ?>
                                        <span class="chip">Primary</span>
                                    <?php endif; ?>
                                </div>
                                <p><?= htmlspecialchars($column['definition'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($table['foreignKeys'])): ?>
                        <h3>Foreign Keys</h3>
                        <ul class="fk-list">
                            <?php foreach ($table['foreignKeys'] as $fk): ?>
                                <li>
                                    <code><?= htmlspecialchars($fk['column'], ENT_QUOTES, 'UTF-8'); ?></code>
                                    → <?= htmlspecialchars($fk['referenced_table'], ENT_QUOTES, 'UTF-8'); ?>.
                                    Referenced column <code><?= htmlspecialchars($fk['referenced_column'], ENT_QUOTES, 'UTF-8'); ?></code>,
                                    DELETE: <?= htmlspecialchars($fk['on_delete'], ENT_QUOTES, 'UTF-8'); ?>,
                                    UPDATE: <?= htmlspecialchars($fk['on_update'], ENT_QUOTES, 'UTF-8'); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer>
        <p>Generated at <?= date('Y-m-d H:i:s'); ?></p>
    </footer>
</body>
</html>
PHP;
}

/**
 * Template for viewer/assets/style.css
 */
function getViewerStyleTemplate(): string
{
    return <<<'CSS'
:root {
    font-family: "Segoe UI", system-ui, sans-serif;
    color-scheme: light dark;
    color: #0c111d;
    background-color: #f4f6fb;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 2rem;
    background: radial-gradient(circle, #ffffff 0%, #eef2ff 100%);
}

header, footer {
    text-align: center;
    margin-bottom: 2rem;
}

h1, h2, h3 {
    margin: 0;
}

.notice, .empty-state, .card {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
}

.card__header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
}

.badge-group {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
}

.badge {
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    background: #e2e8f0;
    color: #0f172a;
}

.badge--success {
    background: #d1fae5;
    color: #065f46;
}

.badge--muted {
    background: #e2e8f0;
    color: #475569;
}

.muted {
    color: #64748b;
    margin: 0.25rem 0 0;
}

.columns-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.column-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
    background: #f8fafc;
}

.column-card__title {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chip {
    background: #2563eb;
    color: #fff;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
}

.fk-list {
    list-style: none;
    padding: 0;
    margin: 0.5rem 0 0;
}

.fk-list li {
    padding: 0.35rem 0;
    border-bottom: 1px solid #e2e8f0;
}

code {
    background: #0f172a;
    color: #f8fafc;
    padding: 0.15rem 0.35rem;
    border-radius: 6px;
}

@media (max-width: 600px) {
    body {
        padding: 1rem;
    }

    .card__header {
        flex-direction: column;
        align-items: flex-start;
    }
}
CSS;
}

