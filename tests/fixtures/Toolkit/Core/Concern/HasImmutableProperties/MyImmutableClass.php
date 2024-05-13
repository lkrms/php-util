<?php declare(strict_types=1);

namespace Salient\Tests\Core\Concern\HasImmutableProperties;

use Salient\Collection\ImmutableCollection;
use Salient\Core\Concern\HasImmutableProperties;
use stdClass;

class MyImmutableClass
{
    use HasImmutableProperties {
        withPropertyValue as public with;
        withoutProperty as public without;
    }

    public int $A;
    /** @var int */
    public $B;
    /** @var int */
    public $C = 3;

    /**
     * @var array<string,string>
     */
    public $Arr1 = [
        'a' => 'foo',
        'b' => 'bar',
    ];

    /** @var array<string,string> */
    public $Arr2 = [];
    /** @var array<string,string> */
    public array $Arr3;
    /** @var array<string,string>|null */
    public $Arr4;
    /** @var stdClass */
    public $Obj;
    /** @var ImmutableCollection<array-key,mixed> */
    public $Coll;
    public stdClass $TypedObj;

    public function __construct()
    {
        $this->Obj = new stdClass();
        $this->Coll = new ImmutableCollection();
    }
}
