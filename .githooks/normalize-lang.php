<?php

/**
 * Normaliza arquivos de tradução (lang/*.json):
 * - valida o JSON (erro aborta o commit)
 * - ordena as chaves naturalmente (case-insensitive)
 * - indentação de 4 espaços, unicode e slashes sem escape
 *
 * Uso: php .githooks/normalize-lang.php <arquivo> [arquivo...]
 * Escreve o arquivo normalizado apenas quando houver mudança.
 */

$exit = 0;

foreach (array_slice($argv, 1) as $file) {
    if (!is_file($file)) {
        continue;
    }

    $raw = file_get_contents($file);
    $data = json_decode($raw, true);

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "[normalize-lang] JSON inválido em {$file}: ".json_last_error_msg()."\n");
        $exit = 1;
        continue;
    }

    uksort($data, 'strnatcasecmp');

    $normalized = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

    if ($normalized !== $raw) {
        file_put_contents($file, $normalized);
        fwrite(STDOUT, "[normalize-lang] normalizado: {$file}\n");
    }
}

exit($exit);
