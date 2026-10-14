<?php
/**
 * Lang checker: fa/en parity for lang/*.php and every __('group.key') / trans('group.key')
 * used under app/ and Modules/ must exist in both locales. Exit 1 on problems.
 *   php scripts/check-lang-parity.php
 */
$root = dirname(__DIR__);
$flat = function (array $a, string $p = '') use (&$flat): array {
    $o = [];
    foreach ($a as $k => $v) {
        $key = $p === '' ? (string) $k : $p.'.'.$k;
        is_array($v) ? $o += $flat($v, $key) : $o[$key] = $v;
    }
    return $o;
};
$cat = [];
foreach (['fa', 'en'] as $loc) {
    foreach (glob("$root/lang/$loc/*.php") as $f) {
        foreach ($flat(require $f, basename($f, '.php')) as $k => $v) {
            $cat[$loc][$k] = $v;
        }
    }
}
$errors = [];
foreach (array_diff_key($cat['fa'], $cat['en']) as $k => $_) $errors[] = "only in fa: $k";
foreach (array_diff_key($cat['en'], $cat['fa']) as $k => $_) $errors[] = "only in en: $k";
foreach ($cat['fa'] as $k => $v) if (trim((string) $v) === '') $errors[] = "empty fa: $k";
$groups = array_unique(array_map(fn ($k) => explode('.', $k)[0], array_keys($cat['fa'])));
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php' || preg_match('#/(vendor|storage|lang|node_modules)/#', $path)) continue;
    preg_match_all("/(?:__|trans|trans_choice)\\(\\s*'([a-z_]+)\\.([A-Za-z0-9_.]+)'/", file_get_contents($path), $m, PREG_SET_ORDER);
    foreach ($m as $hit) {
        if (! in_array($hit[1], $groups, true)) continue;
        $key = $hit[1].'.'.$hit[2];
        foreach (['fa', 'en'] as $loc) {
            if (! isset($cat[$loc][$key])) $errors[] = "missing $loc: $key (".str_replace($root.'/', '', $path).')';
        }
    }
}
if ($errors) {
    fwrite(STDERR, implode("\n", array_unique($errors))."\n".count(array_unique($errors))." problem(s)\n");
    exit(1);
}
echo 'lang parity OK: '.count($cat['fa'])." keys in fa/en, all used keys present.\n";
