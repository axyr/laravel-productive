<?php

declare(strict_types=1);

namespace Axyr\Productive;

use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Data\ModelRegistry;
use Axyr\Productive\Resources\Reports\Reports;
use Axyr\Productive\Resources\TaskResource;
use Axyr\Productive\Resources\TimeEntryResource;
use Closure;

/**
 * Entry point to every Productive resource. Resolve it from the container or use the facade.
 */
final class ProductiveClient
{
    private ?ConnectorInterface $connector = null;

    /**
     * @param  Closure(ProductiveConfig): ConnectorInterface  $connectorFactory
     */
    public function __construct(
        private readonly ProductiveConfig $config,
        private readonly Closure $connectorFactory,
        private readonly ModelRegistry $registry = new ModelRegistry(),
    ) {}

    /**
     * A client for another organization, e.g. when one token has access to several.
     */
    public function withOrganization(string $organizationId): self
    {
        return new self($this->config->withOrganization($organizationId), $this->connectorFactory, $this->registry);
    }

    /**
     * A client that authenticates with another API token.
     */
    public function withToken(string $token): self
    {
        return new self($this->config->withToken($token), $this->connectorFactory, $this->registry);
    }

    public function config(): ProductiveConfig
    {
        return $this->config;
    }

    public function connector(): ConnectorInterface
    {
        return $this->connector ??= ($this->connectorFactory)($this->config);
    }

    public function tasks(): TaskResource
    {
        return new TaskResource($this->connector(), $this->registry);
    }

    public function timeEntries(): TimeEntryResource
    {
        return new TimeEntryResource($this->connector(), $this->registry);
    }

    public function reports(): Reports
    {
        return new Reports($this->connector(), $this->registry);
    }
}
