<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

hh_bootstrap();
$pdo = hh_pdo();

$pdo->exec('CREATE TABLE IF NOT EXISTS hh_schema_migrations (
  name VARCHAR(255) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$dir = dirname(__DIR__) . '/sql';
$files = glob($dir . '/migration_*.sql');
sort($files, SORT_STRING);

$check = $pdo->prepare('SELECT 1 FROM hh_schema_migrations WHERE name = ? LIMIT 1');
$mark = $pdo->prepare('INSERT INTO hh_schema_migrations (name) VALUES (?)');

$applied = 0;
foreach ($files as $path) {
    $name = basename($path);
    $check->execute(array($name));
    if ($check->fetchColumn()) {
        continue;
    }
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        continue;
    }
    echo "Applying {$name}...\n";
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false
            || stripos($msg, 'already exists') !== false) {
            echo "  (skipped duplicate: {$name})\n";
        } else {
            throw $e;
        }
    }
    $mark->execute(array($name));
    $applied++;
}

echo $applied > 0 ? "Done. Applied {$applied} migration(s).\n" : "No pending migrations.\n";
