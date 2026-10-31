<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetRegistrationOverview;

use App\Events\Application\ReadModel\EventRegistrationReadStore;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;

final readonly class GetRegistrationOverviewHandler
{
    public function __construct(private EventRegistrationReadStore $records, private OccurrenceDirectory $occurrences, private PersonDirectory $people, private ClockInterface $clock)
    {
    }

    public function __invoke(GetRegistrationOverviewQuery $message): array
    {
        $eventId = $message->eventId;
        $occurrenceId = $message->occurrenceId;
        $after = $message->after;
        $limit = $message->limit;
        $occurrence = $this->occurrence($eventId, $occurrenceId);
        if (null !== $after) {
            Input::id($after);
        }
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Limit must be between 1 and 100.');
        }
        $items = $this->records->page($occurrenceId, $after, $limit + 1);
        $more = count($items) > $limit;
        if ($more) {
            array_pop($items);
        }
        $people = $this->people->getMany(array_column($items, 'personId'));
        $counts = $this->records->counts($occurrenceId);
        $settings = $this->records->settings($occurrenceId);

        return ['settings' => $settings, 'counts' => $counts, 'remaining' => null === $settings->capacity ? null : max(0, $settings->capacity - $counts['confirmed'] - $counts['pending_payment']), 'acceptingRegistrations' => $settings->open && $this->available($occurrence), 'items' => array_map(fn ($item) => get_object_vars($item) + ['personName' => $people[$item->personId]['name'] ?? 'Person unavailable'], $items), 'nextAfter' => $more ? end($items)->queueToken : null];
    }

    private function occurrence(string $eventId, string $id): array
    {
        Input::id($eventId);
        Input::id($id);
        $occurrence = $this->occurrences->get($id);
        if (!$occurrence || $occurrence['eventId'] !== $eventId) {
            throw new OutOfBoundsException('Occurrence not found for this event.');
        }

        return $occurrence;
    }

    private function available(array $occurrence): bool
    {
        return 'cancelled' !== $occurrence['status'] && new DateTimeImmutable($occurrence['endsAt'], new DateTimeZone('UTC')) > $this->clock->now();
    }
}
