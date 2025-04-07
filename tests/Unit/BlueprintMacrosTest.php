<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

it('registers database macros', function (): void {
    // Test that the macros for Blueprint are registered
    expect(Blueprint::hasMacro('money'))->toBeTrue();
    expect(Blueprint::hasMacro('nullableMoney'))->toBeTrue();
    expect(Blueprint::hasMacro('smallMoney'))->toBeTrue();
    expect(Blueprint::hasMacro('unsignedMoney'))->toBeTrue();
});

it('has correct blueprint money macro implementation', function ($macro, $config, $expected): void {
    $config = array_merge(config('larapara'), $config);

    config(['larapara' => $config]);

    $blueprint = new Blueprint('test_table');

    $moneyColumn = $blueprint->{$macro}('price');
    $columns     = $blueprint->getColumns();
    $commands    = $blueprint->getCommands();

    expect($moneyColumn)->toBe($columns[0]);
    $moneyColumnAttributes = $moneyColumn->getAttributes();

    expect($moneyColumn)->toBeInstanceOf(ColumnDefinition::class);
    expect($moneyColumnAttributes)->toMatchArray($expected['price']);

    $currencyColumn = $columns[1];

    expect($currencyColumn)->toBeInstanceOf(ColumnDefinition::class);
    $currencyColumnAttributes = $currencyColumn->getAttributes();

    expect($currencyColumnAttributes)->toMatchArray($expected['currency']);

    if (isset($expected['index'])) {
        $indexes = \Illuminate\Support\Arr::where($commands, function ($command): bool {
            return $command instanceof \Illuminate\Support\Fluent && $command->index;
        });

        expect(count($indexes))->toBe(1);

        $indexAttributes = array_shift($indexes)?->getAttributes();

        foreach ($expected['index'] as $columnName => $indexAttribute) {
            expect($indexAttributes[$columnName])->toBe($indexAttribute);
        }
    }

})->with([
    'money' => [
        'macro'    => 'money',
        'config'   => [],
        'expected' => [
            'price' => [
                'type'          => 'bigInteger',
                'name'          => 'price',
                'autoIncrement' => false,
                'unsigned'      => false,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_currency',
                'length' => 6,
            ],
            'index' => [
                'name'    => 'index',
                'index'   => 'test_table_price_currency_price_index',
                'columns' => ['price_currency', 'price'],
            ],
        ],
    ],
    'money_decimal' => [
        'macro'  => 'money',
        'config' => [
            'store' => [
                'format' => 'decimal',
            ],
        ],
        'expected' => [
            'price' => [
                'type'   => 'decimal',
                'name'   => 'price',
                'total'  => 12,
                'places' => 3,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_currency',
                'length' => 6,
            ],
        ],
    ],
    'money_custom_currency_suffix' => [
        'macro'  => 'money',
        'config' => [
            'currency_column_suffix' => '_my_currency',
        ],
        'expected' => [
            'price' => [
                'type'          => 'bigInteger',
                'name'          => 'price',
                'autoIncrement' => false,
                'unsigned'      => false,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_my_currency',
                'length' => 6,
            ],
        ],
    ],
    'smallMoney' => [
        'macro'    => 'smallMoney',
        'config'   => [],
        'expected' => [
            'price' => [
                'type'          => 'smallInteger',
                'name'          => 'price',
                'autoIncrement' => false,
                'unsigned'      => true,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_currency',
                'length' => 6,
            ],
        ],
    ],
    'smallMoney_decimal' => [
        'macro'  => 'smallMoney',
        'config' => [
            'store' => [
                'format' => 'decimal',
            ],
        ],
        'expected' => [
            'price' => [
                'type'   => 'decimal',
                'name'   => 'price',
                'total'  => 6,
                'places' => 3,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_currency',
                'length' => 6,
            ],
        ],
    ],
    'nullableMoney' => [
        'macro'    => 'nullableMoney',
        'config'   => [],
        'expected' => [
            'price' => [
                'type'          => 'bigInteger',
                'name'          => 'price',
                'autoIncrement' => false,
                'unsigned'      => true,
                'nullable'      => true,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_currency',
                'length' => 6,
            ],
        ],
    ],
    'nullableMoney_decimal' => [
        'macro'  => 'nullableMoney',
        'config' => [
            'store' => [
                'format' => 'decimal',
            ],
        ],
        'expected' => [
            'price' => [
                'type'     => 'decimal',
                'name'     => 'price',
                'total'    => 12,
                'places'   => 3,
                'nullable' => true,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_currency',
                'length' => 6,
            ],
        ],
    ],
    'unsignedMoney' => [
        'macro'    => 'unsignedMoney',
        'config'   => [],
        'expected' => [
            'price' => [
                'type'          => 'bigInteger',
                'name'          => 'price',
                'autoIncrement' => false,
                'unsigned'      => true,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_currency',
                'length' => 6,
            ],
        ],
    ],
    'unsignedMoney_decimal' => [
        'macro'  => 'unsignedMoney',
        'config' => [
            'store' => [
                'format' => 'decimal',
            ],
        ],
        'expected' => [
            'price' => [
                'type'   => 'decimal',
                'name'   => 'price',
                'total'  => 12,
                'places' => 3,
            ],
            'currency' => [
                'type'   => 'string',
                'name'   => 'price_currency',
                'length' => 6,
            ],
        ],
    ],

]);
