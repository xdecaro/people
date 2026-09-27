<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$replace = static function (string $relative, array $replacements) use ($root): void {
    $path = $root . '/' . $relative;
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $relative);
    }

    $updated = $content;
    foreach ($replacements as [$from, $to]) {
        if (str_contains($updated, $to)) {
            continue;
        }
        $updated = str_replace($from, $to, $updated, $count);
        if ($count === 0) {
            throw new RuntimeException('Expected pattern not found in ' . $relative . ': ' . $from);
        }
    }

    if ($updated !== $content && file_put_contents($path, $updated) === false) {
        throw new RuntimeException('Unable to write ' . $relative);
    }
};

$replace('component/admin/tmpl/duplicates/default.php', [
    ['class="xdecaro-scope ', 'class="xdecaro-scope xdecaro-suite '],
]);

$replace('component/admin/tmpl/import/default.php', [
    ['class="xdecaro-scope ', 'class="xdecaro-scope xdecaro-suite '],
]);

$replace('component/admin/tmpl/information/default.php', [
    ['class="xdecaro-scope ', 'class="xdecaro-scope xdecaro-suite '],
    ['class="xdecaro-info-grid ', 'class="xdecaro-suite__info-grid xdecaro-info-grid '],
    ['class="xdecaro-info-card', 'class="xdecaro-suite__info-card xdecaro-info-card'],
]);

$replace('component/admin/tmpl/person/edit.php', [
    ['class="form-validate"', 'class="form-validate xdecaro-form"'],
    ['class="xdecaro-scope xdecaro-people-person-edit"', 'class="xdecaro-scope xdecaro-suite xdecaro-people-person-edit"'],
    ['class="accordion xdecaro-person-accordion"', 'class="accordion xdecaro-accordion xdecaro-person-accordion"'],
    ['class="accordion-item xdecaro-person-section"', 'class="accordion-item xdecaro-accordion__item xdecaro-person-section"'],
    ['class="accordion-button', 'class="accordion-button xdecaro-accordion__button'],
    ['class="accordion-body"', 'class="accordion-body xdecaro-accordion__body"'],
]);

fwrite(STDOUT, "People 1.8.0 Core admin UI template migration applied.\n");
