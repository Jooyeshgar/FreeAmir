<?php

namespace Tests;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }
}
