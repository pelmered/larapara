<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Pelmered\LaraPara\Exceptions\InvalidColumnScale;

/**
 * Laravel 12 moved the connection into the Blueprint constructor's first argument.
 */
function newBlueprint(string $table, ?Connection $connection = null): Blueprint
{
    $firstParameter = (new ReflectionMethod(Blueprint::class, '__construct'))->getParameters()[0];

    return $firstParameter->getName() === 'connection'
        ? new Blueprint($connection ?? DB::connection(), $table)
        : new Blueprint($table);
}

/**
 * The SQL a macro compiles to under a given grammar.
 *
 * Laravel 12 moved the grammar from an argument of toSql() to a property of the connection, and made
 * the grammars take that connection, so both shapes are built here rather than in every expectation.
 */
function compileMacro(string $macro, string $grammarClass): string
{
    $connection = clone DB::connection();
    $grammar    = (new ReflectionClass($grammarClass))->getConstructor()?->getNumberOfParameters() > 0
        ? new $grammarClass($connection)
        : new $grammarClass;

    // Laravel 11 hands the connection and the grammar to toSql(); 12 reads both off the blueprint's
    // connection. Invoked through the reflection that reads the arity, since a call written for one
    // version has the wrong number of arguments for the other.
    $toSql     = new ReflectionMethod(Blueprint::class, 'toSql');
    $arguments = $toSql->getNumberOfParameters() > 0 ? [$connection, $grammar] : [];

    if ($arguments === []) {
        $connection->setSchemaGrammar($grammar);
    }

    $blueprint = newBlueprint('test_table', $connection);
    $blueprint->create();
    $blueprint->{$macro}('price');

    return implode(' | ', $toSql->invokeArgs($blueprint, $arguments));
}

/**
 * The columns a macro writes, as a comparable shape.
 *
 * Every attribute is listed rather than matched as a subset, since the attributes a macro does not
 * set are exactly the ones the drift between the four macros lived in.
 */
function macroColumns(string $macro, array $arguments = []): array
{
    $blueprint = newBlueprint('test_table');
    $returned  = $blueprint->{$macro}('price', ...$arguments);
    $columns   = $blueprint->getColumns();

    $describe = static fn (array $attributes): array => [
        'type'     => $attributes['type'],
        'name'     => $attributes['name'],
        'unsigned' => $attributes['unsigned'] ?? false,
        'nullable' => $attributes['nullable'] ?? false,
        'length'   => $attributes['length']   ?? null,
        'total'    => $attributes['total']    ?? null,
        'places'   => $attributes['places']   ?? null,
        'default'  => $attributes['default']  ?? null,
    ];

    return [
        'returned' => $returned === $columns[0],
        'count'    => count($columns),
        'amount'   => $describe($columns[0]->getAttributes()),
        'currency' => $describe($columns[1]->getAttributes()),
    ];
}

function amount(string $type, bool $unsigned = false, bool $nullable = false, ?int $total = null, ?int $places = null): array
{
    return [
        'type'     => $type,
        'name'     => 'price',
        'unsigned' => $unsigned,
        'nullable' => $nullable,
        'length'   => null,
        'total'    => $total,
        'places'   => $places,
        'default'  => null,
    ];
}

function currencyColumn(string $name = 'price_currency'): array
{
    return [
        'type'     => 'string',
        'name'     => $name,
        'unsigned' => false,
        'nullable' => false,
        'length'   => 12,
        'total'    => null,
        'places'   => null,
        'default'  => 'USD',
    ];
}

it('registers database macros', function (): void {
    expect(Blueprint::hasMacro('money'))->toBeTrue()
        ->and(Blueprint::hasMacro('nullableMoney'))->toBeTrue()
        ->and(Blueprint::hasMacro('smallMoney'))->toBeTrue()
        ->and(Blueprint::hasMacro('unsignedMoney'))->toBeTrue();
});

