<?php

declare(strict_types=1);

namespace Lkrms\Curler;

/**
 * HTTP header handler
 *
 * @package Lkrms
 */
class CurlerHeaders
{
    private $Headers = [
        "user-agent" => "User-Agent:util-php/Curler (https://github.com/lkrms/util-php)"
    ];

    public function setHeader(string $name, string $value)
    {
        // HTTP headers are case-insensitive, so make sure we don't end up with duplicates
        $this->Headers[strtolower($name)] = "{$name}:{$value}";
    }

    public function unsetHeader(string $name)
    {
        unset($this->Headers[strtolower($name)]);
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        return array_values($this->Headers);
    }

    /**
     * @return string[]
     */
    public function getPublicHeaders(): array
    {
        return array_values(array_filter(
            $this->Headers,
            function ($key) { return !in_array($key, ["authorization"]); },
            ARRAY_FILTER_USE_KEY
        ));
    }
}

