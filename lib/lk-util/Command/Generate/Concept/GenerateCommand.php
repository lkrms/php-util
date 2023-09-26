<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate\Concept;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\File;
use Lkrms\Facade\Reflect;
use Lkrms\LkUtil\Command\Concept\Command;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Support\PhpDoc\PhpDoc;
use Lkrms\Support\PhpDoc\PhpDocTag;
use Lkrms\Support\PhpDoc\PhpDocTemplateTag;
use Lkrms\Support\IntrospectionClass;
use Lkrms\Support\Introspector;
use Lkrms\Support\TokenExtractor;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Test;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Differ;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionType;

/**
 * Base class for code generation commands
 */
abstract class GenerateCommand extends Command
{
    protected const VISIBILITY_PUBLIC = 'public';
    protected const VISIBILITY_PROTECTED = 'protected';
    protected const VISIBILITY_PRIVATE = 'private';
    protected const TAB = '    ';

    protected ?string $OutputDescription;
    protected ?bool $ToStdout;
    protected ?bool $ReplaceIfExists;
    protected ?bool $Check;
    protected ?bool $NoMetaTags;

    /**
     * The unqualified name of the entity to generate
     */
    protected string $OutputClass;

    /**
     * The namespace of the entity being generated (may be empty)
     */
    protected string $OutputNamespace;

    /**
     * A PHPDoc for the generated entity (may be empty)
     */
    protected string $ClassPhpDoc;

    /**
     * Lowercase alias => qualified name
     *
     * @var array<string,class-string>
     */
    protected $AliasMap = [];

    /**
     * Lowercase qualified name => alias
     *
     * @var array<class-string,string>
     */
    protected $ImportMap = [];

    /**
     * @var ReflectionClass<object>
     */
    protected ReflectionClass $InputClass;

    /**
     * @var class-string
     */
    protected string $InputClassName;

    protected PhpDoc $InputClassPhpDoc;

    /**
     * @var PhpDocTemplateTag[]
     */
    protected array $InputClassTemplates;

    /**
     * "<TTemplate[,...]>"
     */
    protected string $InputClassType;

    /**
     * @var Introspector<object,IntrospectionClass<object>>
     */
    protected Introspector $InputIntrospector;

    /**
     * @var array<class-string,string>
     */
    protected array $InputFiles;