// One row per macro and store format, asserted whole: unsigned only where the name says so, nullable
// only where the name says so, and the currency column identical everywhere.
it('writes the columns of each macro', function (string $macro, string $format, array $amount): void {
    config(['larapara.store.format' => $format]);

    expect(macroColumns($macro))->toBe([
        'returned' => true,
        'count'    => 2,
        'amount'   => $amount,
        'currency' => currencyColumn(),
    ]);
})->with([
    'money, int'             => ['money', 'int', amount('bigInteger')],
    'money, decimal'         => ['money', 'decimal', amount('decimal', total: 12, places: 3)],
    'nullableMoney, int'     => ['nullableMoney', 'int', amount('bigInteger', nullable: true)],
    'nullableMoney, decimal' => ['nullableMoney', 'decimal', amount('decimal', nullable: true, total: 12, places: 3)],
    'smallMoney, int'        => ['smallMoney', 'int', amount('smallInteger', unsigned: true, nullable: true)],
    'smallMoney, decimal'    => ['smallMoney', 'decimal', amount('decimal', unsigned: true, nullable: true, total: 6, places: 3)],
    'unsignedMoney, int'     => ['unsignedMoney', 'int', amount('bigInteger', unsigned: true)],
    'unsignedMoney, decimal' => ['unsignedMoney', 'decimal', amount('decimal', unsigned: true, total: 12, places: 3)],
]);

it('names the currency column after the configured suffix', function (): void {
    config(['larapara.currency_column_suffix' => '_my_currency']);

    expect(macroColumns('money')['currency'])->toBe(currencyColumn('price_my_currency'));
});

it('indexes the currency and the amount together', function (string $macro): void {
    $blueprint = newBlueprint('test_table');
    $blueprint->{$macro}('price');

    $indexes = array_values(array_filter(
        $blueprint->getCommands(),
        static fn (Fluent $command): bool => (bool) $command->get('index'),
    ));

    expect($indexes)->toHaveCount(1)
        ->and($indexes[0]->get('columns'))->toBe(['price_currency', 'price'])
        ->and($indexes[0]->get('index'))->toBe('test_table_price_currency_price_index');
})->with(['money', 'nullableMoney', 'smallMoney', 'unsignedMoney']);

it('takes a name for the index', function (): void {
    $blueprint = newBlueprint('test_table');
    $blueprint->money('price', 'my_index');

    $indexes = array_values(array_filter(
        $blueprint->getCommands(),
        static fn (Fluent $command): bool => (bool) $command->get('index'),
    ));

    expect($indexes[0]->get('index'))->toBe('my_index');
});

// The scale of a decimal column decides which currencies it can hold, so it is an argument as well
// as a config key.
it('takes the decimal scale from the config', function (): void {
    config(['larapara.store.format' => 'decimal', 'larapara.store.decimal_scale' => 8]);

    expect(macroColumns('money')['amount'])->toBe(amount('decimal', total: 12, places: 8));
});

it('takes the decimal scale from the macro', function (): void {
    config(['larapara.store.format' => 'decimal']);

    expect(macroColumns('money', [null, 4])['amount'])->toBe(amount('decimal', total: 12, places: 4));
});

// A decimal column cannot keep more decimals than it holds digits: MySQL and PostgreSQL refuse the
// column outright, while SQLite takes it and every amount written to it — so this used to be
// something a project heard from its own database at deploy time, with a green test suite behind it.
// smallMoney() is where it bites: its six digits have no room for the eight decimals a crypto amount
// needs, which is the scale the README tells crypto projects to configure.
it('refuses a scale the column has no digits for', function (string $macro, ?int $scale, int $configured): void {
    config(['larapara.store.format' => 'decimal', 'larapara.store.decimal_scale' => $configured]);

    expect(fn (): array => macroColumns($macro, [null, $scale]))
        ->toThrow(InvalidColumnScale::class);
})->with([
    'small column, scale from the macro'  => ['smallMoney', 8, 3],
    'small column, scale from the config' => ['smallMoney', null, 8],
    'as many decimals as digits'          => ['smallMoney', 6, 3],
    'wide column, from the macro'         => ['money', 12, 3],
    'wide column, from the config'        => ['money', null, 20],
]);

