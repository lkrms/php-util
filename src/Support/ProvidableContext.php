<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IHierarchy;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvidableContext;
use RuntimeException;

/**
 * The context within which an IProvidable is instantiated
 *
 */
class ProvidableContext implements IProvidableContext
{
    /**
     * @var IContainer
     */
    protected $Container;

    /**
     * @var array<string,mixed>
     */
    protected $Values = [];

    /**
     * @var IProvidable[]
     */
    protected $Stack = [];

    /**
     * @var IHierarchy|null
     */
    protected $Parent;

    public function __construct(IContainer $container, ?IHierarchy $parent = null)
    {
        $this->Container = $container;
        $this->Parent    = $parent;
    }

    final protected function maybeMutate(string $property, $value, ?string $key = null)
    {
        if ($key)
        {
            if (!is_array($this->{$property}))
            {
                throw new RuntimeException("\$this->{$property} is not an array");
            }
            $_value       = $this->{$property};
            $_value[$key] = $value;
            $value        = $_value;
        }
        if ($value === $this->{$property})
        {
            return $this;
        }
        $clone = clone $this;
        $clone->{$property} = $value;

        return $clone;
    }

    final public function app(): IContainer
    {
        return $this->Container;
    }

    final public function container(): IContainer
    {
        return $this->Container;
    }

    final public function set(string $key, $value)
    {
        return $this->maybeMutate("Values", $value, $key);
    }

    final public function push(IProvidable $entity)
    {
        $clone = clone $this;
        $clone->Stack[] = $entity;

        return $clone;
    }

    final public function withContainer(IContainer $container)
    {
        return $this->maybeMutate("Container", $container);
    }

    final public function withParent(?IHierarchy $parent)
    {
        return $this->maybeMutate("Parent", $parent);
    }

    final public function get(string $key)
    {
        return $this->Values[$key] ?? false;
    }

    final public function getStack(): array
    {
        return $this->Stack;
    }

    final public function getParent(): ?IHierarchy
    {
        return $this->Parent;
    }

}
