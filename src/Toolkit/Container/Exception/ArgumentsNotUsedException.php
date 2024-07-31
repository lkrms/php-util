<?php declare(strict_types=1);

namespace Salient\Container\Exception;

use Salient\Contract\Container\ArgumentsNotUsedExceptionInterface;
use Salient\Core\AbstractException;

/**
 * Thrown when a container cannot pass arguments to a service, e.g. because it
 * resolves to a shared instance
 */
class ArgumentsNotUsedException extends AbstractException implements ArgumentsNotUsedExceptionInterface {}
