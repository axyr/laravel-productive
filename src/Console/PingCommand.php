<?php

declare(strict_types=1);

namespace Axyr\Productive\Console;

use Axyr\Productive\Data\Models\Organization;
use Axyr\Productive\Exceptions\ProductiveException;
use Axyr\Productive\ProductiveClient;
use Illuminate\Console\Command;

/**
 * Checks the configured token and organization with one cheap request.
 */
final class PingCommand extends Command
{
    /** @var string */
    protected $signature = 'productive:ping {--organization= : Check another organization than the configured one}';

    /** @var string */
    protected $description = 'Check that the Productive token and organization work';

    public function handle(ProductiveClient $client): int
    {
        $client = $this->client($client);

        try {
            $found = $client->organizations()->query()->first();
        } catch (ProductiveException $exception) {
            $this->components->error('Productive did not accept the request: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Connected to Productive: %s (organization %s).', self::name($found), $client->config()->organizationId));

        return self::SUCCESS;
    }

    private function client(ProductiveClient $client): ProductiveClient
    {
        $organization = $this->option('organization');

        return is_string($organization) && $organization !== '' ? $client->withOrganization($organization) : $client;
    }

    private static function name(?object $organization): string
    {
        return $organization instanceof Organization && $organization->name !== null ? $organization->name : 'unnamed organization';
    }
}
