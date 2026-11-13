<?php

declare(strict_types=1);

namespace App\Forms\Domain;

enum Audience: string
{
    case Public = 'PUBLIC';
    case Members = 'MEMBERS';
    case Staff = 'STAFF';
    case Groups = 'GROUPS';
}
