<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetLeaderboard;

use App\Forms\Application\AudienceGate;
use App\Forms\Application\Contract\FormKeys;
use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\Contract\LeaderboardEntry;
use App\Forms\Application\Contract\PublishedForm;
use App\Forms\Domain\Audience;
use App\Forms\Domain\FormException;
use Psr\Clock\ClockInterface;

/** Nicknames and scores only, and only when the quiz has its leaderboard switched on. Computed from the rows each time. */
final readonly class GetLeaderboardHandler
{
    public function __construct(private FormReader $reader, private FormKeys $keys, private AudienceGate $gate, private ClockInterface $clock)
    {
    }

    /** @return list<LeaderboardEntry> */
    public function handle(GetLeaderboardQuery $q): array
    {
        $now = $this->clock->now();
        if (null !== $q->token) {
            $form = $this->reader->publishedByTokenHash($this->keys->tokenHash($q->token), $now);
        } else {
            $form = $this->reader->published((string) $q->formId, $now);
            if ($form instanceof PublishedForm) {
                if (Audience::Public->value === $form->audience) {
                    throw FormException::missing();
                }
                $this->gate->require(Audience::from($form->audience), $form->groupIds, (string) $q->accountId, $now);
            }
        }
        if (null === $form || !$form->leaderboardEnabled) {
            throw FormException::missing();
        }

        return $this->reader->leaderboard($form->id, $this->size($form));
    }

    private function size(PublishedForm $form): int
    {
        $view = $this->reader->find($form->id);

        return null === $view ? 10 : $view->leaderboardSize;
    }
}
