<?php

declare(strict_types=1);

use Axyr\Productive\Generator\Recording\Scrubber;

it('replaces identities and keeps keys, types and codes', function () {
    $scrubber = new Scrubber();

    $scrubbed = $scrubber->scrub([
        'id' => '501',
        'type' => 'people',
        'attributes' => [
            'first_name' => 'jane',
            'title' => 'Head of Design',
            'email' => 'jane@acme.test',
            'contact' => 'jane@acme.test',
            'avatar_url' => 'https://cdn.acme.test/jane.png',
            'website' => 'https://acme.test',
            'email_key' => 'acme',
            'notes' => '',
            'status' => 'active',
            'role' => 'project-manager',
            'currency' => 'EUR',
            'color' => '#FFAA00',
            'amount' => '5000.00',
            'count' => 3,
            'rate' => 1.5,
            'active' => true,
            'nothing' => null,
            'joined_on' => '2026-10-09',
            'updated_at' => '2026-10-09T10:00:00.000+02:00',
            'starts' => '09:30',
            'hash' => 'e66b897d53d8d08aea6fc910765bc1aa',
            'share' => '3f2a9c1e-7b4d-4e8a-9c2f-1a2b3c4d5e6f',
            'tags' => ['VIP client', 'vip'],
            'manager_id' => 502,
            'company_id' => '501',
            'person_ids' => ['501', 503, 'x'],
            'custom_fields' => ['71' => 'Gold', 'note' => 'Secret'],
            'people_custom_fields' => [],
            'list_custom_fields' => ['a'],
            'subsidiary_id' => null,
            'parent_id' => 'Acme',
            9 => 'Numbered',
        ],
        'relationships' => ['manager' => ['data' => ['type' => 'people', 'id' => '502']]],
    ]);

    expect($scrubbed)->toBe([
        'id' => '1001',
        'type' => 'people',
        'attributes' => [
            'first_name' => 'Text 1',
            'title' => 'Text 2',
            'email' => 'person-3@example.test',
            'contact' => 'person-3@example.test',
            'avatar_url' => 'https://example.test/4',
            'website' => 'https://example.test/5',
            'email_key' => 'Text 6',
            'notes' => '',
            'status' => 'active',
            'role' => 'project-manager',
            'currency' => 'EUR',
            'color' => '#FFAA00',
            'amount' => '5000.00',
            'count' => 3,
            'rate' => 1.5,
            'active' => true,
            'nothing' => null,
            'joined_on' => '2026-10-09',
            'updated_at' => '2026-10-09T10:00:00.000+02:00',
            'starts' => '09:30',
            'hash' => 'Text 7',
            'share' => 'Text 8',
            'tags' => ['Text 9', 'vip'],
            'manager_id' => 1002,
            'company_id' => '1001',
            'person_ids' => ['1001', 1003, 'x'],
            'custom_fields' => ['1004' => 'Text 10', 'note' => 'Text 11'],
            'people_custom_fields' => [],
            'list_custom_fields' => ['a'],
            'subsidiary_id' => null,
            'parent_id' => 'Text 12',
            9 => 'Text 13',
        ],
        'relationships' => ['manager' => ['data' => ['type' => 'people', 'id' => '1002']]],
    ])
        ->and($scrubber->replaced())->toBe(['jane', 'Head of Design', 'jane@acme.test', 'https://cdn.acme.test/jane.png', 'https://acme.test', 'acme', 'e66b897d53d8d08aea6fc910765bc1aa', '3f2a9c1e-7b4d-4e8a-9c2f-1a2b3c4d5e6f', 'VIP client', 'Gold', 'Secret', 'Acme', 'Numbered']);
});

it('keeps the same replacement for the same value across documents', function () {
    $scrubber = new Scrubber();

    expect($scrubber->scrub(['id' => '7', 'name' => 'Apollo']))->toBe(['id' => '1001', 'name' => 'Text 1'])
        ->and($scrubber->scrub(['project_id' => 7, 'title' => 'Apollo']))->toBe(['project_id' => 1001, 'title' => 'Text 1']);
});

it('keeps lowercase codes but not names under identity keys or long tokens', function (string $key, string $value, bool $kept) {
    $scrubbed = (new Scrubber())->scrub([$key => $value])[$key];

    expect($scrubbed === $value)->toBe($kept);
})->with([
    ['status', 'open', true],
    ['status', 'in_progress', true],
    ['status', str_repeat('x', 40), true],
    ['status', str_repeat('x', 41), false],
    ['status', 'Open', false],
    ['status', '1abc', false],
    ['status', 'abc123xyz4567', true],
    ['status', 'abc-def-123-456', false],
    ['status', 'abcdef123456', false],
    ['status', '-12.50', true],
    ['status', '2026-10-09 10:00', true],
    ['status', '2026-10-09T10:00:00Z', true],
    ['status', '7:05:30', true],
    ['status', 'EURO', false],
    ['status', '#abc', true],
    ['status', '#abcdefabc', false],
    ['label', 'smith', true],
    ['last_name', 'smith', false],
    ['company_names', 'smith', false],
    ['nickname', 'smith', false],
    ['subdomain', 'smith', false],
    ['body', 'smith', false],
    ['keys', 'smith', true],
    ['username', 'smith', false],
]);

it('reports replaced values as strings, renumbers custom field keys and only treats real emails as emails', function () {
    $scrubber = new Scrubber();

    $scrubbed = $scrubber->scrub([
        'name' => '1234',
        'people_custom_fields' => ['71' => 'Gold', 72 => 'yes', 'label' => 'x'],
        'description' => 'Ping @team',
    ]);

    expect($scrubbed)->toBe([
        'name' => 'Text 1',
        'people_custom_fields' => ['1001' => 'Text 2', '1002' => 'yes', 'label' => 'x'],
        'description' => 'Text 3',
    ])
        ->and($scrubber->replaced())->toBe(['1234', 'Gold', 'Ping @team']);
});
