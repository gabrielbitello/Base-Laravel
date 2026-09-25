<?php

/**
 * Scans Blade views for __() translation calls and checks lang JSON files for missing entries.
 *
 * Unlike the original vippers version (which scanned a fixed components/v2 dir),
 * this one scans the whole resources/views tree and discovers the locales from
 * the lang/*.json files themselves.
 *
 * Usage: php scripts/find-missing-translations.php [--json] [--verbose]
 *   --json     Output as JSON for programmatic consumption
 *   --verbose  Also show per-file key listing
 */
$viewsDir = __DIR__.'/../resources/views';
$langDir = __DIR__.'/../lang';
$outputJson = false;
$verbose = false;

foreach ($argv as $arg) {
    if ($arg === '--json') {
        $outputJson = true;
    } elseif ($arg === '--verbose') {
        $verbose = true;
    } elseif (str_starts_with($arg, '--views-dir=')) {
        $viewsDir = substr($arg, strlen('--views-dir='));
    } elseif (str_starts_with($arg, '--lang-dir=')) {
        $langDir = substr($arg, strlen('--lang-dir='));
    }
}

$viewsDir = realpath($viewsDir) ?: $viewsDir;
$langDir = realpath($langDir) ?: $langDir;

// Load every locale dictionary available in lang/*.json
$locales = [];
$translations = [];

foreach (glob($langDir.'/*.json') ?: [] as $file) {
    $locale = basename($file, '.json');
    $data = json_decode((string) file_get_contents($file), true);

    if (is_array($data)) {
        $locales[] = $locale;
        $translations[$locale] = $data;
    }
}

$locales = array_unique($locales);

// Scan the whole views tree for .blade.php files
$views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
$views->setFlags(RecursiveIteratorIterator::LEAVES_ONLY);

$allKeys = [];
$fileKeys = [];

foreach ($views as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $content = file_get_contents($file->getRealPath());
    $relativePath = str_replace($viewsDir.'/', '', $file->getRealPath());

    preg_match_all("/__\s*\(\s*(['\"])(.+?)\\1\s*\)/", $content, $matches);

    $keys = array_unique($matches[2]);

    if (! empty($keys)) {
        $fileKeys[$relativePath] = $keys;

        foreach ($keys as $key) {
            $allKeys[$key] = true;
        }
    }
}

$allKeys = array_keys($allKeys);
sort($allKeys);

// Find missing translations per locale
$missing = [];

foreach ($locales as $locale) {
    $missing[$locale] = array_values(array_filter(
        $allKeys,
        fn (string $key): bool => ! isset($translations[$locale][$key])
    ));
}

if ($outputJson) {
    $result = [
        'total_keys' => count($allKeys),
        'all_keys' => $allKeys,
        'file_keys' => $fileKeys,
        'locales' => $locales,
    ];

    foreach ($locales as $locale) {
        $result['missing_'.$locale] = $missing[$locale];
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($allKeys)) {
    echo "No translation keys found in any Blade view.\n";
    exit;
}

$allMissing = array_merge(...array_values($missing));

if ($allMissing === []) {
    echo 'All '.count($allKeys).' translation keys are present in: '.implode(', ', $locales).".\n";
    exit;
}

echo "=== Missing Translations ===\n\n";

foreach ($locales as $locale) {
    echo $locale.'.json — '.count($missing[$locale]).' missing out of '.count($allKeys)." keys used in views:\n";

    foreach ($missing[$locale] as $key) {
        echo '  '.json_encode($key, JSON_UNESCAPED_UNICODE)."\n";
    }

    echo "\n";
}

if ($verbose) {
    echo "\n--- Per-file key listing ---\n";

    foreach ($fileKeys as $file => $keys) {
        echo "\n$file:\n";

        foreach ($keys as $key) {
            $marks = [];

            foreach ($locales as $locale) {
                $marks[$locale] = isset($translations[$locale][$key]) ? '✓' : '✗';
            }

            echo '  ['.implode(' ', array_map(fn ($l, $m) => "$l:$m", array_keys($marks), $marks)).'] '
                .json_encode($key, JSON_UNESCAPED_UNICODE)."\n";
        }
    }
}

echo "\nDone.\n";
