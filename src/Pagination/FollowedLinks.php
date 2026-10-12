<?php

declare(strict_types=1);

namespace Axyr\Productive\Pagination;

use Axyr\Productive\Exceptions\InvalidResponseException;

/**
 * The next-page links one iteration has requested, so a repeated cursor cannot loop forever.
 */
final class FollowedLinks
{
    /** @var list<string> */
    private array $links = [];

    public function __construct(
        private readonly string $operation,
    ) {}

    public function follow(string $link): void
    {
        if (in_array($link, $this->links, true)) {
            throw new InvalidResponseException(sprintf('Productive returned the next page link "%s" twice [%s]; stopping instead of looping.', $link, $this->operation));
        }

        $this->links[] = $link;
    }
}
