<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\ListMemberForms;

use App\Forms\Application\AudienceGate;
use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\Contract\PublishedForm;
use App\Forms\Domain\Audience;
use Psr\Clock\ClockInterface;

/** The internal forms this caller may answer now. Filtered by the same gate the submit handler applies. */
final readonly class ListMemberFormsHandler
{
    public function __construct(private FormReader $reader, private AudienceGate $gate, private ClockInterface $clock)
    {
    }

    /** @return list<PublishedForm> */
    public function handle(ListMemberFormsQuery $q): array
    {
        $now = $this->clock->now();

        return array_values(array_filter(
            $this->reader->internalPublished($now),
            fn (PublishedForm $f): bool => $this->gate->check(Audience::from($f->audience), $f->groupIds, $q->accountId, $now)['allowed'],
        ));
    }
}
