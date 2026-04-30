<?php

$root = dirname(__DIR__);

function failPackageStructure(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (is_dir($root . '/src/Http/Controllers')) {
    failPackageStructure('The package should not ship default HTTP controllers.');
}

$readme = file_get_contents($root . '/README.md');
if ($readme === false) {
    failPackageStructure('Unable to read README.md.');
}

if (str_contains($readme, '默认控制器')) {
    failPackageStructure('README.md should not document a default controller.');
}

