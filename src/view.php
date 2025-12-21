<?php
declare(strict_types=1);

/**
 * view.php
 * Spins up the PHP built-in server to render the schema viewer.
 * Usage: php manage.php view [--host=127.0.0.1] [--port=8080]
 */
// Resolve project root (works when called from manage.php which sets REUT_PROJECT_ROOT)
$projectRoot = defined('REUT_PROJECT_ROOT') ? REUT_PROJECT_ROOT : getcwd();
$viewerDir   = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'viewer';

// Ensure the viewer scaffolding exists (including the new router.php)
ensureViewerAssets($viewerDir);

$options = parseViewOptions($argv ?? []);
$host    = $options['host'] ?? '127.0.0.1';
$port    = (string)($options['port'] ?? '8080');
$address = "{$host}:{$port}";

// We now require router.php instead of index.php
$routerPath = $viewerDir . '/router.php';

if (!file_exists($routerPath)) {
    fwrite(STDERR, "Viewer bootstrap failed (missing router.php). Aborting.\n");
    exit(1);
}

echo "Opening REUT Schema Viewer on http://{$address}\n";
echo "Press CTRL+C to stop the server.\n\n";

// Correct command: -t points to viewer/, router script is viewer/router.php
$command = sprintf(
    '%s -S %s -t %s %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($address),
    escapeshellarg($viewerDir),          // document root
    escapeshellarg($routerPath)          
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
    $routerPath = $viewerDir . '/router.php';

    if (!file_exists($indexPath)) {
        file_put_contents($indexPath, getViewerIndexTemplate());
    }

    if (!file_exists($stylePath)) {
        file_put_contents($stylePath, getViewerStyleTemplate());
    }


    if (!file_exists($routerPath)) {
        file_put_contents($routerPath, getRouterTemplate());
    }
}

/**
 * Template for viewer/index.php (kept inline to avoid extra dependencies).
 */
function getViewerIndexTemplate(): string{
return <<<'PHP'
<?php
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
PHP;
}

/**
 * Template for viewer/assets/style.css
 */
