<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator;

/**
 * What an operation's success responses contain, according to the spec.
 */
final readonly class ResponseShape
{
    /**
     * @param  array<string, mixed>|null  $schema  The schema of the first success response that carries data.
     */
    public function __construct(
        public bool $collection = false,
        public bool $resource = false,
        public bool $noContent = false,
        public bool $emptyOk = false,
        public ?array $schema = null,
    ) {}

    /**
     * @param  array<string, mixed>  $operation
     */
    public static function fromOperation(Spec $spec, array $operation): self
    {
        $shapes = [];

        foreach (Spec::map($operation['responses'] ?? []) as $status => $response) {
            if (str_starts_with((string) $status, '2')) {
                $shapes[] = self::fromResponse($spec, (string) $status, $spec->resolve($response));
            }
        }

        $schemas = array_filter(array_column($shapes, 'schema'));

        return new self(
            collection: in_array(true, array_column($shapes, 'collection'), true),
            resource: in_array(true, array_column($shapes, 'resource'), true),
            noContent: in_array(true, array_column($shapes, 'noContent'), true),
            emptyOk: in_array(true, array_column($shapes, 'emptyOk'), true),
            schema: Spec::map(reset($schemas)) ?: null,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(Spec $spec, string $status, array $response): self
    {
        foreach (Spec::map($response['content'] ?? []) as $content) {
            $schema = $spec->resolve(Spec::map($content)['schema'] ?? []);
            $data = Spec::map(Spec::map($schema['properties'] ?? [])['data'] ?? []);

            if ($data !== []) {
                $list = ($data['type'] ?? null) === 'array';

                return new self(collection: $list, resource: ! $list, schema: $schema);
            }
        }

        return new self(noContent: $status !== '200', emptyOk: $status === '200');
    }
}
