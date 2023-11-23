<?php declare(strict_types=1);

namespace Salient\Sli\Command\Generate;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliOption;
use Salient\Cli\CliOptionBuilder;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Core\Facade\Console;
use Salient\Core\AbstractEntity;
use Salient\Core\AbstractProvider;
use Salient\Core\Introspector;
use Salient\Core\ProviderContext;
use Salient\PHPDoc\Tag\AbstractTag;
use Salient\PHPDoc\Tag\TemplateTag;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Sli\Command\AbstractCommand;
use Salient\Sli\TokenExtractor;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Package;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Test;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Differ;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

/**
 * Base class for code generation commands
 */
abstract class AbstractGenerateCommand extends AbstractCommand
{
    protected const GENERATE_CLASS = 'class';
    protected const GENERATE_INTERFACE = 'interface';
    protected const VISIBILITY_PUBLIC = 'public';
    protected const VISIBILITY_PROTECTED = 'protected';
    protected const VISIBILITY_PRIVATE = 'private';
    protected const TAB = '    ';

    private const MEMBER_STUB = [
        self::VISIBILITY_PUBLIC => [],
        self::VISIBILITY_PROTECTED => [],
        self::VISIBILITY_PRIVATE => [],
    ];

    /**
     * The path to the generated file
     *
     * Set by {@see AbstractGenerateCommand::handleOutput()} unless output is
     * written to the standard output.
     *
     * May be relative to the current working directory.
     */
    public ?string $OutputFile = null;

    // --

    /**
     * The user-supplied description of the generated entity
     *
     * Generators should apply a default description if `null`.
     */
    protected ?string $Description = null;

    protected bool $ApiTag = false;
    protected bool $Collapse = false;
    protected bool $ToStdout = false;
    protected bool $Check = false;
    protected bool $ReplaceIfExists = false;

    // --

    /**
     * The type of entity to generate
     *
     * @var AbstractGenerateCommand::GENERATE_*
     */
    protected string $OutputType = AbstractGenerateCommand::GENERATE_CLASS;

    /**
     * The unqualified name of the entity to generate
     */
    protected string $OutputClass;

    /**
     * The namespace of the entity to generate (may be empty)
     */
    protected string $OutputNamespace;

    /**
     * The parent of the generated class, or interfaces extended by the
     * generated interface
     *
     * @var string[]
     */
    protected array $Extends = [];

    /**
     * Interfaces implemented by the generated class
     *
     * @var string[]
     */
    protected array $Implements = [];

    /**
     * Traits used by the generated class
     *
     * @var string[]
     */
    protected array $Uses = [];

    /**
     * Modifiers applied to the generated class
     *
     * @var string[]
     */
    protected array $Modifiers = [];

    /**
     * The PHPDoc added before the generated entity
     *
     * {@see AbstractGenerateCommand::generate()} combines each of the following
     * before applying PHPDoc delimiters:
     *
     * - {@see AbstractGenerateCommand::$Description}
     * - {@see AbstractGenerateCommand::$PHPDoc}
     * - {@see AbstractGenerateCommand::$MagicProperties}
     * - {@see AbstractGenerateCommand::$MagicMethods}
     */
    protected string $PHPDoc;

    /**
     * Declared properties of the generated class
     *
     * @var array<AbstractGenerateCommand::VISIBILITY_*,string[]>
     */
    protected array $Properties = self::MEMBER_STUB;

    /**
     * "Magic" properties of the generated class
     *
     * @var array<string,string>
     */
    protected array $MagicProperties = [];

    /**
     * Declared methods of the generated entity
     *
     * @var array<AbstractGenerateCommand::VISIBILITY_*,string[]>
     */
    protected array $Methods = self::MEMBER_STUB;

    /**
     * "Magic" methods of the generated entity
     *
     * @var array<string,string>
     */
    protected array $MagicMethods = [];

    /**
     * Lowercase alias => alias
     *
     * @var array<string,string>
     */
    protected array $AliasIndex = [];

    /**
     * Lowercase alias => qualified name
     *
     * @var array<string,class-string>
     */
    protected array $AliasMap = [];

    /**
     * Lowercase qualified name => alias
     *
     * @var array<class-string,string>
     */
    protected array $ImportMap = [];

    /**
     * Lowercase qualified name => qualified name
     *
     * @var array<class-string,class-string>
     */
    protected array $FqcnMap = [];

