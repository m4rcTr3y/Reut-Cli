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
            if ($fk['column'] === $name) { $foreignKey = $fk; break; }
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

    return [
        'class' => $className,
        'table' => $instance->tableName ?? pathinfo($filePath, PATHINFO_FILENAME),
        'columns' => $columns,
        'hasRelationships' => $hasRelationships,
        'relationshipCount' => $relationshipCount,
        'traits' => $traits,
        'hasDeletedAt' => $hasDeletedAt,
        'hasTimestamps' => $hasTimestamps,
        'filemtime' => $mtime,
        'modifiedAgo' => time() - $mtime
    ];
};

if (is_dir($modelsDir)) {
    $files = glob($modelsDir . '/*.php') ?: [];
    foreach ($files as $modelFile) {
        $metadata = $loadModelMetadata($modelFile);
        if ($metadata !== null) {
            $tables[] = $metadata;
        }
    }
    // Sort: recently modified first
    usort($tables, fn($a, $b) => $b['filemtime'] <=> $a['filemtime']);
} else {
    $errors[] = "Models directory not found at {$modelsDir}";
}

$generated = date('Y-m-d H:i:s');
$partial = !empty($_GET['partial']);
?>

<?php if (!$partial): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>REUT Schema Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Tables (<?= count($tables) ?>)</h3>
            <button id="toggle-sidebar" class="toggle-sidebar-btn">✕</button>
        </div>
        <nav class="table-list">
            <?php foreach ($tables as $i => $table): ?>
            <a href="#table-<?= htmlspecialchars(strtolower($table['table'])) ?>" 
               class="table-link <?= $i < 3 ? 'recent' : '' ?>">
                <?= htmlspecialchars($table['table']) ?>
                <?php if ($i < 3): ?>
                <span class="recent-badge">Recent</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <div class="header-left">
                <h1>REUT Schema Viewer</h1>
                <p>Realtime snapshot of your model definitions</p>
            </div>
            <div class="header-right">
                <button id="theme-toggle" class="theme-btn" aria-label="Toggle dark mode">Dark Mode</button>
                <button id="toggle-sidebar-mobile" class="toggle-sidebar-btn mobile">☰ Tables</button>
            </div>
        </header>

        <div class="search-bar">
            <input type="text" id="table-search" placeholder="Search tables, columns, traits…" autocomplete="off">
        </div>

        <div id="dynamic-content">
<?php endif; ?>

<?php if (!empty($errors)): ?>
<section class="notice">
    <h2>Warnings</h2>
    <ul><?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
    <?php endforeach; ?></ul>
</section>
<?php endif; ?>

<?php if (empty($tables)): ?>
<section class="empty-state">
    <p>No models found. Create one and watch it appear instantly.</p>
