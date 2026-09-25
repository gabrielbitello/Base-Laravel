<?php

/**
 * Merge driver semântico para lang/*.json (registrado em .gitattributes como
 * merge=lang-json; o driver é configurado no composer post-install).
 *
 * Faz o merge em 3 vias dos dicionários de tradução:
 * - chaves novas/alteradas de cada lado entram no resultado
 * - mesma chave alterada nos dois lados de forma diferente: fica a nossa e
 *   um aviso é impresso
 * - saída sempre normalizada (chaves ordenadas, 4 espaços, unicode literal),
 *   igual ao pre-commit — então o pós-merge já fica consistente
 *
 * Uso: php .githooks/merge-lang.php <base> <ours> <theirs>
 */

[$base, $ours, $theirs] = array_slice($argv, 1, 3);

if (!is_file($ours) || !is_file($theirs)) {
    fwrite(STDERR, "[merge-lang] arquivo do merge sumiu, caindo pro conflito textual\n");
    exit(1);
}

$load = function (string $path): array {
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fwrite(STDERR, "[merge-lang] JSON inválido em {$path} (".json_last_error_msg()."), caindo pro conflito textual\n");
        exit(1);
    }
    return $data;
};

$b = $load($base);
$a = $load($ours);
$t = $load($theirs);

$conflicts = [];
$merged = $a;

foreach ($t as $key => $value) {
    if (!array_key_exists($key, $b)) {
        // chave nova do lado deles
        $merged[$key] = $value;
    } elseif (!array_key_exists($key, $a)) {
        // eles mexeram, nós deletamos: mantém a deleção (nosso lado)
        continue;
    } elseif ($b[$key] !== $value && $a[$key] === $b[$key]) {
        // só eles alteraram
        $merged[$key] = $value;
    } elseif ($b[$key] !== $value && $a[$key] !== $value) {
        // os dois alteraram diferente: fica o nosso, avisa
        $conflicts[] = $key;
    }
}

uksort($merged, 'strnatcasecmp');

file_put_contents(
    $ours,
    json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
);

if ($conflicts) {
    fwrite(STDERR, "[merge-lang] traduções alteradas nos dois lados — mantido o valor da branch atual:\n");
    foreach ($conflicts as $key) {
        fwrite(STDERR, "  - {$key}\n");
    }
}

exit(0);
