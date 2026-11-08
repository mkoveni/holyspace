<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\GetCommunityCover;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityCover;
use App\Communities\Application\Contract\CommunityCoverReader;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Domain\CommunityException;
use App\Shared\Application\CoverImage\CoverImageContent;
use App\Shared\Application\CoverImage\CoverImages;
use App\Shared\Domain\CoverSlot;
use Psr\Clock\ClockInterface;

/** The directory's readers see every cover; a leader only those of the communities they currently lead. */
final readonly class GetCommunityCoverHandler
{
    public function __construct(
        private CommunityAccess $access,
        private CommunityReader $communities,
        private CommunityCoverReader $covers,
        private CoverImages $images,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetCommunityCoverQuery $query): CommunityCover
    {
        $slot = $this->slot($query);

        return new CommunityCover($slot, $this->images->info($slot));
    }

    public function content(GetCommunityCoverQuery $query): CoverImageContent
    {
        return $this->images->content($this->slot($query)) ?? throw CommunityException::missing();
    }

    private function slot(GetCommunityCoverQuery $query): CoverSlot
    {
        $scope = $this->access->directoryScope();
        if (null !== $scope && !$this->communities->leads($query->communityId, $scope, $this->clock->now())) {
            throw CommunityException::missing();
        }
        $this->communities->community($query->communityId) ?? throw CommunityException::missing();

        return $this->covers->cover($query->communityId);
    }
}