    // --

    /** @var ReflectionClass<object> */
    protected ReflectionClass $InputClass;
    /** @var class-string */
    protected string $InputClassName;
    protected ?PHPDoc $InputClassPHPDoc;
    /** @var TemplateTag[] */
    protected array $InputClassTemplates;

    /**
     * "<TTemplate[,...]>"
     */
    protected string $InputClassType;

    /** @var Introspector<object,AbstractProvider,AbstractEntity,ProviderContext<AbstractProvider,AbstractEntity>> */
    protected Introspector $InputIntrospector;
    /** @var array<class-string,string> */
    protected array $InputFiles;

    /**
     * Filename => [ lowercase alias => class name (as imported) ]
     *
     * @var array<string,array<string,class-string>>
     */
    protected array $InputFileUseMaps;

    /**
     * Filename => [ lowercase class name => alias ]
     *
     * @var array<string,array<class-string,string>>
     */
    protected array $InputFileTypeMaps;

    /**
     * @return array<CliOption|CliOptionBuilder>
     */
    protected function getGlobalOptionList(
        string $outputType,
        bool $withDesc = true
    ): array {
        $options = [];
        if ($withDesc) {
            $options[] = CliOption::build()
                ->long('desc')
                ->short('d')
                ->valueName('description')
                ->description("A short description of the $outputType")
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->Description);
        }
        return [
            ...$options,
            CliOption::build()
                ->long('api')
                ->short('a')
                ->description("Add an `@api` tag to the $outputType")
                ->bindTo($this->ApiTag),
            CliOption::build()
                ->long('collapse')
                ->short('C')
                ->description("Collapse one-line declarations in the $outputType")
                ->bindTo($this->Collapse),
            CliOption::build()
                ->long('stdout')
                ->short('s')
                ->description('Write to standard output')
                ->bindTo($this->ToStdout),
            CliOption::build()
                ->long('check')
                ->description('Fail if the output file should be replaced')
                ->bindTo($this->Check),
            CliOption::build()
                ->long('force')
                ->short('f')
                ->description('Overwrite the output file if it already exists')
                ->bindTo($this->ReplaceIfExists),
        ];
    }

    protected function startRun(): void
    {
        $this->OutputFile = null;
        unset($this->OutputClass);
        unset($this->OutputNamespace);
        unset($this->PHPDoc);
        $this->Extends = [];
        $this->Implements = [];
        $this->Uses = [];
        $this->Modifiers = [];
        $this->Properties = self::MEMBER_STUB;
        $this->Methods = self::MEMBER_STUB;
        $this->AliasMap = [];
        $this->ImportMap = [];

        $this->clearInputClass();
    }

    /**
     * @param class-string $fqcn
     */
    protected function assertClassExists(string $fqcn): void
    {
        if (class_exists($fqcn) || interface_exists($fqcn)) {
            return;
        }
        throw new CliInvalidArgumentsException(sprintf('class not found: %s', $fqcn));
    }

    /**
     * @param class-string $fqcn
     */
    protected function assertClassIsInstantiable(string $fqcn): void
    {
        try {
            $class = new ReflectionClass($fqcn);
            if (!$class->isInstantiable()) {
                throw new CliInvalidArgumentsException(sprintf('not an instantiable class: %s', $fqcn));
            }
        } catch (ReflectionException $ex) {
            throw new CliInvalidArgumentsException(sprintf('class not found: %s', $fqcn));
        }
    }

    /**
     * @param class-string $fqcn
     */
    protected function loadInputClass(string $fqcn): void
    {
        $this->InputClass = new ReflectionClass($fqcn);
        $this->InputClassName = $this->InputClass->getName();
        $this->InputClassPHPDoc = PHPDoc::fromDocBlocks(PHPDocUtil::getAllClassDocComments($this->InputClass));
        $this->InputClassTemplates = $this->InputClassPHPDoc
            ? $this->InputClassPHPDoc->getTemplates()
            : [];
        $this->InputClassType = $this->InputClassTemplates
            ? '<' . implode(',', array_keys($this->InputClassTemplates)) . '>'
            : '';
        $this->InputIntrospector = Introspector::get($fqcn);

        $this->InputFiles = [];
        $files = [];

        $class = $this->InputClass;
        do {
            $file = $class->getFileName();
            if ($file !== false) {
                $this->InputFiles[$class->getName()] = $file;
                $files[$file] = true;
            }
        } while ($class = $class->getParentClass());

        foreach ($this->InputClass->getInterfaces() as $interface) {
            $file = $interface->getFileName();
            if ($file) {
                $this->InputFiles[$interface->getName()] = $file;
                $files[$file] = true;
            }
        }

        foreach (array_keys($files) as $file) {
            $extractor = new TokenExtractor($file);
            $useMap = $extractor->getUseMap();
            $this->InputFileUseMaps[$file] = array_change_key_case($useMap);
            $this->InputFileTypeMaps[$file] = array_change_key_case(array_flip($useMap));
        }
    }

    protected function clearInputClass(): void
    {
        unset($this->InputClass);
        unset($this->InputClassName);
        unset($this->InputClassPHPDoc);
        unset($this->InputClassTemplates);
        unset($this->InputClassType);
        unset($this->InputIntrospector);
        unset($this->InputFiles);
        unset($this->InputFileUseMaps);
        unset($this->InputFileTypeMaps);
    }

    protected function getClassPrefix(): string
    {
        return $this->OutputNamespace === '' ? '' : '\\';
    }

    /**
     * @param class-string $fqcn
     * @return class-string
     */
    protected function applyClassPrefix(string $fqcn): string
    {
        /** @var class-string */
        return ($this->OutputNamespace === '' ? '' : '\\') . $fqcn;
    }

    protected function getOutputFqcn(): string
    {
        return Arr::implode('\\', [$this->OutputNamespace, $this->OutputClass]);
    }

    /**
     * Resolve PHPDoc templates to concrete types if possible
     *
     * @param array<string,TemplateTag> $templates
     * @param array<string,TemplateTag> $inputClassTemplates
     */
    protected function resolveTemplates(string $type, array $templates, ?TemplateTag &$template = null, array &$inputClassTemplates = []): string
    {
        $seen = [];
        while ($tag = $templates[$type] ?? null) {
            $template = $tag;
            // Don't resolve templates that will appear in the output
            if ($tag->getClass() === $this->InputClassName
                    && $tag->getMember() === null
                    && ($_template = $this->InputClassTemplates[$type] ?? null)) {
                $inputClassTemplates[$type] = $_template;
                return $type;
            }
            // Prevent recursion
            $tagType = $tag->getType();
            if ($tagType === null || ($seen[$tagType] ?? null)) {
                break;
            }
            $seen[$tagType] = true;
            $type = $tagType;
        }
        return $type;
    }

    /**
     * Resolve a PHPDoc type to a code-safe identifier where templates and PHP
     * types are resolved, using aliases from declaring classes if possible
     *
     * @param AbstractTag|string $type
     * @param array<string,TemplateTag> $templates
     * @param array<string,TemplateTag> $inputClassTemplates
     * @param-out array<string,TemplateTag> $inputClassTemplates
     */
    protected function getPHPDocTypeAlias(
        $type,
        array $templates,
        string $namespace,
        ?string $filename = null,
        array &$inputClassTemplates = []
    ): string {
        $subject = $type instanceof AbstractTag
            ? $type->getType() ?? ''
            : $type;

        return PHPDoc::normaliseType(Regex::replaceCallback(
            '/(?<!\$)([a-z_]+(-[a-z0-9_]+)+|(?=\\\\?\b)' . Regex::PHP_TYPE . ')\b/i',
            function ($match) use (
                $type,
                $templates,
                $namespace,
                $filename,
                &$inputClassTemplates,
                $subject
            ) {
                $t = $this->resolveTemplates($match[0][0], $templates, $template, $inputClassTemplates);
                $type = $template ?: $type;
                if ($type instanceof AbstractTag && ($class = $type->getClass()) !== null) {
                    $class = new ReflectionClass($class);
                    $namespace = $class->getNamespaceName();
                    $filename = $class->getFileName();
                    if ($filename === false) {
                        $filename = null;
                    }
                }
                // Recurse if template expansion occurred
                if ($t !== $match[0][0]) {
                    return $this->getPHPDocTypeAlias($t, $templates, $namespace, $filename);
                }
                // Leave reserved words and PHPDoc types (e.g. `class-string`)
                // alone
                if (Test::isBuiltinType($t) || strpos($t, '-') !== false) {
                    return $t;
                }
                // Leave `min` and `max` (lowercase) alone if they appear
                // between angle brackets after `int` (not case sensitive)
                if ($t === 'min' || $t === 'max') {
                    // - before: `'array < int < 1, max > >'`
                    // - after: `['array', '<', 'int', '<', '1']`
                    /** @disregard P1006 */
                    $before = substr($subject, 0, $match[0][1]);
                    $before = Regex::split('/(?=(?<![-a-z0-9$\\\\_])int\s*<)|(?=<)|(?<=<)|,/i', $before);
                    $before = Arr::trim($before);
                    while ($before) {
                        $last = array_pop($before);
                        if ($last === 'min' || $last === 'max' || Test::isInteger($last)) {
                            continue;
                        }
                        if ($last === '<' && $before && Str::lower(array_pop($before)) === 'int') {
                            return $t;
                        }
                        break;
                    }
                }
                // Don't waste time trying to find a FQCN in $InputFileUseMaps
                if (($t[0] ?? null) === '\\') {
                    return $this->getTypeAlias($t);
                }
                return $this->getTypeAlias(
                    $this->InputFileUseMaps[$filename][Str::lower($t)]
                        ?? '\\' . $namespace . '\\' . $t,
                    $filename
                );
            },
            $subject,
            -1,
            $count,
            \PREG_OFFSET_CAPTURE,
        ));
    }

    /**
     * Convert a built-in or user-defined type to a code-safe identifier, using
     * the same alias as the declaring class if possible
     *
     * Use this method to prepare an arbitrary type for inclusion in a method
     * declaration or PHPDoc tag. If a type is known to be a FQCN, bypass
     * {@see AbstractGenerateCommand::getTypeAlias()} and call
     * {@see AbstractGenerateCommand::getFqcnAlias()} directly instead. Types
     * that originate from a PHPDoc should be passed to
     * {@see AbstractGenerateCommand::getPhpDocTypeAlias()}.
     *
     * @template TReturnFqcn of bool
     *
     * @param string $type Either a built-in type (e.g. `bool`) or a FQCN.
     * @param string|null $filename File where `$type` is declared (if
     * applicable).
     * @param TReturnFqcn $returnFqcn If `false`, return `null` instead of
     * `$type` if the alias has already been claimed.
     * @return (TReturnFqcn is true ? string : string|null)
     */
    protected function getTypeAlias(
        string $type,
        ?string $filename = null,
        bool $returnFqcn = true
    ): ?string {
        $_type = $type;
        $type = ltrim($type, '\\');
        $lower = Str::lower($type);
        if (
            $filename !== null
            && ($alias = $this->InputFileTypeMaps[$filename][$lower] ?? null) !== null
        ) {
            /** @var class-string $type */
            return $this->getFqcnAlias($type, $alias, $returnFqcn);
        }
        if (Test::isBuiltinType($type)) {
            return $returnFqcn ? $lower : null;
        }
        // If it looks like a constant, assume it is one
        if ($lower !== $type && Str::upper($type) === $type && strpos($type, '\\') === false) {
            return $returnFqcn ? $_type : null;
        }
        /** @var class-string $type */
        return $this->getFqcnAlias($type, null, $returnFqcn);
    }

    /**
     * Create an alias for a namespaced name and return a code-safe identifier
     *
     * If an alias for `$fqcn` has already been assigned, the existing alias
     * will be returned. Similarly, if `$alias` has already been claimed, the
     * fully-qualified name will be returned.
     *
     * Otherwise, `use $fqcn[ as $alias];` will be queued for output and
     * `$alias` will be returned.
     *
     * @template TReturnFqcn of bool
     *
     * @param class-string $fqcn
     * @param string|null $alias If `null`, the basename of `$fqcn` will be
     * used.
     * @param TReturnFqcn $returnFqcn If `false`, return `null` instead of the
     * FQCN if `$alias` has already been claimed.
     * @return (TReturnFqcn is true ? string : string|null)
     */
    protected function getFqcnAlias(
        string $fqcn,
        ?string $alias = null,
        bool $returnFqcn = true
    ): ?string {
        $fqcn = ltrim($fqcn, '\\');
        /** @var class-string */
        $_fqcn = Str::lower($fqcn);

        // If $fqcn has already been imported, use its alias
        if (isset($this->ImportMap[$_fqcn])) {
            return $this->ImportMap[$_fqcn];
        }

        $alias ??= Get::basename($fqcn);
        $_alias = Str::lower($alias);

        // Normalise $alias to the first capitalisation seen
        $alias = $this->AliasIndex[$_alias] ?? $alias;

        // Use $alias if it already maps to $fqcn
        $aliasFqcn = $this->AliasMap[$_alias] ?? null;
        if ($aliasFqcn !== null && !strcasecmp($aliasFqcn, $fqcn)) {
            return $alias;
        }

        // Use the canonical basename of the generated class
        if (!strcasecmp($fqcn, $this->getOutputFqcn())) {
            return $this->OutputClass;
        }

        // Don't allow a conflict with the name of the generated class or an
        // existing alias
        if (
            !strcasecmp($alias, $this->OutputClass)
            || isset($this->AliasMap[$_alias])
        ) {
            $this->FqcnMap[$_fqcn] ??= $this->applyClassPrefix($fqcn);

            return $returnFqcn
                ? $this->FqcnMap[$_fqcn]
                : null;
        }

        $this->AliasIndex[$_alias] = $alias;
        $this->AliasMap[$_alias] = $fqcn;

        // Use $alias without importing $fqcn if:
        // - $fqcn is in the same namespace as the generated class; and
        // - the basename of $fqcn is the same as $alias
        if (!strcasecmp($fqcn, Arr::implode('\\', [$this->OutputNamespace, $alias]))) {
            return $alias;
        }

        // Otherwise, import $fqcn
        $this->ImportMap[$_fqcn] = $alias;

        return $alias;
    }

    /**
     * Get the generated file's alias/import/FQCN maps
     *
     * @return array{array<string,string>,array<string,class-string>,array<class-string,string>,array<class-string,class-string>}
     */
    protected function getMaps(): array
    {
        return [
            $this->AliasIndex,
            $this->AliasMap,
            $this->ImportMap,
            $this->FqcnMap,
        ];
    }

    /**
     * Restore the generated file's alias/import/FQCN maps
     *
     * @param array{array<string,string>,array<string,class-string>,array<class-string,string>,array<class-string,class-string>} $maps
     */
    protected function setMaps(array $maps): void
    {
        [
            $this->AliasIndex,
            $this->AliasMap,
            $this->ImportMap,
            $this->FqcnMap
        ] = $maps;
    }

    /**
     * @param string[] $innerBlocks
     */
    protected function generate(array $innerBlocks = []): string
    {
        $line = \PHP_EOL;
        $blank = \PHP_EOL . \PHP_EOL;

        $blocks[] = '<?php declare(strict_types=1);';

        if ($this->OutputNamespace !== '') {
            $blocks[] = sprintf('namespace %s;', $this->OutputNamespace);
        }

        if ($this->ImportMap) {
            $blocks[] = implode($line, $this->generateImports());
        }

        $phpDoc = Arr::implode($blank, [
            $this->Description ?? '',
            $this->PHPDoc ?? '',
            implode($line, $this->MagicProperties),
            implode($line, $this->MagicMethods),
            $this->ApiTag ? '@api' : '',
            '@generated',
        ]);

        $lines =
            $phpDoc === ''
                ? []
                : $this->generatePHPDocBlock($phpDoc);

        $lines[] = Arr::implode(' ', [
            ...$this->Modifiers,
            $this->OutputType,
            $this->OutputClass,
            $this->Extends ? 'extends' : '',
            implode(', ', $this->Extends),
            $this->Implements ? 'implements' : '',
            implode(', ', $this->Implements),
        ]);

        $members = [];

        if ($this->Uses) {
            $imports = [];
            foreach ($this->Uses as $trait) {
                $imports[] = sprintf('use %s;', $trait);
            }
            $members[] = implode($line, $imports);
        }

        foreach ([$this->Properties, $this->Methods] as $memberBlocks) {
            foreach ($memberBlocks as $memberBlocks) {
                array_push($members, ...$memberBlocks);
            }
        }

        $innerBlocks = array_merge($members, $innerBlocks);

        if ($innerBlocks) {
            $lines[] = '{';
            $lines[] = implode($blank, $this->indent($innerBlocks));
            $lines[] = '}';
        } else {
            $lines[array_key_last($lines)] .= ' {}';
        }

        $blocks[] = implode($line, $lines);

        return implode($blank, $blocks);
    }

    /**
     * Generate a list of `use $fqcn[ as $alias];` statements
     *
     * @return string[]
     *
     * @see AbstractGenerateCommand::getFqcnAlias()
     */
    protected function generateImports(): array
    {
        foreach ($this->getImportMap() as $import => $alias) {
            $imports[] = !strcasecmp($alias, Get::basename($import))
                ? sprintf('use %s;', $import)
                : sprintf('use %s as %s;', $import, $alias);
        }

        return $imports ?? [];
    }

    /**
     * Get an array that maps imports to aliases
     *
     * @return array<class-string,string>
     */
    protected function getImportMap(bool $sort = true): array
    {
        if (!$this->ImportMap) {
            return [];
        }

        foreach ($this->ImportMap as $alias) {
            $import = $this->AliasMap[Str::lower($alias)];
            $map[$import] = $alias;
        }

        if (!$sort) {
            return $map;
        }

        // Sort by FQCN, depth-first
        uksort(
            $map,
            fn(string $a, string $b): int =>
                $this->getSortableFqcn($a) <=> $this->getSortableFqcn($b)
        );

        return $map;
    }

    /**
     * Get an array that maps aliases to qualified names
     *
     * @return array<string,class-string>
     */
    protected function getAliasMap(): array
    {
        if (!$this->AliasMap) {
            return [];
        }

        foreach ($this->AliasMap as $_alias => $fqcn) {
            $alias = $this->AliasIndex[$_alias];
            $map[$alias] = $fqcn;
        }

        return $map;
    }

    /**
     * Get the qualified name of an alias if known
     *
     * @return class-string
     */
    protected function expandAlias(string $alias, ?string $filename = null): string
    {
        $_alias = Str::lower($alias);

        if ($filename !== null) {
            $fqcn = $this->InputFileUseMaps[$filename][$_alias] ?? null;
        }

        $fqcn ??= $this->AliasMap[$_alias] ?? null;

        if ($fqcn !== null) {
            return $fqcn;
        }

        if (strpos($alias, '\\') !== false || $this->OutputNamespace === '') {
            /** @var class-string $alias */
            return $alias;
        }

        /** @var class-string */
        $fqcn = $this->OutputNamespace . '\\' . $alias;

        return $fqcn;
    }

    /**
     * Generate a `protected static function` that returns a fixed value
     *
     * @param string[]|string $phpDoc
     * @return string[]
     */
    protected function generateGetter(
        string $name,
        string $valueCode,
        $phpDoc = '@inheritDoc',
        ?string $returnType = 'string',
        string $visibility = AbstractGenerateCommand::VISIBILITY_PUBLIC
    ): array {
        return [
            ...$this->generatePHPDocBlock($phpDoc),
            sprintf(
                '%s static function %s()%s%s',
                $visibility,
                $name,
                $returnType === null ? '' : ': ',
                $returnType,
            ),
            '{',
            $this->indent(sprintf('return %s;', $valueCode)),
            '}'
        ];
    }

    /**
     * Add a "magic" method to the generated entity
     *
     * @param array<ReflectionParameter|string> $params
     * @param ReflectionType|string $returnType
     */
    protected function addMagicMethod(
        string $name,
        array $params = [],
        $returnType = null,
        ?string $description = null,
        ?PHPDoc $phpDoc = null,
        bool $static = false
    ): void {
        $callback = function (string $name): ?string {
            /** @var class-string $name */
            return $this->getFqcnAlias($name, null, false);
        };

        foreach ($params as &$param) {
            if ($param instanceof ReflectionParameter) {
                if ($phpDoc && ($tag = $phpDoc->Params[$param->getName()] ?? null)) {
                    $type = $this->getPHPDocTypeFromTag($tag, $phpDoc->Templates, $param);
                } else {
                    $type = null;
                }
                $param = Reflect::getParameterDeclaration(
                    $param,
                    $this->getClassPrefix(),
                    $callback,
                    $type,
                    null,
                    true,
                );
            }
        }
        $params = implode(', ', $params);

        if ($returnType instanceof ReflectionType) {
            $returnType = Reflect::getTypeDeclaration(
                $returnType,
                $this->getClassPrefix(),
                $callback,
            );
        }

        $parts = ['@method'];
        if ($static) {
            $parts[] = 'static';
        }
        $parts[] = $returnType === null ? 'mixed' : $returnType;
        $parts[] = "{$name}({$params})";
        if ($description !== null) {
            $description = trim($description);
            if ($description !== '') {
                $parts[] = $description;
            }
        }
        $this->MagicMethods[$name] = implode(' ', $parts);
    }

    /**
     * @param array<string,TemplateTag> $templates
     * @param ReflectionClass<object>|ReflectionFunctionAbstract|ReflectionParameter|ReflectionProperty $reflector
     */
    protected function getPHPDocTypeFromTag(
        AbstractTag $tag,
        array $templates,
        $reflector
    ): ?string {
        $type = $tag->getType();
        if ($type === null) {
            return null;
        }
        $declaring = $reflector instanceof ReflectionProperty
            ? $reflector->getDeclaringClass()
            : ($reflector instanceof ReflectionParameter
                ? $reflector->getDeclaringFunction()
                : $reflector);
        $namespace = $declaring->getNamespaceName();
        $filename = $declaring->getFileName();
        if ($filename === false) {
            $filename = null;
        }
        return $this->getPHPDocTypeAlias(
            $type,
            $templates,
            $namespace,
            $filename,
        );
    }

    /**
     * Add a method to the generated entity
     *
     * @param string[]|string|null $code
     * @param array<ReflectionParameter|string> $params
     * @param ReflectionType|string $returnType
     * @param string[]|string $phpDoc
     * @param AbstractGenerateCommand::VISIBILITY_* $visibility
     */
    protected function addMethod(
        string $name,
        $code,
        array $params = [],
        $returnType = null,
        $phpDoc = '',
        bool $static = false,
        string $visibility = AbstractGenerateCommand::VISIBILITY_PUBLIC
    ): void {
        $this->Methods[$visibility][] =
            implode(\PHP_EOL, $this->generateMethod(
                $name,
                $code,
                $params,
                $returnType,
                $phpDoc,
                $static,
                $visibility,
            ));
    }

    /**
     * Generate a method
     *
     * @param string[]|string|null $code
     * @param array<ReflectionParameter|string> $params
     * @param ReflectionType|string $returnType
     * @param string[]|string $phpDoc
     * @param AbstractGenerateCommand::VISIBILITY_* $visibility
     * @return string[]
     */
    protected function generateMethod(
        string $name,
        $code,
        array $params = [],
        $returnType = null,
        $phpDoc = '',
        bool $static = true,
        string $visibility = AbstractGenerateCommand::VISIBILITY_PUBLIC
    ): array {
        $callback = function (string $name): ?string {
            /** @var class-string $name */
            return $this->getFqcnAlias($name, null, false);
        };

        foreach ($params as &$param) {
            if ($param instanceof ReflectionParameter) {
                $param = PHPDocUtil::getParameterDeclaration(
                    $param,
                    $this->getClassPrefix(),
                    $callback,
                );
            }
        }
        $params = implode(', ', $params);

        if ($returnType instanceof ReflectionType) {
            $returnType = PHPDocUtil::getTypeDeclaration(
                $returnType,
                $this->getClassPrefix(),
                $callback,
            );
        }

        $modifiers = [];
        $modifiers[] = $visibility;
        if ($static) {
            $modifiers[] = 'static';
        }
        $modifiers = implode(' ', $modifiers);

        $declaration = $returnType === null
            ? sprintf('%s function %s(%s)', $modifiers, $name, $params)
            : sprintf('%s function %s(%s): %s', $modifiers, $name, $params, $returnType);

        $method = $this->generatePHPDocBlock($phpDoc);

        if ($this->OutputType === AbstractGenerateCommand::GENERATE_INTERFACE) {
            $method[] = "$declaration;";
            return $method;
        }

        $code = $this->indent((array) $code);

        if (!$code) {
            $method[] = "$declaration {}";
            return $method;
        }

        $method[] = $declaration;
        $method[] = '{';
        array_push($method, ...$code);
        $method[] = '}';

        return $method;
    }

    /**
     * @param string[]|string $lines
     */
    protected function handleOutput($lines): void
    {
        $output = is_array($lines)
            ? implode(\PHP_EOL, $lines) . \PHP_EOL
            : rtrim($lines) . \PHP_EOL;

        $empty = \PHP_EOL . '{' . \PHP_EOL . '}' . \PHP_EOL;
        if (substr($output, -strlen($empty)) === $empty) {
            $output = substr($output, 0, -strlen($empty)) . ' {}' . \PHP_EOL;
        }

        $verb = 'Creating';

        if ($this->ToStdout) {
            $file = 'php://stdout';
            $verb = null;
        } else {
            $file = sprintf('%s.php', $this->OutputClass);
            $dir = Package::getNamespacePath($this->OutputNamespace);

            if ($dir !== null) {
                if (!$this->Check) {
                    File::createDir($dir);
                }
                $file = $dir . '/' . $file;
            }

            $this->OutputFile = $file;

            if (file_exists($file)) {
                $input = File::getContents($file);
                if ($input === $output) {
                    Console::log('Nothing to do:', $file);
                    return;
                }
                if ($this->Check || !$this->ReplaceIfExists) {
                    if (class_exists(Differ::class)) {
                        $relative = File::getRelativePath($file, Package::path(), $file);
                        $formatter = Console::getStdoutTarget()->getFormatter();
                        $diff = (new Differ(new StrictUnifiedDiffOutputBuilder([
                            'fromFile' => "a/$relative",
                            'toFile' => "b/$relative",
                        ])))->diff($input, $output);
                        print $formatter->formatDiff($diff);
                    } else {
                        Console::log('Install sebastian/diff to show changes');
                    }
                    if (!$this->Check) {
                        Console::info('Out of date:', $file);
                        return;
                    }
                    Console::info('Would replace', $file);
                    Console::count(Level::ERROR);
                    $this->setExitStatus(1);
                    return;
                }
                $verb = 'Replacing';
            } elseif ($this->Check) {
                Console::info('Would create', $file);
                Console::count(Level::ERROR);
                $this->setExitStatus(1);
                return;
            }
        }

        if ($verb) {
            Console::info($verb, $file);
        }

        File::writeContents($file, $output);
    }

    /**
     * Convert a value to code where arrays are broken over multiple lines
     *
     * @param mixed $value
     */
    protected function code($value): string
    {
        return Get::code(
            $value,
            ',' . \PHP_EOL,
            ' => ',
            null,
            self::TAB,
            array_merge(array_keys($this->getAliasMap()), $this->FqcnMap),
        );
    }

    /**
     * Add one or more levels of indentation to a line of code or an array
     * thereof
     *
     * @template T of string[]|string
     *
     * @param T $lines
     * @return T
     */
    protected function indent($lines, int $levels = 1)
    {
        if (is_array($lines)) {
            foreach ($lines as &$line) {
                $line = $this->indent($line, $levels);
            }
            return $lines;
        }

        $indent = str_repeat(self::TAB, $levels);
        return $indent . str_replace(\PHP_EOL, \PHP_EOL . $indent, $lines);
    }

    /**
     * Get one or more levels of indentation
     */
    protected function tab(int $levels = 1): string
    {
        return str_repeat(self::TAB, $levels);
    }

    /**
     * @param string[]|string $phpDoc
     * @return string[]
     */
    private function generatePHPDocBlock($phpDoc): array
    {
        if ($phpDoc === []
                || (is_string($phpDoc) && trim($phpDoc) === '')) {
            return [];
        }

        // Implode and explode to allow for multi-line elements and unnecessary
        // whitespace
        $phpDoc = explode(\PHP_EOL, trim(implode(\PHP_EOL, (array) $phpDoc)));

        $block[] = '/**';
        foreach ($phpDoc as $line) {
            if ($line === '') {
                $block[] = ' *';
                continue;
            }
            $block[] = ' * ' . $line;
        }
        $block[] = ' */';

        return $block;
    }

    /**
     * Normalise a FQCN for depth-first sorting
     *
     * Before:
     *
     * ```
     * A
     * A\B\C
     * A\B
     * ```
     *
     * After:
     *
     * ```
     * 1A
     * 0A \ 0B \ 1C
     * 0A \ 1B
     * ```
     */
    private function getSortableFqcn(string $import): string
    {
        $names = explode('\\', $import);
        $import = '';
        $prefix = 0;
        do {
            $name = array_shift($names);
            if (!$names) {
                $prefix = 1;
            }
            $import .= ($import === '' ? '' : ' \ ') . "$prefix$name";
        } while (!$prefix);

        return $import;
    }
}
