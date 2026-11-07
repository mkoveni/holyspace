<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use RuntimeException;

/** Published failure; consumers never depend on the originating domain exception. */
final class StaffingUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Volunteer staffing is unavailable.');
    }
}
