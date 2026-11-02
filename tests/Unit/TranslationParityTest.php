<?php

/**
 * @return array<int, string> dotted keys of a nested array
 */
function translationKeys(array $messages, string $prefix = ''): array
{
    $keys = [];

    foreach ($messages as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        $keys = array_merge($keys, is_array($value) ? translationKeys($value, $path) : [$path]);
    }

    sort($keys);

    return $keys;
}

it('has identical translation keys in lang/ar and lang/en', function () {
    $files = collect(glob(dirname(__DIR__, 2).'/lang/{ar,en}/*.php', GLOB_BRACE))
        ->map(fn ($path) => basename($path))
        ->unique()
        ->values();

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $ar = dirname(__DIR__, 2).'/lang/ar/'.$file;
        $en = dirname(__DIR__, 2).'/lang/en/'.$file;

        expect(file_exists($ar))->toBeTrue("lang/ar/{$file} is missing");
        expect(file_exists($en))->toBeTrue("lang/en/{$file} is missing");
        expect(translationKeys(require $ar))->toBe(translationKeys(require $en), "Key mismatch in {$file}");
    }
});
