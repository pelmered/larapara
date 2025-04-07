<?php

use Filament\Forms;
use Filament\Forms\Components\Field;
use Filament\Infolists;
use Illuminate\Validation\ValidationException;
use Pelmered\LaraPara\Forms\Components\MoneyInput;
use Pelmered\LaraPara\Infolists\Components\MoneyEntry;
use Pelmered\LaraPara\Tables\Columns\MoneyColumn;
use Pelmered\LaraPara\Tests\Support\Components\FormTestComponent;
use Pelmered\LaraPara\Tests\Support\Components\InfolistTestComponent;
use Pelmered\LaraPara\Tests\Support\Components\TableTestComponent;
use Pelmered\LaraPara\Tests\TestCase;

pest()->project()->github('pelmered/larapara');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Unit', 'Components', 'Forms');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Replaces all non-breaking spaces in the given string with regular spaces.
 */
function replaceNonBreakingSpaces(string $string): string
{
    return str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $string);
}

function validationTester(Field $field, $value, ?callable $assertsCallback = null): true|array
{
    try {
        \Filament\Forms\ComponentContainer::make(FormTestComponent::make())
            ->statePath('data')
            ->components([$field])
            ->fill([$field->getName() => $value])
            ->validate();
    } catch (ValidationException $validationException) {
        if ($assertsCallback !== null) {
            $assertsCallback($validationException, $field);
        }

        return [
            'errors' => $validationException->validator->errors()->toArray()[$field->getStatePath()],
            'failed' => $validationException->validator->failed()[$field->getStatePath()],
        ];
    }

    return true;
}

/**
 * @throws Exception
 */
function createTestComponent($type = 'form', $components = [], $fieldName = 'amount', $statePath = 'data'): Forms\ComponentContainer|Infolists\ComponentContainer
{
    if (count($components) <= 0) {
        $components = match ($type) {
            'form'     => [MoneyInput::make($fieldName)],
            'infolist' => [MoneyEntry::make($fieldName)],
            'table'    => [MoneyColumn::make($fieldName)],
            default    => [],
        };
    }

    return (match ($type) {
        'form'     => Forms\ComponentContainer::make(FormTestComponent::make()),
        'infolist' => Infolists\ComponentContainer::make(InfolistTestComponent::make()),
        // 'table' =>  \Filament\Tables\ComponentContainer::make(TableTestComponent::make()),
        default => throw new Exception('Unknown component type: '.$type),
    })
        ->statePath($statePath)
        ->components($components);
}

function createFormTestComponent($components = [], $fill = [], $fieldName = 'amount', $statePath = 'data'): \Filament\Forms\ComponentContainer|\Filament\Infolists\ComponentContainer
{
    $components = createTestComponent('form', $components, $fieldName, $statePath);
    $components->fill($fill);

    return $components;
}

function createInfolistTestComponent($components = [], string $fieldName = 'amount', string $statePath = 'data'): MoneyEntry
{
    return createTestComponent('infolist', $components, $fieldName, $statePath)
        ->getComponent($statePath.'.'.$fieldName);
}
