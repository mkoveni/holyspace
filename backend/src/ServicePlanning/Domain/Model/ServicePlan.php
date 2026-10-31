<?php

declare(strict_types=1);

namespace App\ServicePlanning\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class ServicePlan extends WorkflowRecord
{
    public function __construct(public readonly string $occurrenceId, public readonly string $title, public readonly array $items, public readonly string $status = 'draft', string $id = '', string $createdAt = '', string $updatedAt = '', int $version = 0)
    {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($occurrenceId);
        Input::text($title, 200);
        if (!in_array($status, ['draft', 'published', 'needs_review', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid plan status.');
        }
        if (!array_is_list($items) || count($items) < 1 || count($items) > 50) {
            throw new InvalidArgumentException('A service plan needs 1–50 ordered items.');
        }
        foreach ($items as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['title', 'durationMinutes', 'notes', 'resourceUrl']) || !is_string($item['title'] ?? null)) {
                throw new InvalidArgumentException('Invalid service item.');
            }
            Input::text($item['title'], 200);
            if (!is_int($item['durationMinutes'] ?? null) || $item['durationMinutes'] < 1 || $item['durationMinutes'] > 180) {
                throw new InvalidArgumentException('Item duration must be 1–180 whole minutes.');
            }
            if (isset($item['notes']) && (!is_string($item['notes']) || mb_strlen($item['notes']) > 2000)) {
                throw new InvalidArgumentException('Item notes are too long.');
            }
            if (isset($item['resourceUrl']) && '' !== $item['resourceUrl'] && (!is_string($item['resourceUrl']) || strlen($item['resourceUrl']) > 2000 || !filter_var($item['resourceUrl'], FILTER_VALIDATE_URL) || 'https' !== parse_url($item['resourceUrl'], PHP_URL_SCHEME) || null !== parse_url($item['resourceUrl'], PHP_URL_USER))) {
                throw new InvalidArgumentException('Resources must be HTTPS links without credentials.');
            }
        }
    }

    public function edit(string $title, array $items): self
    {
        return $this->changed(['title' => $title, 'items' => $items, 'status' => 'draft']);
    }

    public function changeStatus(string $status): self
    {
        return $this->changed(['status' => $status]);
    }
}
