<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use App\IdentityAccess\Infrastructure\Security\SymfonyUser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class AuditIdentity
{
    private ?string $source = null;

    public function withSource(string $source, callable $operation): mixed
    {
        $previous = $this->source;
        $this->source = $source;
        try {
            return $operation();
        } finally {
            $this->source = $previous;
        }
    }

    public function __construct(private RequestStack $requests, private TokenStorageInterface $tokens)
    {
    }

    public function values(): array
    {
        $request = $this->requests->getMainRequest();
        $user = $this->tokens->getToken()?->getUser();

        return [
            $user instanceof SymfonyUser ? $user->account()->id()->toString() : '',
            (string) ($request?->attributes->get('_request_id') ?? ''),
            $this->source ?? ($request ? (str_starts_with($request->getPathInfo(), '/api/member') ? 'member_portal' : 'http') : 'worker'),
        ];
    }
}
