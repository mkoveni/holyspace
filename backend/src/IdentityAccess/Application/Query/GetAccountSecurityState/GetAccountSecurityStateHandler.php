<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetAccountSecurityState;

use App\IdentityAccess\Application\Port\SecurityStateReader;

final readonly class GetAccountSecurityStateHandler
{
    public function __construct(private SecurityStateReader $db)
    {
    }

    public function __invoke(GetAccountSecurityStateQuery $message): array
    {
        $userId = $message->userId;
        $row = $this->db->find($userId);

        return $row ?: ['user_id' => $userId, 'secret' => null, 'pending_secret' => null, 'pending_until' => 0, 'last_step' => -1, 'recovery_hashes' => '[]', 'recovery_until' => 0, 'version' => 0];
    }
}
