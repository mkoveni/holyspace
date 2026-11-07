<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\IssueAcknowledgement;

use App\Giving\Application\Contract\GivingReader;
use App\Giving\Application\GivingAccess;
use App\Giving\Application\GivingHash;
use App\Giving\Domain\Acknowledgement;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\AcknowledgementRepository;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Repository\GivingHistoryRepository;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Issues the one numbered acknowledgement a gift may have, as an explicit command (never a posting side effect).
 * Idempotent on the request id. The gift row lock serialises concurrent issues, so the second sees the first.
 */
final readonly class IssueAcknowledgementHandler implements CommandHandler
{
    public function __construct(
        private GivingAccess $access,
        private GiftRepository $gifts,
        private AcknowledgementRepository $acknowledgements,
        private GivingReader $reader,
        private GivingHistoryRepository $history,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
    ) {
    }

    /** @return string the acknowledgement id, which is the request id */
    public function handle(IssueAcknowledgementCommand $command): string
    {
        $this->access->acknowledge($command->actorId);
        $hash = GivingHash::of('acknowledgement', $command->actorId, $command->giftId);

        $gift = $this->gifts->findForUpdate($command->giftId) ?? throw GivingException::missing();
        $replay = $this->acknowledgements->issuedBy($command->requestId, $hash);
        if (null !== $replay) {
            return $replay;
        }
        if ($this->gifts->hasReversal($gift->id) || $this->gifts->refunded($gift->id) > 0) {
            throw GivingException::conflict('An acknowledgement cannot be issued for a reversed gift.');
        }
        if (null !== $this->acknowledgements->forGift($gift->id)) {
            throw GivingException::conflict('This gift already has an acknowledgement. Refresh to open it.');
        }
        $detail = $this->reader->gift($gift->id) ?? throw GivingException::missing();
        $now = $this->clock->now();
        $acknowledgement = Acknowledgement::issue(
            $command->requestId,
            $gift,
            $detail->donorName,
            $detail->fundNames,
            $this->calendar->today(),
            $command->actorId,
            $now,
        );
        $this->acknowledgements->insert($acknowledgement, $hash);
        $this->history->append($gift->id, GivingHistoryRepository::GIFT, 'ACKNOWLEDGEMENT_ISSUED', '', $command->actorId, $now);

        return $acknowledgement->id;
    }
}
