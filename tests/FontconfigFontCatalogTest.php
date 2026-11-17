<?php

declare(strict_types=1);

use Jovian\Typography\Fontconfig\FontconfigFontCatalog;
use Jovian\Typography\Fontconfig\Tests\Fixtures\FontDirectory;
use Jovian\Typography\Fontconfig\Tests\Fixtures\TestFont;
use Surface\Contracts\Typography\FontRequest;
use Surface\Contracts\Typography\FontSlant;
use Surface\Contracts\Typography\TypographyException;

/*
 * The catalog on fonts the test writes, seen through a fonts.conf of the test's own: each answer
 * is the face fontconfig picks for what the request asked.
 */

/** The fonts most tests ask of, and fontconfig aliases for the three generic families. */
function fonts(): string
{
    $variable = new TestFont('Probe Var', codepoints: [...range(0x41, 0x5A), 0x3A9]);
    $variable->weightAxis = [100, 400, 900];
    $variable->instances = ['Light' => 300, 'Regular' => 400, 'Bold' => 700];

    return FontDirectory::make([
        'sans-regular.ttf' => new TestFont('Probe Sans'),
        'sans-light.ttf' => new TestFont('Probe Sans', 'Light', 300),
        'sans-bold.ttf' => new TestFont('Probe Sans', 'Bold', 700),
        'sans-italic.ttf' => new TestFont('Probe Sans', 'Italic', 400, 'italic'),
        'sans-oblique.ttf' => new TestFont('Probe Sans', 'Oblique', 400, 'oblique'),
        'serif.ttf' => new TestFont('Probe Serif'),
        'mono.ttf' => new TestFont('Probe Mono'),
        'han.ttf' => new TestFont('Probe Han', codepoints: [0x4E2D]),
        'pair.ttc' => [new TestFont('Pair One'), new TestFont('Pair Two')],
        'odd.ttf' => new TestFont('Odd-Name: One, Two'),
        'variable, one.ttf' => $variable,
    ], [
        'sans-serif' => ['Probe Sans'],
        'serif' => ['Probe Serif'],
        'monospace' => ['Probe Mono'],
    ]);
}

it('answers the file and face index fontconfig matches, a face of a collection included', function (): void {
    $fonts = fonts();
    $catalog = new FontconfigFontCatalog();

    expect($catalog->match(new FontRequest(['Pair Two']))->key())->toBe("{$fonts}/pair.ttc#1")
        ->and($catalog->match(new FontRequest(['Pair One']))->key())->toBe("{$fonts}/pair.ttc#0")
        ->and($catalog->match(new FontRequest(['Probe Serif']))->key())->toBe("{$fonts}/serif.ttf#0");
});

it('matches the first of the request\'s families that fontconfig has', function (): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->match(new FontRequest(['No Such Family', 'Probe Mono', 'Probe Serif']))->path)
        ->toBe("{$fonts}/mono.ttf");
});

it('resolves a generic family through fontconfig\'s aliases', function (string $generic, string $file): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->match(new FontRequest([$generic]))->path)->toBe("{$fonts}/{$file}");
})->with([
    ['monospace', 'mono.ttf'],
    ['serif', 'serif.ttf'],
    ['sans-serif', 'sans-regular.ttf'],
]);

it('matches the face nearest the request\'s weight, converted to fontconfig\'s scale by fontconfig', function (int $weight, string $file): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->match(new FontRequest(['Probe Sans'], $weight))->path)->toBe("{$fonts}/{$file}");
})->with([
    [300, 'sans-light.ttf'],
    [400, 'sans-regular.ttf'],
    [700, 'sans-bold.ttf'],
    [900, 'sans-bold.ttf'],
]);

it('matches the face of the request\'s slant', function (FontSlant $slant, string $file): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->match(new FontRequest(['Probe Sans'], slant: $slant))->path)->toBe("{$fonts}/{$file}");
})->with([
    [FontSlant::UPRIGHT, 'sans-regular.ttf'],
    [FontSlant::ITALIC, 'sans-italic.ttf'],
    [FontSlant::OBLIQUE, 'sans-oblique.ttf'],
]);

it('finds a family whose name holds the characters fontconfig\'s name syntax reads', function (): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->match(new FontRequest(['Odd-Name: One, Two']))->path)->toBe("{$fonts}/odd.ttf");
});

it('answers a variable family by its face and the coordinates fontconfig sets for the request', function (int $weight, string $key): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->match(new FontRequest(['Probe Var'], $weight))->key())->toBe("{$fonts}/{$key}");
})->with([
    'a named instance\'s weight' => [700, 'variable, one.ttf#0@wght=700'],
    'the default\'s weight' => [400, 'variable, one.ttf#0@wght=400'],
    'a weight between instances' => [650, 'variable, one.ttf#0@wght=650'],
    'a weight under the lightest instance' => [200, 'variable, one.ttf#0@wght=200'],
]);

it('answers a static family by its face alone, with no variations', function (): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->match(new FontRequest(['Probe Sans'], 700))->key())->toBe("{$fonts}/sans-bold.ttf#0");
});

it('covers a codepoint in a variable font with the coordinates fontconfig sets for the request', function (array $families): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->covering(new FontRequest($families, 650), 0x3A9)?->key())->toBe("{$fonts}/variable, one.ttf#0@wght=650");
})->with([
    'the request\'s own family' => [['Probe Var']],
    'a family the request did not name' => [['Probe Sans']],
]);

it('throws a TypographyException when fontconfig has no fonts', function (): void {
    FontDirectory::make([]);

    expect(fn () => (new FontconfigFontCatalog())->match(new FontRequest()))
        ->toThrow(TypographyException::class, 'Fontconfig has no font to match');
});

it('covers a codepoint with the first face in fontconfig\'s order for the request that has it', function (array $families, int $weight, int $codepoint, string $file): void {
    $fonts = fonts();

    expect((new FontconfigFontCatalog())->covering(new FontRequest($families, $weight), $codepoint)?->path)->toBe("{$fonts}/{$file}");
})->with([
    'the request\'s own face' => [['Probe Sans'], 700, 0x41, 'sans-bold.ttf'],
    'the request\'s family first' => [['Probe Serif'], 400, 0x41, 'serif.ttf'],
    'a face the request did not name' => [['Probe Sans'], 400, 0x4E2D, 'han.ttf'],
]);

it('covers a codepoint no face has with null', function (): void {
    fonts();

    expect((new FontconfigFontCatalog())->covering(new FontRequest(['Probe Sans']), 0x1F600))->toBeNull();
});

it('keeps each request\'s and each codepoint\'s answer apart in one catalog', function (): void {
    $fonts = fonts();
    $catalog = new FontconfigFontCatalog();
    $sans = new FontRequest(['Probe Sans']);
    $serif = new FontRequest(['Probe Serif']);

    expect($catalog->covering($sans, 0x41)?->path)->toBe("{$fonts}/sans-regular.ttf")
        ->and($catalog->covering($sans, 0x4E2D)?->path)->toBe("{$fonts}/han.ttf")
        ->and($catalog->covering($sans, 0x3A9)?->key())->toBe("{$fonts}/variable, one.ttf#0@wght=400")
        ->and($catalog->covering($serif, 0x41)?->path)->toBe("{$fonts}/serif.ttf")
        ->and($catalog->covering($sans, 0x41)?->path)->toBe("{$fonts}/sans-regular.ttf");
});
