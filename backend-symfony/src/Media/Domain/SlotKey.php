<?php

declare(strict_types=1);

namespace App\Media\Domain;

/** Which picture slot: one kind of picture of one owner. The owner type follows from the kind. */
final readonly class SlotKey
{
    private function __construct(public OwnerType $ownerType, public string $ownerId, public ImageKind $kind)
    {
    }

    public static function of(ImageKind $kind, string $ownerId): self
    {
        $normalized = strtolower($ownerId);
        if (1 !== preg_match(MediaFile::UUID, $normalized)) {
            throw MediaException::invalid('The image owner is not valid.');
        }

        return new self($kind->ownerType(), $normalized, $kind);
    }

    public function equals(self $other): bool
    {
        return $this->ownerType === $other->ownerType && $this->ownerId === $other->ownerId && $this->kind === $other->kind;
    }
}
