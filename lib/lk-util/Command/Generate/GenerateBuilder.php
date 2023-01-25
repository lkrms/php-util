<?php declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Generate;

use Closure;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Reflect;
use Lkrms\Facade\Test;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Support\Introspector;
use Lkrms\Support\PhpDocParser;
use Lkrms\Support\TokenExtractor;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Generates fluent interfaces that create instances of a class
 *
 */
class GenerateBuilder extends GenerateCommand
{
    public function getShortDescription(): string
    {
        return 'Generate a fluent interface that creates instances of a class';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('class')
                ->valueName('CLASS')
                ->description('The class to generate a builder for')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
                ->required(),
            CliOption::build()
                ->long('generate')
                ->short('g')
                ->valueName('CLASS')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value)),
            CliOption::build()
                ->long('static-builder')
                ->short('b')
                ->valueName('METHOD')
                ->description('The static method that returns a new builder')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('build'),
            CliOption::build()
                ->long('terminator')
                ->short('t')
                ->valueName('METHOD')
                ->description('The method that terminates the interface')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('go'),
            CliOption::build()
                ->long('static-resolver')
                ->short('r')
                ->valueName('METHOD')
                ->description('The static method that resolves unterminated builders')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('resolve'),
            CliOption::build()
                ->long('extend')
                ->short('x')
                ->valueName('CLASS')
                ->description('The Builder subclass to extend')
                ->optionType(CliOptionType::VALUE)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value)),
            CliOption::build()
                ->long('no-final')
                ->short('a')
                ->description("Do not declare the generated class 'final'"),
            CliOption::build()
                ->long('package')
                ->short('p')
                ->valueName('PACKAGE')
                ->description('The PHPDoc package')
                ->optionType(CliOptionType::VALUE)
                ->envVariable('PHPDOC_PACKAGE'),
            CliOption::build()
                ->long('desc')
                ->short('d')
                ->valueName('DESCRIPTION')
                ->description('A short description of the builder')
                ->optionType(CliOptionType::VALUE),
            CliOption::build()
                ->long('stdout')
                ->short('s')
                ->description('Write to standard output'),
            CliOption::build()
                ->long('force')
                ->short('f')
                ->description('Overwrite the class file if it already exists'),
            CliOption::build()
                ->long('no-meta')
                ->short('m')
                ->description("Suppress '@lkrms-*' metadata tags"),
            CliOption::build()
                ->long('declared')
                ->short('e')
                ->description('Ignore inherited properties'),
        ];
    }

    public $SkipProperties = [];

    protected function run(string ...$args)
    {
        $namespace = explode('\\', ltrim($this->getOptionValue('class'), '\\'));
        $class     = array_pop($namespace);
        $namespace = implode('\\', $namespace) ?: Env::get('DEFAULT_NAMESPACE', '');
        $fqcn      = $namespace ? $namespace . '\\' . $class : $class;

        $builderNamespace = explode('\\', ltrim($this->getOptionValue('generate') ?: $fqcn . 'Builder', '\\'));
        $builderClass     = array_pop($builderNamespace);
        $builderNamespace = implode('\\', $builderNamespace) ?: Env::get('BUILDER_NAMESPACE', $namespace);
        $builderFqcn      = $builderNamespace ? $builderNamespace . '\\' . $builderClass : $builderClass;
        $classPrefix      = $builderNamespace ? '\\' : '';

        $extendsNamespace = explode('\\', ltrim($this->getOptionValue('extend') ?: Builder::class, '\\'));
        $extendsClass     = array_pop($extendsNamespace);
        $extendsNamespace = implode('\\', $extendsNamespace) ?: Env::get('BUILDER_NAMESPACE', $namespace);
        $extendsFqcn      = $extendsNamespace ? $extendsNamespace . '\\' . $extendsClass : $extendsClass;

        $this->OutputClass     = $builderClass;
        $this->OutputNamespace = $builderNamespace;
        $this->ClassPrefix     = $classPrefix;

        $extends   = $this->getFqcnAlias($extendsFqcn, $extendsClass);
        $service   = $this->getFqcnAlias($fqcn, $class);
        $container = $this->getFqcnAlias(IContainer::class);

        $staticBuilder  = Convert::toCamelCase($this->getOptionValue('static-builder'));
        $terminator     = Convert::toCamelCase($this->getOptionValue('terminator'));
        $staticResolver = Convert::toCamelCase($this->getOptionValue('static-resolver'));
        array_push($this->SkipProperties, $staticBuilder, $terminator, $staticResolver);

        $package  = $this->getOptionValue('package');
        $desc     = $this->getOptionValue('desc');
        $desc     = is_null($desc) ? "A fluent interface for creating $class objects" : $desc;
        $declared = $this->getOptionValue('declared');

        if (!$fqcn) {
            throw new CliArgumentsInvalidException("invalid class: $fqcn");
        }

        if (!$builderFqcn) {
            throw new CliArgumentsInvalidException("invalid builder: $builderFqcn");
        }

        if (!$extendsFqcn) {
            throw new CliArgumentsInvalidException("invalid builder subclass: $extendsFqcn");
        }

        if (!is_a($extendsFqcn, Builder::class, true)) {
            throw new CliArgumentsInvalidException("not a subclass of Builder: $extendsClass");
        }

        try {
            $_class = new ReflectionClass($fqcn);

            if (!$_class->isInstantiable() && !$_class->isAbstract()) {
                throw new CliArgumentsInvalidException("not an instantiable class: $fqcn");
            }
        } catch (ReflectionException $ex) {
            throw new CliArgumentsInvalidException("class does not exist: $fqcn");
        }

        $files = [];
        $maybeAddFile =
            function ($file) use (&$files) {
                if ($file !== false) {
                    $files[$file] = $file;
                }
            };

        $writable = Introspector::get($_class->getName())->getWritableProperties();
        $writable = array_combine(
            array_map(
                fn(string $name) => Convert::toCamelCase($name),
                $writable
            ),
            $writable
        );

        /** @var ReflectionParameter[] */
        $_params   = [];
        $toDeclare = [];
        $docBlocks = [];
        if ($_constructor = $_class->getConstructor()) {
            foreach ($_constructor->getParameters() as $_param) {
                $name = Convert::toCamelCase($_param->getName());
                unset($writable[$name]);
                $_params[$name] = $_param;
                // Variables can't be passed to __call by reference, so this
                // parameter needs to be received via a declared method
                if ($_param->isPassedByReference()) {
                    $toDeclare[$name] = $_param;
                }
            }
        }

        /** @var ReflectionProperty[] */
        $_allProperties = [];
        foreach ($_class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) as $_property) {
            if ($_property->isStatic()) {
                continue;
            }
            $_name                 = $_property->getName();
            $name                  = Convert::toCamelCase($_name);
            $_allProperties[$name] = $_property;
        }

        /** @var ReflectionProperty[] */
        $_properties = [];
        $maybeAddFile($_class->getFileName());
        foreach (array_keys($writable) as $name) {
            $_properties[$name] = $_property = $_allProperties[$name];
            $maybeAddFile($_property->getDeclaringClass()->getFileName());
        }

        $useMap  = [];
        $typeMap = [];
        foreach ($files as $file) {
            $useMap[$file]  = (new TokenExtractor($file))->getUseMap();
            $typeMap[$file] = array_change_key_case(array_flip($useMap[$file]), CASE_LOWER);
        }

        $typeNameCallback = function (string $name, bool $returnFqcn = false) use ($typeMap, &$propertyFile): ?string {
            $alias = $typeMap[$propertyFile][ltrim(strtolower($name), '\\')] ?? null;

            return ($alias ? $this->getFqcnAlias($name, $alias, $returnFqcn) : null)
                ?: (Test::isPhpReservedWord($name)
                    ? ($returnFqcn ? $name : null)
                    : $this->getFqcnAlias($name, null, $returnFqcn));
        };
        $phpDocTypeCallback = function (string $type) use (&$propertyFile, &$propertyNamespace, $useMap, $typeNameCallback): string {
            return preg_replace_callback(
                '/(?<!\$)(?=\\\\?\b)' . PhpDocParser::TYPE_PATTERN . '\b/',
                function ($match) use (&$propertyFile, &$propertyNamespace, $useMap, $typeNameCallback) {
                    if (Test::isPhpReservedWord($match[0])) {
                        return $match[0];
                    }
                    if (preg_match('/^\\\\/', $match[0])) {
                        return $typeNameCallback($match[0], true);
                    }

                    return $typeNameCallback(
                        $useMap[$propertyFile][$match[0]]
                            ?? '\\' . $propertyNamespace . '\\' . $match[0],
                        true
                    );
                },
                $type
            );
        };

        $_phpDoc = PhpDocParser::fromDocBlocks(Reflect::getAllMethodDocComments($_constructor));

        $names   = array_keys($_params + $_properties);
        //sort($names);
        $methods = [
            " * @method static \$this $staticBuilder(?$container \$container = null) Create a new $builderClass (syntactic sugar for 'new $builderClass()')",
        ];
        foreach ($names as $name) {
            if (in_array($name, $this->SkipProperties)) {
                continue;
            }

            if ($_property = $_properties[$name] ?? null) {
                if ($declared && $_property->getDeclaringClass() != $_class) {
                    continue;
                }

                $phpDoc            = PhpDocParser::fromDocBlocks(Reflect::getAllPropertyDocComments($_property));
                $propertyFile      = $_property->getDeclaringClass()->getFileName();
                $propertyNamespace = $_property->getDeclaringClass()->getNamespaceName();

                $type = ($_type = $phpDoc->Var[0]['type'] ?? null) && strpbrk($_type, '<>') === false
                    ? $phpDocTypeCallback($_type)
                    : ($_property->hasType()
                        ? Reflect::getTypeDeclaration($_property->getType(), $classPrefix, $typeNameCallback)
                        : '');
                switch ($type) {
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case 'self':
                        $type = $typeNameCallback($_property->getDeclaringClass()->getName(), true);
                        break;
                }
                $summary = $phpDoc->Summary ?? null;
                if (!$summary && ($_param = $_params[$name] ?? null)) {
                    $summary = $_phpDoc ? $_phpDoc->unwrap($_phpDoc->Params[$_param->getName()]['description'] ?? null) : null;
                }

                $type = $type ? "$type " : '';
                $methods[] = " * @method \$this $name($type\$value)" . $this->getSummary(
                    $summary, $_property, $typeNameCallback
                );

                continue;
            }

            $propertyFile      = $_constructor->getFileName();
            $propertyNamespace = $_constructor->getDeclaringClass()->getNamespaceName();
            $declaringClass    = $typeNameCallback($_constructor->getDeclaringClass()->getName(), true);
            $declare           = array_key_exists($name, $toDeclare);

            $_param = $_params[$name];
            $_name  = $_param->getName();

            $_type = ($_type = $_phpDoc->Params[$_name]['type'] ?? null) &&
                strpbrk($_type, '<>') === false
                    ? $phpDocTypeCallback($_type)
                    : null;
            $type = is_null($_type)
                ? ($_param->hasType()
                    ? Reflect::getTypeDeclaration($_param->getType(), $classPrefix, $typeNameCallback)
                    : '')
                : $_type;

            $default = '';
            switch ($type) {
                case 'static':
                case '$this':
                    $type = $service;
                    break;
                case 'self':
                    $type = $declaringClass;
                    break;
                case 'bool':
                    $default = ' = true';
                    break;
            }

            $summary = $_phpDoc ? $_phpDoc->unwrap($_phpDoc->Params[$_name]['description'] ?? null) : null;
            if (($_property = $_allProperties[$name] ?? null) &&
                    !$summary &&
                    ($phpDoc = PhpDocParser::fromDocBlocks(Reflect::getAllPropertyDocComments($_property)))) {
                $summary = $phpDoc->Summary;
            }

            if ($declare) {
                if ($param = Reflect::getParameterPhpDoc($_param, $classPrefix, $typeNameCallback, $_type, 'variable')) {
                    $param = $this->cleanPhpDocTag($param);
                }
                $lines   = [];
                $lines[] = '/**';
                $lines[] = ' * ' . $this->getSummary($summary, $_property, $typeNameCallback, $declaringClass, $name, true, $see);
                $lines[] = ' *';
                $lines[] = " * $param";
                $lines[] = ' * @return $this';
                $lines[] = " * @see $see";
                $lines[] = ' */';
                if (!$param) {
                    unset($lines[3]);
                }
                $docBlocks[$name] = implode(PHP_EOL, $lines);
            } else {
                $type = $type ? "$type " : '';
                $methods[] = " * @method \$this $name($type\$value$default)" . $this->getSummary(
                    $summary, $_property, $typeNameCallback, $declaringClass, $name
                );
            }
        }
        $methods[] = " * @method $service $terminator() Return a new $class object";
        $methods[] = " * @method static $service|null $staticResolver($service|$builderClass|null \$object) Resolve a $builderClass or $class object to a $class object";
        $methods   = implode(PHP_EOL, $methods);

        $imports = $this->getImports();

        $docBlock[] = '/**';
        if ($desc) {
            $docBlock[] = " * $desc";
            $docBlock[] = ' *';
        }
        if ($methods) {
            $docBlock[] = $methods;
            $docBlock[] = ' *';
        }
        if ($package) {
            $docBlock[] = " * @package $package";
        }
        $docBlock[] = " * @uses $service";
        if (!$this->getOptionValue('no-meta')) {
            $docBlock[] = ' * @lkrms-generate-command '
                . implode(' ', $this->getEffectiveCommandLine(true, [
                    'stdout' => null,
                    'force'  => null,
                ]));
        }
        $docBlock[] = ' */';

        $blocks = [
            '<?php declare(strict_types=1);',
            "namespace $builderNamespace;",
            implode(PHP_EOL, $imports),
            implode(PHP_EOL, $docBlock) . PHP_EOL
                . ($_class->isAbstract() ? 'abstract ' : ($this->getOptionValue('no-final') ? '' : 'final '))
                . "class $builderClass extends $extends" . PHP_EOL
                . '{'
        ];

        if (!$imports) {
            unset($blocks[3]);
        }

        if (!$builderNamespace) {
            unset($blocks[2]);
        }

        $lines = [implode(PHP_EOL . PHP_EOL, $blocks)];

        array_push($lines,
                   ...$this->getStaticGetter('getClassName', "$service::class"));

        if ($this->getOption('static-builder')->DefaultValue !== $staticBuilder) {
            array_push($lines, '',
                       ...$this->getStaticGetter('getStaticBuilder', var_export($staticBuilder, true)));
        }

        if ($this->getOption('terminator')->DefaultValue !== $terminator) {
            array_push($lines, '',
                       ...$this->getStaticGetter('getTerminator', var_export($terminator, true)));
        }

        if ($this->getOption('static-resolver')->DefaultValue !== $staticResolver) {
            array_push($lines, '',
                       ...$this->getStaticGetter('getStaticResolver', var_export($staticResolver, true)));
        }

        /** @var ReflectionParameter $_param */
        foreach ($toDeclare as $name => $_param) {
            $code = [
                'return $this->getWithReference(__FUNCTION__, $variable);'
            ];

            $type = $_param->hasType()
                ? Reflect::getTypeDeclaration($_param->getType(), $classPrefix, $typeNameCallback)
                : '';
            $type = $type ? "$type " : '';
            array_push($lines, '',
                       ...$this->getMethod($name, $code, ["{$type}&\$variable"], null, $docBlocks[$name], false));
        }

        $lines[] = '}';

        $this->handleOutput($builderClass, $builderNamespace, $lines);
    }

    private function getSummary(?string $summary, ?ReflectionProperty $property, Closure $typeNameCallback, ?string $class = null, ?string $name = null, bool $declare = false, ?string &$see = null): string
    {
        if ($summary) {
            $summary = rtrim($summary, '.');
        }
        if ($property) {
            $class = $typeNameCallback($property->getDeclaringClass()->getName(), true);
            $name  = $property->getName();
            $param = '';
            $see   = $class . '::$' . $name;
        } else {
            $param = "`\$$name` in ";
            $see   = $class . '::__construct()';
        }

        return $summary
            ? ($declare ? $summary : " $summary (see {@see {$see}})")
            : ($declare ? "Pass a variable to $param$see by reference" : " See {@see {$see}}");
    }
}