function getViewerStyleTemplate(): string
{
    return <<<'CSS'
    :root { --bg: #f8fafc; --surface: #ffffff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --primary: #3b82f6; --success: #10b981; --warning: #f59e0b; --purple: #8b5cf6; }
.dark { --bg: #0f172a; --surface: #1e293b; --text: #e2e8f0; --muted: #94a3b8; --border: #334155; --light-pill: #312e81e7; }
* { box-sizing: border-box; margin:0; padding:0; }
body, html { height: 100%; }
body { font-family: "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
.app { display: flex; min-height: 100vh; }
.sidebar {width: 280px;background: var(--surface);border-right: 1px solid var(--border);padding: 1.5rem;position: fixed;top: 0;left: 0;height: 100%;overflow-y: auto;z-index: 50;transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);box-shadow: 4px 0 25px rgba(0,0,0,0.1);}
@media (min-width: 993px) {.sidebar {transform: translateX(0) !important;}.toggle-sidebar-btn { display: none; }    }
@media (max-width: 992px) {.sidebar {transform: translateX(-100%);}.sidebar.open {transform: translateX(0);}.main-content {margin-left: 0 !important;}}
.sidebar.open ~ .main-content::before {content: "";position: fixed;inset: 0;background: rgba(0,0,0,0.4);z-index: 40;animation: fadeIn 0.3s;}
.toggle-sidebar-btn {background: none;border: none;font-size: 1.6rem;cursor: pointer;color: var(--text);padding: 0.2rem;opacity: 0.7;transition: opacity 0.2s;}
.toggle-sidebar-btn:hover { opacity: 1; }
#toggle-sidebar-mobile {display: none;background: var(--primary);color: white;border: none;padding: 0.6rem 1rem;border-radius: 12px;font-size: 1rem;cursor: pointer;}
@media (max-width: 992px) {#toggle-sidebar-mobile { display: block; }}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; }}
.sidebar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; width: 100%; }
.table-list a { display: block; padding: .7rem 1rem; border-radius: 10px; margin: .3rem 0; text-decoration: none; color: var(--text); transition: .2s; }
.table-list a:hover, .table-list a.recent { background: rgba(99,102,241,.15); font-weight: 600; }
.recent-badge { float: right; background: #ef4444; color: white; font-size: .65rem; padding: 2px 6px; border-radius: 6px; }
.main-content { flex: 1; margin-left: 280px; padding: 2rem; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.theme-btn, .toggle-sidebar-btn { background: var(--primary); color: white; border: none; padding: .6rem 1.2rem; border-radius: 12px; cursor: pointer; font-weight: 500; }
.theme-btn:hover { background: #2563eb; }
.header-right {display: flex;align-items: center;gap: 1rem;}
.search-bar { max-width: 720px; margin: 0 auto 3rem; }
#table-search { width: 100%; padding: 1rem 1.4rem; font-size: 1.1rem; border: 1px solid var(--border); border-radius: 16px; background: var(--surface); color: var(--text); box-shadow: 0 10px 30px rgba(0,0,0,.1); }
.card { background: var(--surface); border-radius: 18px; padding: 1.8rem; margin-bottom: 2rem; box-shadow: 0 15px 35px rgba(15,23,42,.08); transition: .3s; }
.card__header {display: flex;justify-content: space-between;align-items: flex-start;margin-bottom: 1.5rem;}
.card:hover { transform: translateY(-4px); box-shadow: 0 25px 50px rgba(15,23,42,.15); }
.card.collapsed .card__body { display: none; }
.chevron { transition: .3s; }
.card.collapsed .chevron { transform: rotate(-90deg); }
.badge-group { display: flex; flex-wrap: wrap; gap: .6rem; }
.badge { padding: .45rem .95rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
.badge--success { background: #d1fae5; color: #065f46; }
.badge--warning { background: #ffebc2; color: #d97706; }
.badge--info { background: #dbeafe; color: #4338ca; }
.badge--purple { background: #e9d5ff; color: #6b21a8; }
.badge--recent { background: #f87171; color: white; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.7; }}
.columns-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1.3rem; margin: 1.5rem 0; }
.column-card { border: 1px solid var(--border); border-radius: 14px; padding: 1.2rem; background: var(--surface); transition: .25s; }
.column-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,.12); border-color: var(--primary); }
.chip { padding: .3rem .8rem; border-radius: 999px; font-size: .75rem; font-weight: 600; color: white; }
.chip--primary { background: var(--primary); }
.chip--fk { background: var(--success); }
.pills { display: flex; flex-wrap: wrap; gap: .55rem; margin: .9rem 0 .6rem; }
.pill { background: #eef2ff; color: #4f46e5; padding: .35rem .75rem; border-radius: 999px; font-size: .84rem; font-family: ui-monospace, monospace; font-weight: 500; }
.dark .pill { background: var(--light-pill); color: rgb(206, 205, 205);}
.fk-info { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; margin-top: .8rem; padding: .7rem; border-radius: 12px; cursor: pointer; transition: .25s; }
.fk-info:hover { background: rgba(16,185,129,.15); }
.fk-arrow { color: var(--success); font-weight: bold; font-size: 1.2em; }
.highlight { animation: highlight 3s; }
@keyframes highlight { 0% { background: rgba(251,191,36,.4); } 100% { background: none; } }
.status { text-align: center; margin: 3rem 0; color: var(--muted); }
#manual-refresh { background: var(--primary); color: white; border: none; padding: .55rem 1.3rem; border-radius: 10px; cursor: pointer; }
@media (max-width: 992px) {.sidebar { transform: translateX(-100%); }.sidebar.open { transform: translateX(0); }.main-content { margin-left: 0; }.mobile { display: block !important; }}
@media (max-width: 640px) {.columns-grid { grid-template-columns: 1fr; }.header { flex-direction: column; text-align: center; }}
CSS;
}



/**
 * router.php – smart static file router for PHP built-in server
 */
function getViewerRouterTemplate(): string
{
    return <<<'PHP'
<?php
declare(strict_types=1);

// Normalise request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$requestUri = '/' . ltrim(preg_replace('#/{2,}#', '/', $requestUri), '/');

$documentRoot = __DIR__;
$filePath     = $documentRoot . $requestUri;

// Serve static files directly
if ($requestUri !== '/' && is_file($filePath)) {
    $realPath = realpath($filePath);
    if ($realPath !== false && strpos($realPath, realpath($documentRoot)) === 0) {
        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $mimes = [
            'css'=>'text/css', 'js'=>'application/javascript', 'json'=>'application/json',
            'png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'gif'=>'image/gif',
            'svg'=>'image/svg+xml', 'webp'=>'image/webp', 'ico'=>'image/x-icon',
            'woff'=>'font/woff', 'woff2'=>'font/woff2', 'ttf'=>'font/ttf',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($realPath);
        exit;
    }
}

// Fall back to the actual viewer
require __DIR__ . '/index.php';
PHP;
}