<?php
declare(strict_types=1); namespace App\IdentityAccess\Application\Command\CreateRole; final readonly class CreateRoleCommand { public function __construct(public string $name, public string $description='') {} }
