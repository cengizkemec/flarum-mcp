<?php

/*
 * Lightweight repository checks that do not require a database.
 * Run with: php tools/lint.php
 */

$root = dirname(__DIR__);
$failed = false;

$targets = [
    $root.'/src',
    $root.'/migrations',
    $root.'/tests',
    $root.'/tools',
    $root.'/extend.php',
];

$files = [];

foreach ($targets as $target) {
    if (is_file($target)) {
        $files[] = $target;
        continue;
    }

    if (! is_dir($target)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

foreach ($files as $file) {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $code);

    if ($code !== 0) {
        $failed = true;
        echo implode("\n", $output)."\n";
    }
}

echo 'PHP syntax: '.count($files)." files checked.\n";

require $root.'/vendor/autoload.php';

foreach (['locale/en.yml', 'locale/tr.yml'] as $localeFile) {
    try {
        Symfony\Component\Yaml\Yaml::parseFile($root.'/'.$localeFile);
        echo "$localeFile: valid\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "$localeFile: INVALID - {$e->getMessage()}\n";
    }
}

$parser = new CengizKemec\FlarumMcp\Skill\SkillParser();
$skills = glob($root.'/resources/skills/*/SKILL.md') ?: [];

foreach ($skills as $skillFile) {
    try {
        $parser->parse((string) file_get_contents($skillFile), 'builtin');
    } catch (Throwable $e) {
        $failed = true;
        echo basename(dirname($skillFile)).": INVALID - {$e->getMessage()}\n";
    }
}

echo 'Skills: '.count($skills)." files checked.\n";

exit($failed ? 1 : 0);
