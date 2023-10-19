<?php declare(strict_types=1);

namespace Lkrms\Tests\Iterator;

use Lkrms\Iterator\RecursiveFilesystemIterator;
use FilesystemIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RecursiveFilesystemIteratorTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider iteratorProvider
     *
     * @param string[] $expected
     * @param RecursiveFilesystemIterator $iterator
     */
    public function testIterator(
        array $expected,
        RecursiveFilesystemIterator $iterator,
        string $replace,
        bool $sort = true
    ): void {
        $actual = [];
        foreach ($iterator as $file) {
            $actual[] = str_replace($replace, '', (string) $file);
        }
        if ($sort) {
            sort($expected);
            sort($actual);
        }
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array<string,array{string[],RecursiveFilesystemIterator,string,3?:bool}>
     */
    public static function iteratorProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);

        return [
            'in($dir)' => [
                [
                    '/.hidden',
                    '/dir1/.hidden',
                    '/dir1/dir2/.hidden',
                    '/dir1/dir2/file2',
                    '/dir1/dir2/file6.ext',
                    '/dir1/file3',
                    '/dir1/file4.ext',
                    '/dir2/.hidden',
                    '/dir2/dir3/.hidden',
                    '/dir2/dir3/file7',
                    '/dir2/dir3/file8.ext',
                    '/dir2/file5',
                    '/dir2/file6.ext',
                    '/file1',
                    '/file2.ext',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir),
                $dir,
            ],
            'in($dir)->dirs()' => [
                [
                    '/.hidden',
                    '/dir1',
                    '/dir1/.hidden',
                    '/dir1/dir2',
                    '/dir1/dir2/.hidden',
                    '/dir1/dir2/file2',
                    '/dir1/dir2/file6.ext',
                    '/dir1/file3',
                    '/dir1/file4.ext',
                    '/dir2',
                    '/dir2/.hidden',
                    '/dir2/dir3',
                    '/dir2/dir3/.hidden',
                    '/dir2/dir3/file7',
                    '/dir2/dir3/file8.ext',
                    '/dir2/file5',
                    '/dir2/file6.ext',
                    '/file1',
                    '/file2.ext',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->dirs(),
                $dir,
            ],
            'in($dir)->noFiles()' => [
                [
                    '/dir1',
                    '/dir1/dir2',
                    '/dir2',
                    '/dir2/dir3',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->noFiles(),
                $dir,
            ],
            'in($dir)->noFiles()->noDirs()' => [
                [
                    '/.hidden',
                    '/dir1/.hidden',
                    '/dir1/dir2/.hidden',
                    '/dir1/dir2/file2',
                    '/dir1/dir2/file6.ext',
                    '/dir1/file3',
                    '/dir1/file4.ext',
                    '/dir2/.hidden',
                    '/dir2/dir3/.hidden',
                    '/dir2/dir3/file7',
                    '/dir2/dir3/file8.ext',
                    '/dir2/file5',
                    '/dir2/file6.ext',
                    '/file1',
                    '/file2.ext',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->noFiles()
                    ->noDirs(),
                $dir,
            ],
            'in($dir)->files(false)' => [
                [],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->files(false),
                $dir,
            ],
            'in($dir)->include(REGEX) #1' => [
                [
                    '/dir1/dir2/.hidden',
                    '/dir1/dir2/file2',
                    '/dir1/dir2/file6.ext',
                    '/dir2/.hidden',
                    '/dir2/dir3/.hidden',
                    '/dir2/dir3/file7',
                    '/dir2/dir3/file8.ext',
                    '/dir2/file5',
                    '/dir2/file6.ext',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->include('/\/dir2\//'),
                $dir,
            ],
            'in($dir)->include(REGEX) #2' => [
                [
                    '/.hidden',
                    '/dir1/.hidden',
                    '/dir1/dir2/.hidden',
                    '/dir2/.hidden',
                    '/dir2/dir3/.hidden',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->include('/\/\.hidden$/'),
                $dir,
            ],
            '[unsorted] in($dir)->noFiles()->dirsFirst()->include(REGEX)->matchRelative()' => [
                [
                    '/dir2',
                    '/dir2/dir3',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->noFiles()
                    ->dirsFirst()
                    ->include('/^\/dir2\//')
                    ->matchRelative(),
                $dir,
                false,
            ],
            '[unsorted] in($dir)->noFiles()->dirsLast()->include(REGEX)->matchRelative()' => [
                [
                    '/dir2/dir3',
                    '/dir2',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->noFiles()
                    ->dirsLast()
                    ->include('/^\/dir2\//')
                    ->matchRelative(),
                $dir,
                false,
            ],
            'in($dir)->noFiles()->include(REGEX)' => [
                [
                    '/dir1/dir2',
                    '/dir2',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->noFiles()
                    ->include('/\/dir2$/'),
                $dir,
            ],
            'in($dir)->doNotRecurse()' => [
                [
                    '/.hidden',
                    '/file1',
                    '/file2.ext',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->doNotRecurse(),
                $dir,
            ],
            'in($dir)->dirs()->doNotRecurse()' => [
                [
                    '/.hidden',
                    '/dir1',
                    '/dir2',
                    '/file1',
                    '/file2.ext',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->dirs()
                    ->doNotRecurse(),
                $dir,
            ],
            'in($dir)->dirs()->doNotRecurse()->exclude(REGEX)' => [
                [
                    '/dir1',
                    '/dir2',
                    '/file1',
                    '/file2.ext',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->dirs()
                    ->doNotRecurse()
                    ->exclude('/\/\.hidden$/'),
                $dir,
            ],
            'in($dir)->dirs()->doNotRecurse()->exclude(REGEX)->include(REGEX)->include(CALLABLE)->matchRelative()' => [
                [
                    '/.hidden',
                    '/dir1',
                    '/dir2',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->dirs()
                    ->doNotRecurse()
                    ->exclude('/^file[0-9]+/')
                    ->include('/\/[^\/]*\.[^\/]*$/')
                    ->include(fn(SplFileInfo $f) => $f->isDir())
                    ->matchRelative(),
                $dir,
            ],
            'in($dir)->dirs()->exclude(REGEX)->include(REGEX)->include(CALLABLE)->matchRelative()' => [
                [
                    '/.hidden',
                    '/dir1/.hidden',
                    '/dir1/dir2/.hidden',
                    '/dir1/dir2/file6.ext',
                    '/dir1/dir2',
                    '/dir2/dir3',
                    '/dir1/file4.ext',
                    '/dir2/.hidden',
                    '/dir2/dir3/.hidden',
                    '/dir2/dir3/file8.ext',
                    '/dir2/file6.ext',
                ],
                (new RecursiveFilesystemIterator())
                    ->in($dir)
                    ->dirs()
                    ->exclude('/^file[0-9]+/')
                    ->include('/\/[^\/]*\.[^\/]*$/')
                    ->include(
                        fn(
                            SplFileInfo $f,
                            string $p,
                            FilesystemIterator $i,
                            ?RecursiveIteratorIterator $ri = null
                        ) => $f->isDir() && $ri->getDepth() === 1
                    )
                    ->matchRelative(),
                $dir,
            ],
        ];
    }
}
