<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/** The boundary rules must demonstrably catch violations, or the architecture tests prove nothing. */
final class BoundaryRulesTest extends TestCase
{
    public function testContextMayUseOwnClassesSharedAndPublishedContracts(): void
    {
        $source = <<<'PHP'
            use App\People\Domain\Person;
            use App\Shared\Domain\Revision;
            use App\IdentityAccess\Application\Contract\PermissionChecker;
            use App\ChildrenMinistry\Sessions\Application\Contract\ActivitySource;
            use Symfony\Component\Uid\Uuid;
            PHP;

        self::assertSame([], BoundaryRules::contextViolations('People', $source));
    }

    public function testContextCannotReachIntoAnotherContextsInternals(): void
    {
        $source = <<<'PHP'
            use App\IdentityAccess\Application\Authorization\Authorization;
            use App\IdentityAccess\Presentation\Caller;
            use App\ChildrenMinistry\Sessions\Domain\Session;
            use App\ChildrenMinistry\Sessions\Application\Command\CheckIn\CheckInHandler;
            PHP;

        self::assertSame([
            'use App\IdentityAccess\Application\Authorization\Authorization',
            'use App\IdentityAccess\Presentation\Caller',
            'use App\ChildrenMinistry\Sessions\Domain\Session',
            'use App\ChildrenMinistry\Sessions\Application\Command\CheckIn\CheckInHandler',
        ], BoundaryRules::contextViolations('People', $source));
    }

    public function testModuleMayUseSiblingDomainContractsAndTheKernel(): void
    {
        $source = <<<'PHP'
            use App\ChildrenMinistry\Participation\Domain\Enrolment;
            use App\ChildrenMinistry\Participation\Domain\ValueObject\ClassId;
            use App\ChildrenMinistry\Safeguarding\Application\Contract\GuardianAuthority;
            use App\ChildrenMinistry\Shared\Domain\ChildrenException;
            use App\ChildrenMinistry\Sessions\Application\Query\GetSession\GetSessionHandler;
            PHP;

        self::assertSame([], BoundaryRules::moduleViolations('ChildrenMinistry', 'Sessions', $source), 'the last import is the module\'s own handler');
    }

    public function testModuleCannotUseSiblingAdaptersHandlersOrPresentation(): void
    {
        $source = <<<'PHP'
            use App\ChildrenMinistry\Participation\Infrastructure\Persistence\DbalEnrolmentStore;
            use App\ChildrenMinistry\Safeguarding\Application\Command\GrantConsent\GrantConsentHandler;
            use App\ChildrenMinistry\Safeguarding\Presentation\ConsentController;
            use App\ChildrenMinistry\Safeguarding\Application\Query\GetConsent\GetConsentQuery;
            PHP;

        self::assertCount(4, BoundaryRules::moduleViolations('ChildrenMinistry', 'Sessions', $source));
    }

    public function testTheKernelModuleDependsOnNoSiblingModule(): void
    {
        $source = 'use App\ChildrenMinistry\Participation\Domain\Enrolment;';

        self::assertSame(['use App\ChildrenMinistry\Participation\Domain\Enrolment'], BoundaryRules::moduleViolations('ChildrenMinistry', 'Shared', $source));
    }

    public function testImportsOfOtherContextsAreNotModuleViolations(): void
    {
        self::assertSame([], BoundaryRules::moduleViolations('ChildrenMinistry', 'Sessions', 'use App\People\Domain\Person;'));
    }
}
