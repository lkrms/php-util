<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\Exception\InvalidConfigurationException;
use Salient\Core\ConfigurationManager;
use Salient\Tests\TestCase;
use LogicException;
use OutOfRangeException;

/**
 * @covers \Salient\Core\ConfigurationManager
 */
final class ConfigurationManagerTest extends TestCase
{
    private const CONFIG = [
        'app' => [
            'name' => 'My App',
            'description' => null,
            'maintenance' => [
                'driver' => 'file',
            ],
        ],
        'services' => [
            'Vendor\Contract\ServiceInterface' => 'Vendor\Service',
        ],
    ];

    public function testLoadDirectory(): void
    {
        $config = new ConfigurationManager();
        $config->loadDirectory(self::getFixturesPath(__CLASS__) . '/config');
        $this->assertSame(self::CONFIG, $config->all());
    }

    /**
     * @dataProvider loadInvalidDirectoryProvider
     */
    public function testLoadInvalidDirectory(string $expected, string $directory): void
    {
        $config = new ConfigurationManager();
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expected);
        $config->loadDirectory($directory);
    }

    /**
     * @return array<array{string,string}>
     */
    public static function loadInvalidDirectoryProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);

        return [
            [
                "Invalid configuration file name: $dir/invalid-config1/app.providers.php",
                $dir . '/invalid-config1',
            ],
            [
                "Invalid configuration file name: $dir/invalid-config2/app providers.php",
                $dir . '/invalid-config2',
            ],
            [
                "Invalid configuration file name: $dir/invalid-config3/000.php",
                $dir . '/invalid-config3',
            ],
            [
                "Invalid configuration file: $dir/invalid-config4/app.php",
                $dir . '/invalid-config4',
            ],
        ];
    }

    public function testHas(): void
    {
        $config = new ConfigurationManager(self::CONFIG);
        $this->assertTrue($config->has('app.name'));
        $this->assertTrue($config->has('app.description'));
        $this->assertTrue($config->has('app.maintenance.driver'));
        $this->assertFalse($config->has('app.maintenance.does-not-exist'));
        $this->assertFalse($config->has('app.maintenance.does-not-exist.key'));
    }

    public function testGet(): void
    {
        $config = new ConfigurationManager(self::CONFIG);
        $this->assertSame('My App', $config->get('app.name'));
        $this->assertNull($config->get('app.description'));
        $this->assertSame('file', $config->get('app.maintenance.driver'));
        $this->assertNull($config->get('app.maintenance.does-not-exist', null));
        $this->assertSame('default', $config->get('app.maintenance.does-not-exist.key', 'default'));

        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Value not found: app.maintenance.does-not-exist');
        $config->get('app.maintenance.does-not-exist');
    }

    public function testGetMany(): void
    {
        $config = new ConfigurationManager(self::CONFIG);
        $this->assertSame([
            'app.name' => 'My App',
            'services' => ['Vendor\Contract\ServiceInterface' => 'Vendor\Service'],
        ], $config->getMultiple([
            'app.name',
            'services',
        ]));
        $this->assertSame([
            'app.name' => 'My App',
            'app.does-not-exist' => null,
            'app.maintenance.does-not-exist.key' => null,
        ], $config->getMultiple([
            'app.name',
            'app.does-not-exist',
            'app.maintenance.does-not-exist.key',
        ], null));

        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Value not found: app.does-not-exist');
        $config->getMultiple(['app.name', 'app.does-not-exist']);
    }

    public function testArrayAccess(): void
    {
        $config = new ConfigurationManager(self::CONFIG);
        $this->assertTrue(isset($config['app.name']));
        // `isset()` should return `false` for null values
        $this->assertFalse(isset($config['app.description']));
        $this->assertTrue(isset($config['app.maintenance.driver']));
        $this->assertFalse(isset($config['app.maintenance.does-not-exist']));
        $this->assertFalse(isset($config['app.maintenance.does-not-exist.key']));
        $this->assertSame('My App', $config['app.name']);
        $this->assertNull($config['app.description']);

        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Value not found: app.does-not-exist');
        // @phpstan-ignore expr.resultUnused
        $config['app.does-not-exist'];
    }

    public function testSetThrowsException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Configuration values cannot be changed at runtime');
        $config = new ConfigurationManager();
        $config['key'] = 'value';
    }

    public function testUnsetThrowsException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Configuration values cannot be changed at runtime');
        $config = new ConfigurationManager(self::CONFIG);
        unset($config['app.name']);
    }
}
