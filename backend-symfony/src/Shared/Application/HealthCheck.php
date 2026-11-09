<?php

declare(strict_types=1);

namespace App\Shared\Application;

/** One dependency the API needs to work; tagged `app.health_check` and run by the health endpoint. */
interface HealthCheck
{
    public function healthy(): bool;
}
