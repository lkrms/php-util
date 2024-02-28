<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntityProviderInterface;
use Salient\Contract\Sync\SyncEntityResolverInterface;
use Salient\Core\Catalog\TextComparisonAlgorithm as Algorithm;
use Salient\Core\Catalog\TextComparisonFlag as Flag;
use Salient\Sync\Support\SyncEntityFuzzyResolver;
use Salient\Sync\Support\SyncEntityResolver;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\SyncTestCase;

final class SyncEntityResolverTest extends SyncTestCase
{
    /**
     * @dataProvider getByNameProvider
     *
     * @template T of SyncEntityInterface
     *
     * @param array<array{string|null,float|null}> $expected
     * @param class-string<SyncEntityResolverInterface<T>> $resolver
     * @param class-string<T> $entity
     * @param mixed[] $args
     * @param string[] $names
     */
    public function testGetByName(
        array $expected,
        string $resolver,
        string $entity,
        string $propertyName,
        array $args,
        array $names
    ): void {
        /** @var SyncEntityProviderInterface<T> */
        $provider = [$entity, 'withDefaultProvider']($this->App);
        /** @var SyncEntityResolverInterface<T> */
        $resolver = new $resolver($provider, $propertyName, ...$args);
        foreach ($names as $name) {
            $uncertainty = -1;
            $result = $resolver->getByName($name, $uncertainty);
            if ($result) {
                $result = $result->{$propertyName};
            }
            $actual[] = [$result, $uncertainty];
        }
        $this->assertSame($expected, $actual ?? []);
    }

    /**
     * @return array<string,array{array<array{string|null,float|null}>,class-string<SyncEntityResolverInterface<SyncEntityInterface>>,class-string<SyncEntityInterface>,string,mixed[],string[]}>
     */
    public static function getByNameProvider(): array
    {
        $names = [
            'Leanne Graham',
            'leanne graham',
            'GRAHAM, leanne',
            'Lee-Anna Graham',
            'leanne graham',
            'GRAHAM, leanne',
            'Lee-Anna Graham',
        ];

        return [
            'first exact' => [
                [
                    ['Leanne Graham', 0.0],
                    [null, null],
                    [null, null],
                    [null, null],
                    [null, null],
                    [null, null],
                    [null, null],
                ],
                SyncEntityResolver::class,
                User::class,
                'Name',
                [],
                $names,
            ],
            'levenshtein + normalise' => [
                [
                    ['Leanne Graham', 0.0],
                    ['Leanne Graham', 0.0],
                    [null, null],
                    ['Leanne Graham', 0.2],
                    ['Leanne Graham', 0.0],
                    [null, null],
                    ['Leanne Graham', 0.2],
                ],
                SyncEntityFuzzyResolver::class,
                User::class,
                'Name',
                [Algorithm::LEVENSHTEIN | Flag::NORMALISE, 0.6],
                $names,
            ],
            'similar_text + normalise' => [
                [
                    ['Leanne Graham', 0.0],
                    ['Leanne Graham', 0.0],
                    ['Leanne Graham', 0.5384615384615384],
                    ['Leanne Graham', 0.19999999999999996],
                    ['Leanne Graham', 0.0],
                    ['Leanne Graham', 0.5384615384615384],
                    ['Leanne Graham', 0.19999999999999996],
                ],
                SyncEntityFuzzyResolver::class,
                User::class,
                'Name',
                [Algorithm::SIMILAR_TEXT | Flag::NORMALISE, 0.6],
                $names,
            ]
        ];
    }
}