</section>
<?php else: foreach ($tables as $table): ?>
<section class="card" id="table-<?= htmlspecialchars(strtolower($table['table'])) ?>">
    <div class="card__header">
        <div class="header-left">
            <h2><?= htmlspecialchars($table['table']) ?></h2>
            <p class="muted"><?= htmlspecialchars($table['class']) ?></p>
        </div>
        <div class="header-right">
            <div class="badge-group">
                <?php if ($table['relationshipCount'] > 0): ?>
                <span class="badge badge--success">Relations: <?= $table['relationshipCount'] ?></span>
                <?php endif; ?>
                <?php if ($table['hasDeletedAt']): ?>
                <span class="badge badge--warning">Soft Deletes</span>
                <?php endif; ?>
                <?php if ($table['hasTimestamps']): ?>
                <span class="badge badge--info">Timestamps</span>
                <?php endif; ?>
                <?php if ($table['traits']): ?>
                <span class="badge badge--purple"><?= count($table['traits']) ?> trait<?= count($table['traits'])>1?'s':'' ?></span>
                <?php endif; ?>
                <?php if ($table['modifiedAgo'] < 3600): ?>
                <span class="badge badge--recent">
                    <?= $table['modifiedAgo'] < 300 ? 'Just now' : round($table['modifiedAgo']/60).'m ago' ?>
                </span>
                <?php endif; ?>
            </div>
            <button class="toggle-btn"><span class="chevron">▼</span></button>
        </div>
    </div>

    <div class="card__body">
        <h3>Columns</h3>
        <div class="columns-grid">
            <?php foreach ($table['columns'] as $column): ?>
            <article class="column-card">
                <div class="column-card__header">
                    <strong class="column-name"><?= htmlspecialchars($column['name']) ?></strong>
                    <div class="chip-group">
                        <?php if ($column['isPrimary']): ?><span class="chip chip--primary">PK</span><?php endif; ?>
                        <?php if ($column['foreignKey']): ?><span class="chip chip--fk">FK</span><?php endif; ?>
                    </div>
                </div>

                <div class="pills">
                    <?php
                    $def = trim($column['definition']);
                    if ($def && $def !== 'N/A') {
                        foreach (preg_split('/\s+/', $def) as $part)
                            echo '<span class="pill">'.htmlspecialchars($part).'</span>';
                    } else {
                        echo '<span class="pill pill--muted">N/A</span>';
                    }
                    ?>
                </div>

                <?php if ($column['foreignKey']): ?>
                <div class="fk-info" data-ref-table="<?= htmlspecialchars($column['foreignKey']['referenced_table']) ?>">
                    <span class="fk-arrow">→</span>
                    <strong><?= htmlspecialchars($column['foreignKey']['referenced_table']) ?>.</strong>
                    <code><?= htmlspecialchars($column['foreignKey']['referenced_column']) ?></code>
                    <span class="fk-actions">
                        • Delete: <?= strtoupper(htmlspecialchars($column['foreignKey']['on_delete'])) ?>
                        • Update: <?= strtoupper(htmlspecialchars($column['foreignKey']['on_update'])) ?>
                    </span>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endforeach; endif; ?>

<div class="status">
    <p>Generated: <?= $generated ?> • <button id="manual-refresh">Refresh now</button></p>
</div>

<?php if (!$partial): ?>
        </div>
    </main>
</div>

<script>
const themeKey = 'reut-viewer-theme';
const savedTheme = localStorage.getItem(themeKey);
if (savedTheme) document.documentElement.classList.add(savedTheme);
else if (window.matchMedia('(prefers-color-scheme: dark)').matches)
    document.documentElement.classList.add('dark');

document.getElementById('theme-toggle').addEventListener('click', () => {
    document.documentElement.classList.toggle('dark');
    const isDark = document.documentElement.classList.contains('dark');
    localStorage.setItem(themeKey, isDark ? 'dark' : 'light');
    document.getElementById('theme-toggle').textContent = isDark ? 'Light Mode' : 'Dark Mode';
});

async function updateContent() {
    const url = new URL(location.href); url.searchParams.set('partial', '1');
    const html = await fetch(url).then(r => r.text());
    document.getElementById('dynamic-content').innerHTML = html;
    initInteractivity();
}

function initInteractivity() {
    document.querySelectorAll('.toggle-btn').forEach(b => b.onclick = () => b.closest('.card').classList.toggle('collapsed'));
    document.querySelectorAll('.fk-info').forEach(el => {
        el.onclick = () => {
            const ref = el.dataset.refTable?.toLowerCase();
            const target = document.querySelector(`#table-${ref}`);
            if (target) {
                target.scrollIntoView({behavior: 'smooth'});
                target.classList.add('highlight');
                setTimeout(() => target.classList.remove('highlight'), 3000);
            }
        };
    });
}

function filterTables() {
    const term = document.getElementById('table-search').value.toLowerCase();
    document.querySelectorAll('.card').forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(term) ? 'block' : 'none';
    });
}

document.getElementById('table-search').addEventListener('input', filterTables);
document.getElementById('manual-refresh').onclick = updateContent;
document.getElementById('toggle-sidebar-mobile').onclick = () => document.getElementById('sidebar').classList.toggle('open');
document.getElementById('toggle-sidebar').onclick = () => document.getElementById('sidebar').classList.remove('open');

setInterval(updateContent, 15000);
initInteractivity();
filterTables();
</script>
</body>
</html>
<?php endif; ?>