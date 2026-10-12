<?php

declare(strict_types=1);

use Tests\Support\SpecExamples;

/*
 * The response kind and organization header of every operation, recomputed from the raw spec with
 * rules written here, not taken from the generator, and compared with the API description.
 */

/**
 * @return array<string, mixed>
 */
function specNode(mixed $node): array
{
    $node = is_array($node) ? $node : [];

    while (isset($node['$ref'])) {
        $target = SpecExamples::spec();

        foreach (explode('/', substr($node['$ref'], 2)) as $segment) {
            $target = $target[str_replace(['~1', '~0'], ['/', '~'], $segment)];
        }

        $node = $target;
    }

    return $node;
}

/**
 * The response kind by the spec's own 2xx responses: no content, a file, a list or one resource,
 * or one resource that may also come back empty.
 *
 * @param  array<string, mixed>  $operation
 */
function specResponseKind(array $operation): string
{
    $kinds = [];

    foreach ($operation['responses'] ?? [] as $status => $response) {
        if (! str_starts_with((string) $status, '2')) {
            continue;
        }

        $content = specNode($response)['content'] ?? [];
        $json = array_filter(array_keys($content), fn(string $type): bool => str_contains($type, 'json'));
        $schema = $json === [] ? [] : specNode($content[reset($json)]['schema'] ?? []);
        $data = specNode($schema['properties']['data'] ?? []);

        $kinds[] = match (true) {
            (string) $status === '204', $content === [], $json !== [] && $schema === [] => 'no_content',
            $json === [] => 'raw',
            ($data['type'] ?? null) === 'array' => 'collection',
            $data === [] => 'raw',
            default => 'resource',
        };
    }

    $kinds = array_values(array_unique($kinds));
    sort($kinds);

    return match ($kinds) {
        ['no_content', 'resource'] => 'optional_resource',
        default => $kinds[0] ?? 'no_content',
    };
}

it('describes every operation with the response kind and organization header the spec gives', function () {
    $api = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/generator/api.json'), true, flags: JSON_THROW_ON_ERROR);
    $differences = [];

    foreach (SpecExamples::spec()['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            if (! is_array($operation) || ! isset($operation['operationId'])) {
                continue;
            }

            $organization = in_array('#/components/parameters/header_organization', array_column($operation['parameters'] ?? [], '$ref'), true);
            $spec[$operation['operationId']] = [specResponseKind($operation), $organization];
        }
    }

    foreach ($api['resources'] as $resource) {
        foreach ($resource['operations'] as $operation) {
            [$response, $organization] = $spec[$operation['operation_id'] ?? ''] ?? [null, null];

            if ($response !== null && ($response !== $operation['response'] || $organization !== $operation['requires_organization'])) {
                $differences[] = sprintf('%s: spec %s/%s, described %s/%s', $operation['key'], $response, $organization ? 'org' : 'no org', $operation['response'], $operation['requires_organization'] ? 'org' : 'no org');
            }
        }
    }

    sort($differences);

    expect($differences)->toBe([
        // Bulk actions send IDs and return nothing, whatever the spec documents.
        'expenses.bulk_approve: spec collection/org, described no_content/org',
        // Bulk writes return the written resources; the spec documents the single-resource response.
        'line_items.create_bulk: spec resource/org, described collection/org',
        // A PDF download, though the spec documents a JSON resource.
        'proposals.signed_pdf: spec resource/org, described raw/org',
        // The spec documents no body; the endpoint answers with an empty 200 that is read as is.
        'public.artifacts.attachments_auth: spec no_content/no org, described raw/no org',
        // Machine credentials, returned as the raw response rather than hydrated as a session.
        'sessions.machine: spec optional_resource/org, described raw/org',
        'time_entries.approve_bulk: spec resource/org, described no_content/org',
        'time_entries.create_bulk: spec resource/org, described collection/org',
        'time_entries.unapprove_bulk: spec resource/org, described no_content/org',
        'time_entries.update_bulk: spec resource/org, described collection/org',
    ]);
});