it('takes a scale the column has one digit left for', function (): void {
    config(['larapara.store.format' => 'decimal']);

    expect(macroColumns('smallMoney', [null, 5])['amount'])
        ->toBe(amount('decimal', unsigned: true, nullable: true, total: 6, places: 5));
});

// The way out of the exception is the point of it, so the message names both numbers and the macro
// that has the digits to spare.
it('names the digits and the wider macro when it refuses a scale', function (): void {
    expect(InvalidColumnScale::exceedsColumnDigits('price', 8, 6)->getMessage())
        ->toContain('"price"')
        ->toContain('8 decimals')
        ->toContain('6 digits')
        ->toContain('money() holds 12');
});

// The scale belongs to a decimal column, so integer storage passes it by rather than refusing it: a
// project storing minor units has no column for the decimals to be too many for.
it('ignores a scale integer storage has no column for', function (): void {
    config(['larapara.store.format' => 'int', 'larapara.store.decimal_scale' => 8]);

    expect(macroColumns('smallMoney', [null, 8])['amount'])
        ->toBe(amount('smallInteger', unsigned: true, nullable: true));
});

// The returned column is the amount, so the chain lands where it reads as landing.
it('returns a column the caller can keep building on', function (): void {
    $blueprint = newBlueprint('test_table');
    $blueprint->money('price')->nullable()->default(0);

    [$amount, $currency] = $blueprint->getColumns();

    expect($amount->get('nullable'))->toBeTrue()
        ->and($amount->get('default'))->toBe(0)
        ->and($currency->get('nullable'))->toBeFalsy()
        ->and($currency->get('default'))->toBe('USD');
});

// Attributes are one thing and the SQL a driver writes is another, so each macro is compiled through
// the two grammars whose types differ most from SQLite's.
it('compiles through the MySQL and Postgres grammars', function (string $macro, string $format, string $mysql, string $postgres): void {
    config(['larapara.store.format' => $format]);

    expect(compileMacro($macro, MySqlGrammar::class))->toContain($mysql)
        ->and(compileMacro($macro, PostgresGrammar::class))->toContain($postgres);
})->with([
    'money, int' => [
        'money', 'int',
        "`price` bigint not null, `price_currency` varchar(12) not null default 'USD'",
        '"price" bigint not null, "price_currency" varchar(12) not null default \'USD\'',
    ],
    'nullableMoney, int' => [
        'nullableMoney', 'int',
        '`price` bigint null',
        '"price" bigint null',
    ],
    'smallMoney, int' => [
        'smallMoney', 'int',
        '`price` smallint unsigned null',
        '"price" smallint null',
    ],
    'unsignedMoney, int' => [
        'unsignedMoney', 'int',
        '`price` bigint unsigned not null',
        '"price" bigint not null',
    ],
    'money, decimal' => [
        'money', 'decimal',
        '`price` decimal(12, 3) not null',
        '"price" decimal(12, 3) not null',
    ],
    'unsignedMoney, decimal' => [
        'unsignedMoney', 'decimal',
        '`price` decimal(12, 3) unsigned not null',
        '"price" decimal(12, 3) not null',
    ],
]);

// A scale is a count of decimals, so a negative one is not a narrower column but a nonsense one: the
// macro wrote decimal(12, -1), which MySQL rejects outright and other drivers read as they please.
it('refuses a negative scale', function (?int $scale, int $configured): void {
    config(['larapara.store.format' => 'decimal', 'larapara.store.decimal_scale' => $configured]);

    expect(fn (): array => macroColumns('money', [null, $scale]))->toThrow(InvalidColumnScale::class);
})->with([
    'from the macro'  => [-1, 3],
    'from the config' => [null, -1],
]);
