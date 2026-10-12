<?php

declare(strict_types=1);

namespace Axyr\Productive\Contracts;

use Axyr\Productive\Config\ProductiveConfig;

/**
 * A bound connector that wants to know each client's configuration, such as the organization a
 * request is sent for. The testing fake uses it to record which organization each request targets.
 */
interface ConfigurableConnector extends ConnectorInterface
{
    public function forConfig(ProductiveConfig $config): ConnectorInterface;
}
