<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunities;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityPage;
use App\Communities\Application\Contract\CommunityReader;
use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use Psr\Clock\ClockInterface;

/** The directory: everything for readers and managers, only the communities they lead for leaders. */
final readonly class ListCommunitiesHandler
{
    public function __construct(private CommunityAccess $access, private CommunityReader $reader, private ClockInterface $clock)
    {
    }

    public function handle(ListCommunitiesQuery $query): CommunityPage
    {
        $this->access->directoryScope();
        CommunityRules::page($query->limit, $query->offset);
        if (null !== $query->kind && !in_array($query->kind, [Community::MINISTRY, Community::GROUP], true)) {
            throw CommunityException::invalid('Choose ministry or group.');
        }
        $scope = $this->access->directoryScope();
        $term = CommunityRules::optional($query->search, 150);
        $now = $this->clock->now();

        return new CommunityPage(
            $this->reader->directory($query->kind, $term, $query->archived, $scope, $query->limit, $query->offset, $now),
            $this->reader->directoryCount($query->kind, $term, $query->archived, $scope, $now),
        );
    }
}
