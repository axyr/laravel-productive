<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\LeakCheck;

it('names what leaked, never the value', function () {
    $output = '{"organization_id": 123456, "owner": "Text 1", "slug": "martijn", "note": "Budget plan"}';

    expect(LeakCheck::leaks($output, [
        'the organization ID' => '123456',
        'the git user name' => 'Martijn van Nieuwenhoven',
        'the API token' => 'tok-abc',
        'nothing' => '',
    ], ['Budget plan', 'Hi']))->toBe(['the organization ID', 'the git user name', 'a replaced value']);
});

it('matches IDs as whole numbers and replaced values as whole words', function () {
    expect(LeakCheck::leaks('{"amount": 1234567, "flag": "enableBudgetViewSharing"}', ['the organization ID' => '12345'], ['Budget']))->toBe([])
        ->and(LeakCheck::leaks('{"a": "x12345"}', ['id' => '12345'], []))->toBe(['id'])
        ->and(LeakCheck::leaks('{"a": "Budget!"}', [], ['Budget', 'Budget!']))->toBe(['a replaced value']);
});

it('matches names and emails in any case and word by word', function () {
    $terms = ['the git user email' => 'info@axyrmedia.nl', 'the git user name' => 'Jan de Vries'];

    expect(LeakCheck::leaks('"INFO@AXYRMEDIA.NL"', $terms, []))->toBe(['the git user email'])
        ->and(LeakCheck::leaks('"vries"', $terms, []))->toBe(['the git user name'])
        ->and(LeakCheck::leaks('"axyrmedia"', $terms, []))->toBe(['the git user email'])
        ->and(LeakCheck::leaks('"de jan"', $terms, []))->toBe([])
        ->and(LeakCheck::leaks('"info"', ['email' => 'info@x.nl'], []))->toBe(['email']);
});

it('checks replaced values from four characters and terms as a whole too', function () {
    expect(LeakCheck::leaks('"abc" "abcd"', [], ['abc']))->toBe([])
        ->and(LeakCheck::leaks('"abc" "abcd"', [], ['abcd']))->toBe(['a replaced value'])
        ->and(LeakCheck::leaks('"tok-abc"', ['the API token' => 'tok-abc'], []))->toBe(['the API token']);
});
