<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

final class ServingPermissions
{
    public const string READ = 'volunteering.read';
    public const string MANAGE = 'volunteering.manage';
    public const string CREDENTIALS = 'volunteering.credentials.manage';
    public const string SERVE = 'volunteering.serve';
    public const string COMMUNICATIONS = 'volunteering.communications.send';
    public const string IDENTITY = 'identity.manage';

    private function __construct()
    {
    }
}
