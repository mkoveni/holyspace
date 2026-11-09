<?php

declare(strict_types=1);

namespace App\Media\Domain;

/**
 * The pictures the church keeps. A kind fixes who can own it, the size it is stored at and whether it may be shown
 * without a session. Person, family and profile pictures are personal data (children included) and are never public.
 */
enum ImageKind: string
{
    case Profile = 'profile';
    case Person = 'person';
    case Family = 'family';
    case FamilyBanner = 'family_banner';
    case Event = 'event';
    case EventBanner = 'event_banner';
    case Community = 'community';

    public function ownerType(): OwnerType
    {
        return match ($this) {
            self::Profile => OwnerType::Account,
            self::Person => OwnerType::Person,
            self::Family, self::FamilyBanner => OwnerType::Household,
            self::Event, self::EventBanner => OwnerType::Event,
            self::Community => OwnerType::Community,
        };
    }

    public function policy(): ImagePolicy
    {
        return match ($this) {
            self::Profile, self::Person => new ImagePolicy(250, 250, [64]),
            self::Family => new ImagePolicy(1200, 800, [480]),
            self::FamilyBanner => new ImagePolicy(1200, 300, [600]),
            self::Event, self::EventBanner, self::Community => new ImagePolicy(1280, 720, [640]),
        };
    }

    /** True only for pictures that carry no personal data and may be fetched by their random id without a session. */
    public function isPublic(): bool
    {
        return match ($this) {
            self::Event, self::EventBanner, self::Community => true,
            self::Profile, self::Person, self::Family, self::FamilyBanner => false,
        };
    }
}