    /**
     * Filename => [ alias => class name (as imported) ]
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
     * Get mandatory options
     *
     * @return array<CliOption|CliOptionBuilder>
     */
    protected function getOutputOptionList(string $outputType): array
    {
        return [
            CliOption::build()
                ->long('desc')
                ->short('d')
                ->valueName('description')
                ->description("A short description of the $outputType")
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->OutputDescription),
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
            CliOption::build()
                ->long('no-meta')
                ->short('m')
                ->description('Suppress metadata tags')
                ->bindTo($this->NoMetaTags),
        ];
    }

    protected function reset(): void
    {
        unset($this->OutputClass);
        unset($this->OutputNamespace);
        unset($this->ClassPhpDoc);
        $this->AliasMap = [];
        $this->ImportMap = [];

        $this->clearInputClass();
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
        $this->InputClassPhpDoc = PhpDoc::fromDocBlocks(Reflect::getAllClassDocComments($this->InputClass));
        $this->InputClassTemplates = $this->InputClassPhpDoc->Templates;
        $this->InputClassType = $this->InputClassTemplates
            ? '<' . implode(',', array_keys($this->InputClassTemplates)) . '>'
            : '';
        $this->InputIntrospector = Introspector::get($fqcn);

        $this->InputFiles = [];
        $files = [];

        $class = $this->InputClass;
        do {
            $file = $class->getFileName();
            if ($file) {
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
            $this->InputFileUseMaps[$file] = $useMap;
            $this->InputFileTypeMaps[$file] = array_change_key_case(array_flip($useMap));
        }
    }

    protected function clearInputClass(): void
    {
        unset($this->InputClass);
        unset($this->InputClassName);
        unset($this->InputClassPhpDoc);
        unset($this->InputClassTemplates);
        unset($this->InputClassType);
        unset($this->InputIntrospector);
        unset($this->InputFiles);
        unset($this->InputFileUseMaps);
        unset($this->InputFileTypeMaps);
    }

    protected function getClassPrefix(): string
    {
        return $this->OutputNamespace ? '\\' : '';
    }

    /**
     * Resolve PHPDoc templates to concrete types if possible
     *
     * @param array<string,PhpDocTemplateTag> $templates
     * @param array<string,PhpDocTemplateTag> $inputClassTemplates
     */
    protected function resolveTemplates(string $type, array $templates, ?PhpDocTemplateTag &$template = null, array &$inputClassTemplates = []): string
    {
        $seen = [];
        while ($tag = $templates[$type] ?? null) {
            $template = $tag;
            // Don't resolve templates that will appear in the output
            if ($tag->Class === $this->InputClassName && $tag->Member === null &&
                    ($_template = $this->InputClassTemplates[$type] ?? null)) {
                $inputClassTemplates[$type] = $_template;
                return $type;
            }
            // Prevent recursion
            if (!$tag->Type || ($seen[$tag->Type] ?? null)) {
                break;
            }
            $seen[$tag->Type] = true;
            $type = $tag->Type;
        }
        return $type;
    }

    /**
     * Resolve a PHPDoc type to a code-safe identifier where templates and PHP
     * types are resolved, using aliases from declaring classes if possible
     *
     * @param PhpDocTag|string $type
     * @param array<string,PhpDocTemplateTag> $templates
     * @param array<string,PhpDocTemplateTag> $inputClassTemplates
     */
    protected function getPhpDocTypeAlias($type, array $templates, string $namespace, ?string $filename = null, array &$inputClassTemplates = []): string
    {
        return PhpDocTag::normaliseType(preg_replace_callback(
            '/(?<!\$)([a-z_]+(-[a-z0-9_]+)+|(?=\\\\?\b)' . Regex::PHP_TYPE . ')\b/i',
            function ($match) use ($type, $namespace, $templates, $filename, &$inputClassTemplates) {
                $t = $this->resolveTemplates($match[0], $templates, $template, $inputClassTemplates);
                $type = $template ?: $type;
                if ($type instanceof PhpDocTag && $type->Class) {
                    $class = new ReflectionClass($type->Class);
                    $namespace = $class->getNamespaceName();
                    $filename = $class->getFileName();
                }
                // Recurse if template expansion occurred
                if ($t !== $match[0]) {
                    return $this->getPhpDocTypeAlias($t, $templates, $namespace, $filename);
                }
                // Leave reserved words and PHPDoc types (e.g. `class-string`)
                // alone
                if (Test::isPhpReservedWord($t) || strpos($t, '-') !== false) {
                    return $t;
                }
                // Don't waste time trying to find a FQCN in $InputFileUseMaps
                if (($t[0] ?? null) === '\\') {
                    return $this->getTypeAlias($t);
                }
                return $this->getTypeAlias(
                    $this->InputFileUseMaps[$filename][$t]
                        ?? '\\' . $namespace . '\\' . $t,
                    $filename
                );
            },
            $type instanceof PhpDocTag
                ? ($type->Type ?: '')
                : $type
        ));
    }

    /**
     * Convert a built-in or user-defined type to a code-safe identifier, using
     * the same alias as the declaring class if possible
     *
     * @param string $type Either a built-in type (e.g. `bool`) or a FQCN.
     * @param string|null $filename File where `$type` is declared (if
     * applicable).
     * @param bool $returnFqcn If `false`, return `null` instead of `$type` if
     * the alias has already been claimed.
     */
    protected function getTypeAlias(string $type, ?string $filename = null, bool $returnFqcn = true): ?string
    {
        $type = ltrim($type, '\\');
        $lower = strtolower($type);
        if ($filename !== null &&
                ($alias = $this->InputFileTypeMaps[$filename][$lower] ?? null)) {
            return $this->getFqcnAlias($type, $alias, $returnFqcn);
        }
        if (Test::isPhpReservedWord($type)) {
            return $returnFqcn ? $lower : null;
        }
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
     * @param string|null $alias If `null`, the basename of `$fqcn` will be
     * used.
     * @param bool $returnFqcn If `false`, return `null` instead of the FQCN if
     * `$alias` has already been claimed.
     */
    protected function getFqcnAlias(string $fqcn, ?string $alias = null, bool $returnFqcn = true): ?string
    {
        $fqcn = ltrim($fqcn, '\\');
        $_fqcn = strtolower($fqcn);

        // If $fqcn has already been imported, use its alias
        if ($lastAlias = $this->ImportMap[$_fqcn] ?? null) {
            return $lastAlias;
        }

        $alias = is_null($alias) ? Convert::classToBasename($fqcn) : $alias;
        $_alias = strtolower($alias);

        // Use $alias if it already maps to $fqcn
        if (($aliasFqcn = $this->AliasMap[$_alias] ?? null) &&
                !strcasecmp($aliasFqcn, $fqcn)) {
            return $alias;
        }

        // Use the canonical basename of the generated class
        if (!strcasecmp($fqcn, "{$this->OutputNamespace}\\{$this->OutputClass}")) {
            return $this->OutputClass;
        }

        // Don't allow a conflict with the name of the generated class
        if (!strcasecmp($alias, $this->OutputClass) ||
                array_key_exists($_alias, $this->AliasMap)) {
            return $returnFqcn ? $this->getClassPrefix() . $fqcn : null;
        }

        $this->AliasMap[$_alias] = $fqcn;

        // Use $alias without importing $fqcn if:
        // - $fqcn is in the same namespace as the generated class; and
        // - the basename of $fqcn is the same as $alias
        if (!strcasecmp($fqcn, "{$this->OutputNamespace}\\{$alias}")) {
            return $alias;
        }

        // Otherwise, import $fqcn
        $this->ImportMap[$_fqcn] = $alias;

        return $alias;
    }

    /**
     * @param string[] $extends
     * @param string[] $implements
     * @param string[] $modifiers
     */
    protected function generate(
        string $type = 'class',
        array $extends = [],
        array $implements = [],
        array $modifiers = []
    ): string {
        $lines = [
            ...$this->generatePhpDocBlock($this->ClassPhpDoc),
            Convert::sparseToString(' ', [
                ...$modifiers,
                $type,
                $this->OutputClass,
                $extends ? 'extends' : '',
                implode(', ', $extends),
                $implements ? 'implements' : '',
                implode(', ', $implements),
            ]),
            '{',
            '}',
        ];

        $blocks[] = '<?php declare(strict_types=1);';

        if ($this->OutputNamespace) {
            $blocks[] = sprintf('namespace %s;', $this->OutputNamespace);
        }

        if ($this->ImportMap) {
            $blocks[] = implode(PHP_EOL, $this->generateImports());
        }

        $blocks[] = implode(PHP_EOL, $lines);

        return implode(PHP_EOL . PHP_EOL, $blocks);
    }

    /**
     * Generate a list of `use $fqcn[ as $alias];` statements
     *
     * @return string[]
     * @see GenerateCommand::getFqcnAlias()
     */
    protected function generateImports(): array
    {
        $map = [];
        foreach ($this->ImportMap as $alias) {
            $import = $this->AliasMap[strtolower($alias)];
            if (!strcasecmp($alias, Convert::classToBasename($import))) {
                $map[$import] = null;
                continue;
            }
            $map[$import] = $alias;
        }

        // Sort by FQCN, depth-first
        uksort($map, fn($a, $b) => $this->getSortableFqcn($a) <=> $this->getSortableFqcn($b));

        $imports = [];
        foreach ($map as $import => $alias) {
            $imports[] =
                $alias === null
                    ? sprintf('use %s;', $import)
                    : sprintf('use %s as %s;', $import, $alias);
        }

        return $imports;
    }

    /**
     * Generate a `protected static function` that returns a fixed value
     *
     * @param string|string[] $phpDoc
     * @return string[]
     */
    protected function generateGetter(
        string $name,
        string $valueCode,
        $phpDoc = '@internal',
        string $returnType = 'string',
        string $visibility = GenerateCommand::VISIBILITY_PROTECTED
    ): array {
        $lines = [
            ...$this->generatePhpDocBlock($phpDoc),
            sprintf('%s static function %s(): %s', $visibility, $name, $returnType),
            '{',
            sprintf('%sreturn %s;', self::TAB, $valueCode),
            '}'
        ];

        return array_map(fn(string $line) => self::TAB . $line, $lines);
    }

    /**
     * Generate a method
     *
     * @param string[] $code
     * @param array<ReflectionParameter|string> $params
     * @param ReflectionType|string $returnType
     * @return string[]
     */
    protected function generateMethod(
        string $name,
        array $code,
        array $params = [],
        $returnType = null,
        string $phpDoc = '',
        bool $static = true,
        string $visibility = GenerateCommand::VISIBILITY_PUBLIC
    ): array {
        $callback = fn(string $name): ?string => $this->getFqcnAlias($name, null, false);

        foreach ($params as &$param) {
            if ($param instanceof ReflectionParameter) {
                $param = Reflect::getParameterDeclaration($param, $this->getClassPrefix(), $callback);
            }
        }
        $params = implode(', ', $params);

        if ($returnType instanceof ReflectionType) {
            $returnType = Reflect::getTypeDeclaration($returnType, $this->getClassPrefix(), $callback);
        }

        $modifiers = [];
        $modifiers[] = $visibility;
        if ($static) {
            $modifiers[] = 'static';
        }

        $lines = [
            ...$this->generatePhpDocBlock($phpDoc),
            sprintf('%s function %s(%s)' . ($returnType ? ': %s' : ''), implode(' ', $modifiers), $name, $params, $returnType),
            '{',
            ...array_map(fn(string $line) => self::TAB . $line, $code),
            '}'
        ];

        return array_map(fn(string $line) => self::TAB . $line, $lines);
    }

    /**
     * @param string[]|string $lines
     */
    protected function handleOutput($lines): void
    {
        $output = is_array($lines)
            ? implode(PHP_EOL, $lines) . PHP_EOL
            : rtrim($lines) . PHP_EOL;

        $verb = 'Creating';

        if ($this->ToStdout) {
            $file = 'php://stdout';
            $verb = null;
        } else {
            $file = sprintf('%s.php', $this->OutputClass);
            $dir = Composer::getNamespacePath($this->OutputNamespace);

            if ($dir !== null) {
                if (!$this->Check) {
                    File::maybeCreateDirectory($dir);
                }
                $file = $dir . '/' . $file;
            }

            if (file_exists($file)) {
                $input = file_get_contents($file);
                if ($input === $output) {
                    Console::log('Nothing to do:', $file);
                    return;
                }
                if ($this->Check) {
                    Console::info('Would replace', $file);
                    Console::count(Level::ERROR);
                    $this->setExitStatus(1);
                    return;
                }
                if (!$this->ReplaceIfExists) {
                    $basePath = Composer::getRootPackagePath();
                    $relative = File::realpath($file);
                    if (strpos($relative, $basePath) === 0) {
                        $relative = substr($relative, strlen($basePath) + 1);
                    }
                    print (new Differ(new StrictUnifiedDiffOutputBuilder([
                        'fromFile' => "a/$relative",
                        'toFile' => "b/$relative",
                    ])))->diff($input, $output);
                    Console::info('Out of date:', $file);
                    return;
                }
                $verb = 'Replacing';
            } elseif ($this->Check) {
                Console::info('Would create', $file);
                $this->setExitStatus(1);
                Console::count(Level::ERROR);
                return;
            }
        }

        if ($verb) {
            Console::info($verb, $file);
        }

        file_put_contents($file, $output);
    }

    /**
     * @param string|string[] $phpDoc
     * @return string[]
     */
    private function generatePhpDocBlock($phpDoc): array
    {
        if (!$phpDoc) {
            return [];
        }
        return [
            '/**',
            ...array_map(
                fn(string $line) => $line
                    ? ' * ' . $line
                    : ' *',
                // Implode and explode to allow for multi-line elements
                explode(PHP_EOL, implode(PHP_EOL, (array) $phpDoc))
            ),
            ' */'
        ];
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
