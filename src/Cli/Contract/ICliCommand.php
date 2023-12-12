<?php declare(strict_types=1);

namespace Lkrms\Cli\Contract;

use Lkrms\Contract\HasJsonSchema;

/**
 * A runnable CLI command
 */
interface ICliCommand extends ICliCommandNode, HasJsonSchema
{
    /**
     * Parse the given arguments and run the command
     */
    public function __invoke(string ...$args): int;
}
