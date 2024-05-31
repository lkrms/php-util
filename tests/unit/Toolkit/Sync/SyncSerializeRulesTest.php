<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Container\Container;
use Salient\Sync\SyncSerializeRules;
use Salient\Tests\Sync\CustomEntity\Post as CustomPost;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Sync\SyncSerializeRules
 * @covers \Salient\Sync\SyncSerializeRulesBuilder
 */
final class SyncSerializeRulesTest extends TestCase
{
    public function testApply(): void
    {
        $container = new Container();

        $rules1 = SyncSerializeRules::build($container)
            ->entity(User::class)
            ->remove([
                'l1.l2.field1',
                ['l1.l2.field3', 'field3_id_1'],
                Post::class => [['e1_field1', 'e1_field1_id']],
                User::class => ['e3_field', ['e4_field', 'e4_field_id_1']],
            ])
            ->go();
        $rules2 = SyncSerializeRules::build($container)
            ->entity(User::class)
            ->remove([
                ['l1.l2.field1', 'field1_id_2'],
                'l1.l2.field2',
                ['l1.l2.field2', 'field2_id_2'],
                Post::class => [['e1_field2', 'e1_field2_id'], 'e1_field2'],
                CustomPost::class => ['e2_field2', ['e2_field2', 'e2_field2_id_2']],
                User::class => ['e4_field', ['e3_field', 'e3_field_id_2']],
            ])
            ->go();
        $rules3 = SyncSerializeRules::build($container)
            ->entity(User::class)
            ->remove([
                'l1.l2.field2',
                ['l1.l2.field3', 'field3_id_3'],
                CustomPost::class => ['e2_field3', ['e2_field2', 'e2_field2_id_3']],
                User::class => ['e3_field', ['e4_field', 'e4_field_id_3']],
            ])
            ->go();

        $this->assertEquals([
            ['l1.l2.field1', 'field1_id_2'],
            ['l1.l2.field3', 'field3_id_1'],
            ['l1.l2.field2', 'field2_id_2'],
            Post::class => [['e1_field1', 'e1_field1_id'], 'e1_field2'],
            CustomPost::class => [['e2_field2', 'e2_field2_id_2']],
            User::class => [['e3_field', 'e3_field_id_2'], 'e4_field'],
        ], $rules1->apply($rules2)->getRemove());
        $this->assertEquals([
            'l1.l2.field1',
            ['l1.l2.field2', 'field2_id_2'],
            ['l1.l2.field3', 'field3_id_1'],
            Post::class => ['e1_field2', ['e1_field1', 'e1_field1_id']],
            CustomPost::class => [['e2_field2', 'e2_field2_id_2']],
            User::class => [['e4_field', 'e4_field_id_1'], 'e3_field'],
        ], $rules2->apply($rules1)->getRemove());
        $this->assertEquals([
            ['l1.l2.field2', 'field2_id_2'],
            ['l1.l2.field3', 'field3_id_1'],
            CustomPost::class => ['e2_field3', ['e2_field2', 'e2_field2_id_2']],
            User::class => ['e3_field', ['e4_field', 'e4_field_id_1']],
            Post::class => ['e1_field2', ['e1_field1', 'e1_field1_id']],
            'l1.l2.field1',
        ], $rules3->apply($rules2)->apply($rules1)->getRemove());
    }
}
