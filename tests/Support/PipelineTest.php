<?php

declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;
use Lkrms\Support\Pipeline;
use UnexpectedValueException;

final class PipelineTest extends \Lkrms\Tests\TestCase
{
    public function testStream()
    {
        $in  = [12, 23, 34, 45, 56, 67, 78, 89, 91];
        $out = [];
        foreach (
            (new Container())
            ->get(Pipeline::class)
            ->send($in)
            ->through(
                fn($payload, Closure $next) => $next($payload * 3),
                fn($payload, Closure $next) => $next($payload / 23),
                fn($payload, Closure $next) => $next(round($payload, 3)),
            )->thenStream() as $_out
        ) {
            $out[] = $_out;
        }

        $this->assertSame(
            [1.565, 3.0, 4.435, 5.87, 7.304, 8.739, 10.174, 11.609, 11.87],
            $out
        );
    }

    public function testMap()
    {
        $in = [
            [
                "USER_ID"   => 32,
                "FULL_NAME" => "Greta",
                "MAIL"      => "greta@domain.test",
            ],
            [
                "FULL_NAME" => "Amir",
                "MAIL"      => "amir@domain.test",
                "URI"       => "https://domain.test/~amir",
            ],
            [
                "USER_ID"   => 71,
                "FULL_NAME" => "Terry",
                "MAIL"      => null,
            ],
        ];
        $map = [
            "USER_ID"   => "Id",
            "FULL_NAME" => "Name",
            "MAIL"      => "Email",
        ];
        $out = [];

        $pipeline = Pipeline::create()->map($map);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->thenReturn();
        }

        $pipeline = Pipeline::create()->map($map, ArrayKeyConformity::NONE, ArrayMapperFlag::ADD_MISSING);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->thenReturn();
        }

        $pipeline = Pipeline::create()->map($map, ArrayKeyConformity::NONE, ArrayMapperFlag::ADD_UNMAPPED);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->thenReturn();
        }

        $pipeline = Pipeline::create()->map($map, ArrayKeyConformity::NONE, ArrayMapperFlag::REMOVE_NULL);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->thenReturn();
        }

        $pipeline = Pipeline::create()->map($map, ArrayKeyConformity::NONE, ArrayMapperFlag::ADD_MISSING | ArrayMapperFlag::ADD_UNMAPPED | ArrayMapperFlag::REMOVE_NULL);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->thenReturn();
        }

        $this->assertSame([
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => null, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test', 'URI' => 'https://domain.test/~amir'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry'],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test', 'URI' => 'https://domain.test/~amir'],
            ['Id' => 71, 'Name' => 'Terry']
        ], $out);

        $pipeline = Pipeline::create()->map($map, ArrayKeyConformity::NONE, ArrayMapperFlag::REQUIRE_MAPPED);
        $this->expectException(UnexpectedValueException::class);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->thenReturn();
        }
    }
}
