<?php

declare(strict_types=1);
namespace App\IdentityAccess\Application\Command\ChangePassword;
final readonly class ChangePasswordCommand { public function __construct(public string $userId, public string $plainPassword) {} }
