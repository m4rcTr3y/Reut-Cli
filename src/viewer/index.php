<?php
declare(strict_types=1);

/**
 * Lightweight schema viewer that renders metadata about every model/table.
 * This script is copied into each new project under the /viewer directory.
 */

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';
require $projectRoot . '/config.php';

$modelsDir = $projectRoot . '/models';
$modelsNamespace = 'Reut\\Models\\';
$tables = [];
$errors = [];

/**
 * Attempt to load a model class and extract its column + relationship info.
 */
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
        /** @var \Reut\DB\DataBase $instance */
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

