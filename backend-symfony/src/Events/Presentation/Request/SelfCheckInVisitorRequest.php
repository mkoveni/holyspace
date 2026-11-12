<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SelfCheckInVisitorRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $firstName,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $lastName,
        #[Assert\IsTrue(message: 'Please agree to the church contacting you and keeping your details.')]
        public bool $consent,
        #[Assert\NotBlank]
        #[Assert\Length(max: 40)]
        public string $consentVersion,
        #[Assert\Length(max: 40)]
        public ?string $mobile = null,
        #[Assert\Length(max: 254)]
        #[Assert\Email]
        public ?string $email = null,
        #[Assert\Choice(choices: ['FRIEND_OR_FAMILY', 'SOCIAL_MEDIA', 'WEBSITE', 'PASSING_BY', 'EVENT', 'OTHER'])]
        public ?string $howHeard = null,
        #[Assert\Range(min: 1, max: 20)]
        public ?int $partyAdults = null,
        /** @var array<array-key, array{firstName: string, birthDate: string}> children, for Children's Ministry staff to follow up */
        #[Assert\Count(max: 8)]
        #[Assert\All([new Assert\Collection(fields: [
            'firstName' => [new Assert\Type('string'), new Assert\NotBlank(), new Assert\Length(max: 100)],
            'birthDate' => [new Assert\Type('string'), new Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Enter the date of birth as year-month-day.')],
        ])])]
        public array $children = [],
        /** Hidden field that real visitors never fill in; bots do. */
        #[Assert\Length(max: 200)]
        public ?string $website = null,
    ) {
    }
}
