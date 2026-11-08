<?php

declare(strict_types=1);

namespace App\Volunteering\Domain;

/**
 * A role on a team, with the children responsibility it carries and the credential types readiness requires. The
 * children responsibility never changes: approvals keep the scope they were granted for.
 */
final readonly class ServingRole
{
    public const array CHILDREN_ROLES = ['NONE', 'LEADER', 'ASSISTANT'];

    private const int MAX_REQUIREMENTS = 30;

    public string $name;

    /** @var list<string> credential type ids */
    public array $requiredCredentials;

    /** @param list<string|null>|null $requiredCredentials */
    private function __construct(public string $id, public string $teamId, ?string $name, public string $childrenRole, public bool $active, ?array $requiredCredentials)
    {
        $this->name = ServingRules::text($name, 100);
        if (!\in_array($childrenRole, self::CHILDREN_ROLES, true)) {
            throw VolunteerException::invalid('Choose a children responsibility or none.');
        }
        if (null === $requiredCredentials || \in_array(null, $requiredCredentials, true)) {
            throw VolunteerException::invalid('Choose credential requirements from the catalogue.');
        }
        /** @var list<string> $requiredCredentials */
        if (\count($requiredCredentials) > self::MAX_REQUIREMENTS || \count(array_unique($requiredCredentials)) !== \count($requiredCredentials)) {
            throw VolunteerException::invalid('Choose at most 30 unique credential requirements.');
        }
        $this->requiredCredentials = $requiredCredentials;
    }

    /** @param list<string|null>|null $requiredCredentials */
    public static function create(string $id, string $teamId, ?string $name, ?string $childrenRole, ?array $requiredCredentials): self
    {
        return new self($id, $teamId, $name, (string) $childrenRole, true, $requiredCredentials);
    }

    /** @param list<string> $requiredCredentials */
    public static function reconstitute(string $id, string $teamId, string $name, string $childrenRole, bool $active, array $requiredCredentials): self
    {
        return new self($id, $teamId, $name, $childrenRole, $active, $requiredCredentials);
    }

    /** @param list<string|null>|null $requiredCredentials */
    public function change(?string $name, ?string $childrenRole, bool $active, ?array $requiredCredentials): self
    {
        if ($childrenRole !== $this->childrenRole) {
            throw VolunteerException::conflict('Create a separate role for a different children responsibility. Existing approvals must retain their scope.');
        }

        return new self($this->id, $this->teamId, $name, $this->childrenRole, $active, $requiredCredentials);
    }
}
