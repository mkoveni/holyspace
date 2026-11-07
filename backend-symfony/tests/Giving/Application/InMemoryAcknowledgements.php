<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Domain\Acknowledgement;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\AcknowledgementRepository;

/** In-memory test double: no database. One acknowledgement per gift, as the unique gift_id column enforces. */
final class InMemoryAcknowledgements implements AcknowledgementRepository
{
    /** @var array<string, Acknowledgement> */
    public array $acknowledgements = [];
    /** @var array<string, string> */
    public array $hashes = [];

    public function issuedBy(string $requestId, string $hash): ?string
    {
        if (!isset($this->hashes[$requestId])) {
            return null;
        }
        if ($this->hashes[$requestId] !== $hash) {
            throw GivingException::conflict('This request identifier was already used.');
        }

        return $requestId;
    }

    public function forGift(string $giftId): ?string
    {
        foreach ($this->acknowledgements as $acknowledgement) {
            if ($acknowledgement->giftId === $giftId) {
                return $acknowledgement->id;
            }
        }

        return null;
    }

    public function insert(Acknowledgement $acknowledgement, string $hash): void
    {
        $this->acknowledgements[$acknowledgement->id] = $acknowledgement;
        $this->hashes[$acknowledgement->id] = $hash;
    }
}
