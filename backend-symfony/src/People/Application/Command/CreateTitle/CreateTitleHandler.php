<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateTitle;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Application\Contract\TitleWriter;
use App\People\Domain\PeopleException;
use App\People\Domain\PeopleRules;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class CreateTitleHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private TitleWriter $titleWriter,
    ) {
    }

    public function handle(CreateTitleCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');
        $code = PeopleRules::titleCode($command->code) ?? throw PeopleException::invalid('Title code is required.');
        $label = PeopleRules::text($command->label, 'Title label', 40);

        $this->titleWriter->add($code, $label);
        $this->audit->audit($command->actorId, 'title.created', $code, $this->clock->now());
    }
}
