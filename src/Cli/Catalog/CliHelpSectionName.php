<?php declare(strict_types=1);

namespace Lkrms\Cli\Catalog;

use Lkrms\Concept\Dictionary;

/**
 * Section names commonly used in usage information / help messages
 *
 * @see \Lkrms\Cli\CliCommand::getHelpSections()
 */
final class CliHelpSectionName extends Dictionary
{
    public const NAME = 'NAME';
    public const SYNOPSIS = 'SYNOPSIS';
    public const OPTIONS = 'OPTIONS';
    public const DESCRIPTION = 'DESCRIPTION';
    public const EXIT_STATUS = 'EXIT STATUS';
    public const RETURN_VALUE = 'EXIT STATUS';
    public const CONFIGURATION = 'CONFIGURATION';
    public const ENVIRONMENT = 'ENVIRONMENT';
    public const FILES = 'FILES';
    public const EXAMPLES = 'EXAMPLES';
    public const SEE_ALSO = 'SEE ALSO';
    public const HISTORY = 'HISTORY';
    public const BUGS = 'BUGS';
    public const REPORTING_BUGS = 'REPORTING BUGS';
    public const AUTHOR = 'AUTHOR';
    public const COPYRIGHT = 'COPYRIGHT';
}