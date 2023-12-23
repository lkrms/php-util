<?php declare(strict_types=1);

namespace Lkrms\Tests\Concern;

use Lkrms\Tests\Concern\Immutable\MyImmutableClass;
use Lkrms\Tests\TestCase;

final class ImmutableTest extends TestCase
{
    public function testWithPropertyValue(): void
    {
        $a = new MyImmutableClass();
        $b = $a->with('A', 1);
        $c = $b
            ->with('B', 2)
            ->with('C', $b->C * 10);

        $arr1 = $c->Arr1;
        $arr1['b'] = 'bbb';
        $arr1['c'] = 'ccc';

        $arr2 = $c->Arr2;
        $arr2['d'] = 'ddd';

        $arr3 = $c->Arr3 ?? [];
        $arr3['e'] = 'eee';

        $arr4 = $c->Arr4 ?? [];
        $arr4['f'] = 'fff';

        $d = $c
            ->with('Arr1', $arr1)
            ->with('Arr2', $arr2)
            ->with('Arr3', $arr3)
            ->with('Arr4', $arr4);

        $obj = clone $d->Obj;
        $obj->A = 'aa';
        $obj->B = 'bb';

        $e = $d
            ->with('Obj', $obj);
        $f = $e
            ->with('A', 1)  // Changes to $f should be no-ops
            ->with('Obj', $obj);

        $g = $f->with('Coll', $f->Coll->set('g', new \stdClass()));

        $this->assertNotSame($a, $b);
        $this->assertNotEquals($a, $b);
        $this->assertNotSame($b, $c);
        $this->assertNotEquals($b, $c);
        $this->assertNotSame($c, $d);
        $this->assertNotEquals($c, $d);
        $this->assertNotSame($d, $e);
        $this->assertNotEquals($d, $e);
        $this->assertNotSame($f, $g);
        $this->assertNotEquals($f, $g);

        $this->assertSame($e, $f);

        $this->assertSame($c->Obj, $d->Obj);
        $this->assertNotSame($d->Obj, $e->Obj);

        $this->assertSame($d->Coll, $e->Coll);
        $this->assertNotSame($f->Coll, $g->Coll);

        $A = new MyImmutableClass();
        $A->A = 1;
        $A->B = 2;
        $A->C = 30;
        $A->Arr1 = [
            'a' => 'foo',
            'b' => 'bbb',
            'c' => 'ccc',
        ];
        $A->Arr2 = [
            'd' => 'ddd',
        ];
        $A->Arr3 = [
            'e' => 'eee',
        ];
        $A->Arr4 = [
            'f' => 'fff',
        ];
        $A->Obj->A = 'aa';
        $A->Obj->B = 'bb';
        $A->Coll = $A->Coll->set('g', new \stdClass());

        $this->assertEquals($A, $g);
    }
}
