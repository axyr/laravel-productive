<?php

declare(strict_types=1);

namespace Axyr\Productive;

use Axyr\Productive\Concerns\ProvidesResources;
use Axyr\Productive\Config\ProductiveConfig;
use Axyr\Productive\Contracts\ConnectorInterface;
use Axyr\Productive\Data\ModelRegistry;
use Closure;

/**
 * Entry point to every Productive resource. Resolve it from the container or use the facade.
 */
final class ProductiveClient
{
    use ProvidesResources;

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
}
