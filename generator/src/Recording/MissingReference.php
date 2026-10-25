<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Recording;

use RuntimeException;

/**
 * A recipe refers to a record that does not exist, or to a list that could not be read; the step
 * that needs it is skipped with this message.
 */
final class MissingReference extends RuntimeException {}
