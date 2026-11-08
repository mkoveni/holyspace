<?php

declare(strict_types=1);

namespace App\Communities\Application;

use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Application\Contract\CommunityView;
use App\Communities\Domain\CommunityException;
use App\IdentityAccess\Application\Contract\CurrentActor;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use Psr\Clock\ClockInterface;

/**
 * Java `CommunityAccess`. `ministries.manage` reaches every community; `ministries.read` browses the directory but no
 * rosters; `ministries.lead` reaches only the communities the caller currently leads through a bound login.
 */
final readonly class CommunityAccess
{
    public const string READ = 'ministries.read';
    public const string MANAGE = 'ministries.manage';
    public const string LEAD = 'ministries.lead';
    public const string SEND = 'ministries.communications.send';

    public function __construct(
        private PermissionChecker $auth,
        private CurrentActor $actors,
        private CommunityReader $reader,
        private ClockInterface $clock,
    ) {
    }

    public function manager(string $actorId): void
    {
        $this->auth->requireActorPermission($actorId, self::MANAGE);
    }

    public function caller(): string
    {
        return $this->actors->current()->accountId ?? throw CommunityException::forbidden('Sign in to continue.');
    }

    /** @return string|null null for the whole directory, or the account whose current leadership bounds it */
    public function directoryScope(): ?string
    {
        if ($this->auth->hasPermission(self::READ) || $this->auth->hasPermission(self::MANAGE)) {
            $this->auth->requirePermission(self::READ);

            return null;
        }
        $this->auth->requirePermission(self::LEAD);

        return $this->caller();
    }

    public function canOperate(string $communityId): bool
    {
        return $this->auth->hasPermission(self::MANAGE)
            || ($this->auth->hasPermission(self::LEAD) && $this->reader->leads($communityId, $this->caller(), $this->clock->now()));
    }

    public function require(string $communityId): void
    {
        if ($this->auth->hasPermission(self::MANAGE)) {
            $this->auth->requirePermission(self::MANAGE);

            return;
        }
        $this->auth->requirePermission(self::LEAD);
        if (!$this->reader->leads($communityId, $this->caller(), $this->clock->now())) {
            throw CommunityException::forbidden('This community is outside your current leadership scope.');
        }
    }

    /** The actor must be the caller and hold manage, or else lead (scope is checked separately with require()). */
    public function actor(string $actorId): void
    {
        $this->auth->requireActorPermission($actorId, $this->auth->hasPermission(self::MANAGE) ? self::MANAGE : self::LEAD);
    }

    public function community(string $id): CommunityView
    {
        return $this->reader->community($id) ?? throw CommunityException::missing();
    }
}
