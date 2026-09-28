<?php

use App\Memory\Domain\ProjectName;

test('it normalizes a project name', function (?string $name, ?string $expected) {
    expect(ProjectName::normalize($name))->toBe($expected);
})->with([
    'mixed case' => ['DbMcp', 'dbmcp'],
    'non-ascii uppercase' => ['Éxito', 'éxito'],
    'surrounding whitespace' => ['  dbmcp  ', 'dbmcp'],
    'repeated dashes' => ['db--mcp', 'db-mcp'],
    'repeated underscores' => ['db___mcp', 'db_mcp'],
    'whitespace only' => ['   ', null],
    'null' => [null, null],
]);
