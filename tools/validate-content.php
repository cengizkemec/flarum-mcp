<?php

require __DIR__.'/../vendor/autoload.php';

use CengizKemec\FlarumMcp\Skill\SkillParser;
use Symfony\Component\Yaml\Yaml;

$failed = false;

foreach (['locale/en.yml', 'locale/tr.yml'] as $file) {
    try {
        Yaml::parseFile(__DIR__.'/../'.$file);
        echo "$file OK\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "$file FAIL: {$e->getMessage()}\n";
    }
}

$parser = new SkillParser();
$skills = glob(__DIR__.'/../resources/skills/*/SKILL.md') ?: [];

foreach ($skills as $file) {
    try {
        $skill = $parser->parse((string) file_get_contents($file), 'builtin');
        echo basename(dirname($file))." => {$skill->slug} OK\n";
    } catch (Throwable $e) {
        $failed = true;
        echo basename(dirname($file))." FAIL: {$e->getMessage()}\n";
    }
}

exit($failed ? 1 : 0);
