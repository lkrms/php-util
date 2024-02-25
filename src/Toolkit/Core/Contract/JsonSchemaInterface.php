<?php declare(strict_types=1);

namespace Salient\Core\Contract;

interface JsonSchemaInterface
{
    public const DRAFT_04_SCHEMA_ID = 'http://json-schema.org/draft-04/schema#';

    /**
     * Get the object's JSON Schema
     *
     * @return array<string,mixed>
     */
    public function getJsonSchema(): array;
}
