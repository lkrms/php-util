<?php

declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

abstract class MyBaseClass
{
    /**
     * MyBaseClass::$MyDocumentedProperty PHPDoc
     */
    public $MyDocumentedProperty;

    /**
     * MyBaseClass::MyDocumentedMethod() PHPDoc
     */
    public function MyDocumentedMethod()
    {
    }

}
