<?php declare(strict_types=1);

namespace Salient\Tests\Contract\Catalog;

use Salient\Contract\Catalog\TextComparisonAlgorithm;
use Salient\Contract\Catalog\TextComparisonFlag;
use Salient\Tests\TestCase;
use ReflectionClass;

/**
 * @coversNothing
 */
final class TextComparisonFlagTest extends TestCase
{
    public function testConstantValues(): void
    {
        $algorithms = [];

        $constants =
            (new ReflectionClass(TextComparisonAlgorithm::class))
                ->getReflectionConstants();
        foreach ($constants as $constant) {
            if (!$constant->isPublic()) {
                continue;
            }
            $name = $constant->getName();
            $value = $constant->getValue();
            $this->assertIsInt(
                $value,
                sprintf('%s::%s', TextComparisonAlgorithm::class, $name)
            );
            $algorithms[$name] = $value;
        }

        $constants =
            (new ReflectionClass(TextComparisonFlag::class))
                ->getReflectionConstants();
        foreach ($constants as $constant) {
            if (!$constant->isPublic()) {
                continue;
            }
            $name = $constant->getName();
            $value = $constant->getValue();
            $this->assertIsInt(
                $value,
                sprintf('%s::%s', TextComparisonFlag::class, $name)
            );
            foreach ($algorithms as $algorithm => $algorithmValue) {
                $this->assertSame(
                    0,
                    $algorithmValue & $value,
                    sprintf(
                        '%s::%s & %s::%s',
                        TextComparisonAlgorithm::class,
                        $algorithm,
                        TextComparisonFlag::class,
                        $name,
                    )
                );
            }
        }
    }
}
