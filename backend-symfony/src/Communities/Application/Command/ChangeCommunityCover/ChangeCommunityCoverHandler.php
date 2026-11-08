<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\ChangeCommunityCover;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\Contract\CommunityCoverReader;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\CommunityCoverRepository;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\CoverImage\CoverImages;
use Psr\Clock\ClockInterface;

/** Staff with `ministries.manage` change a community's cover. The community row lock serializes cover changes. */
final readonly class ChangeCommunityCoverHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private CommunityRepository $communities,
        private CommunityCoverReader $reader,
        private CommunityCoverRepository $covers,
        private CoverImages $images,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ChangeCommunityCoverCommand $command): void
    {
        $this->access->manager($command->actorId);
        $community = $this->communities->lockCommunity($command->communityId) ?? throw CommunityException::missing();
        $current = $this->reader->cover($community->id);
        CommunityRules::version($current->version, $command->expectedVersion);
        $now = $this->clock->now();
        $this->covers->save($community->id, $this->images->next($current, $command->image, $now), $now);
        $this->images->discard($current);
        $this->audit->record($community->id, $community->id, 'COVER_CHANGED', null === $command->image ? 'Cover image removed' : 'Cover image set', $command->actorId, $now);
    }
}
