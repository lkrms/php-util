<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use stdClass;

/**
 * @internal
 */
class MethodData implements JsonSerializable
{
    use HasPHPDoc;
    use HasTemplates;
    use MemberDataTrait;

    public string $Name;
    public ClassData $Class;
    /** @var array<string,string> */
    public array $Templates = [];
    public ?string $Summary = null;
    public bool $Api = false;
    public bool $Internal = false;
    public bool $Deprecated = false;
    public bool $Declared = false;
    public bool $HasDocComment = false;
    public bool $Inherited = false;
    /** @var array{class-string,string}|null */
    public ?array $InheritedFrom = null;
    /** @var array{class-string,string}|null */
    public ?array $Prototype = null;
    public bool $IsAbstract = false;
    public bool $IsFinal = false;
    public bool $IsPublic = false;
    public bool $IsProtected = false;
    public bool $IsPrivate = false;
    public bool $IsStatic = false;
    /** @var string[] */
    public array $Modifiers = [];
    /** @var array<string,array{string,string}> */
    public array $Parameters = [];
    public ?string $ReturnType = null;
    public ?int $Line = null;
    public ?int $Lines = null;

    final public function __construct(string $name, ClassData $class)
    {
        $this->Name = $name;
        $this->Class = $class;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    public static function fromReflection(
        ReflectionMethod $method,
        ReflectionClass $class,
        ClassData $classData,
        ?bool $declared = null,
        ?int $line = null,
        ?ConsoleWriterInterface $console = null
    ): self {
        $methodName = $method->getName();
        $docBlocks = PHPDocUtil::getAllMethodDocComments($method, $class, $classDocBlocks);
        $phpDoc = PHPDoc::fromDocBlocks($docBlocks, $classDocBlocks, "{$methodName}()");
        $declaring = $method->getDeclaringClass();
        $className = $class->getName();
        $declaringName = $declaring->getName();

        $data = (new static($methodName, $classData))
            ->applyPHPDoc($phpDoc)
            ->applyTemplates($phpDoc);
        $data->Declared = $declared ?? ($declaringName === $className);
        $data->Inherited = $declaringName !== $className;

        if ($prototype = Reflect::getPrototype($method)) {
            $data->Prototype = [
                $prototype->getDeclaringClass()->getName(),
                $prototype->getName(),
            ];
        }

        if ($data->Declared) {
            $start = $method->getStartLine();
            $end = $method->getEndLine();
            if ($start === false || $end === false) {
                // @codeCoverageIgnoreStart
                !$declared || !$console || $console->warn(
                    'Cannot get method start/end lines via reflection:',
                    $data->getFqsen(),
                );
                $data->Line = $line;
                // @codeCoverageIgnoreEnd
            } else {
                $data->Line = $start;
                $data->Lines = $end - $start + 1;
            }
        } elseif ($declaringName !== $className) {
            $data->InheritedFrom = [$declaringName, $methodName];
        } elseif ($inserted = Reflect::getTraitMethod($declaring, $methodName)) {
            $data->InheritedFrom = [
                $inserted->getDeclaringClass()->getName(),
                $inserted->getName(),
            ];
        }

        $data->Modifiers = array_keys(array_filter([
            'abstract' => $data->IsAbstract = !$class->isInterface() && $method->isAbstract(),
            'final' => $data->IsFinal = $method->isFinal(),
            'public' => $data->IsPublic = $method->isPublic(),
            'protected' => $data->IsProtected = $method->isProtected(),
            'private' => $data->IsPrivate = $method->isPrivate(),
            'static' => $data->IsStatic = $method->isStatic(),
        ]));

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (
                ($tag = $phpDoc->getParams()[$name] ?? null)
                && ($tagType = $tag->getType()) !== null
            ) {
                $type = "{$tagType} ";
            } elseif ($param->hasType()) {
                $type = PHPDocUtil::getTypeDeclaration(
                    $param->getType(),
                    '\\',
                    fn($fqcn) => Get::basename($fqcn),
                ) . ' ';
            } else {
                $type = '';
            }
            if ($param->isPassedByReference()) {
                $type .= '&';
            }
            if ($param->isVariadic()) {
                $type .= '...';
            }
            $default = '';
            if ($param->isDefaultValueAvailable()) {
                $default .= ' = ';
                if ($param->isDefaultValueConstant()) {
                    $default .= PHPDocUtil::removeTypeNamespaces((string) $param->getDefaultValueConstantName());
                } else {
                    $default .= Get::code($param->getDefaultValue());
                }
            }
            $data->Parameters[$name] = [$type, $default];
        }

        if ($phpDoc->hasReturn()) {
            $data->ReturnType = $phpDoc->getReturn()->getType();
        } elseif ($method->hasReturnType()) {
            $data->ReturnType = PHPDocUtil::getTypeDeclaration(
                $method->getReturnType(),
                '\\',
                fn($fqcn) => Get::basename($fqcn),
            );
        }

        return $data;
    }

    public function getFqsen(): string
    {
        return "{$this->Class->getFqcn()}::{$this->Name}()";
    }

    public function getStructuralElementName(): string
    {
        return "{$this->Name}()";
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'templates' => $this->Templates ?: new stdClass(),
            'summary' => $this->Summary,
            'api' => $this->Api,
            'internal' => $this->Internal,
            'deprecated' => $this->Deprecated,
            'declared' => $this->Declared,
            'hasDocComment' => $this->HasDocComment,
            'inherited' => $this->Inherited,
            'inheritedFrom' => $this->InheritedFrom,
            'prototype' => $this->Prototype,
            'abstract' => $this->IsAbstract,
            'final' => $this->IsFinal,
            'public' => $this->IsPublic,
            'protected' => $this->IsProtected,
            'private' => $this->IsPrivate,
            'static' => $this->IsStatic,
            'modifiers' => $this->Modifiers,
            'parameters' => $this->Parameters ?: new stdClass(),
            'returnType' => $this->ReturnType,
            'line' => $this->Line,
            'lines' => $this->Lines,
        ];
    }
}
