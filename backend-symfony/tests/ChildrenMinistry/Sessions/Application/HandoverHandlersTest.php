<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Command\CheckOutChild\CheckOutChildCommand;
use App\ChildrenMinistry\Sessions\Application\Command\CheckOutChild\CheckOutChildHandler;
use App\ChildrenMinistry\Sessions\Application\Command\SupervisorCheckout\SupervisorCheckoutCommand;
use App\ChildrenMinistry\Sessions\Application\Command\SupervisorCheckout\SupervisorCheckoutHandler;
use App\ChildrenMinistry\Sessions\Application\HandoverOutcome;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Domain\ErrorKind;
use LogicException;

/** A child leaves only with a verified adult holding a current grant and the right code, or a supervisor's recorded reason. */
final class HandoverHandlersTest extends AttendanceScenario
{
    private const string VISIT = 'b0000000-0000-4000-8000-000000000001';
    private const string CHECKOUT = '70000000-0000-4000-8000-000000000001';
    private const string CODE = 'right-code';

    /** @param list<string> $permissions */
    private function checkOut(array $permissions = ['children.station'], string $collectorBirth = '1985-01-01'): CheckOutChildHandler
    {
        return new CheckOutChildHandler($this->permissions($permissions), $this->handover($permissions, $collectorBirth));
    }

    private function command(?string $code = self::CODE, int $version = 1, bool $verified = true, string $request = self::CHECKOUT, string $actor = self::STATION): CheckOutChildCommand
    {
        return new CheckOutChildCommand($actor, self::VISIT, $version, $request, self::COLLECTOR, $verified, $code);
    }

    private function refusal(HandoverOutcome $outcome): ChildrenException
    {
        return $outcome->refusal() ?? throw new LogicException('Expected the attempt to be refused.');
    }

    public function testACorrectCodeReleasesTheChildInTheDocumentedLockOrder(): void
    {
        $this->present(self::CODE);

        $view = $this->checkOut()->handle($this->command())->attendance();

        self::assertSame([
            'read:attendance', 'person:'.self::CHILD, 'person:'.self::COLLECTOR, 'guard:'.self::CHILD, 'forUpdate:session', 'forUpdate:attendance',
            'update:attendance', 'close:segment', 'insert:event:COLLECTED', 'audit:attendance.collected',
        ], $this->log->entries);
        self::assertSame([self::COLLECTOR, self::GRANT, true, false, 2], [$view->collectorId, $view->authorizationId, $view->identityVerified, $view->exceptionUsed, $view->version]);
        self::assertNotNull($view->checkedOutAt);
        self::assertNull($this->world->attendances[self::VISIT]->labelHash, 'the label dies with the visit');
    }

    public function testPermissionIsCheckedBeforeAnythingIsRead(): void
    {
        $this->present(self::CODE);

        try {
            $this->checkOut(['children.read'])->handle($this->command());
            self::fail('Expected a refusal.');
        } catch (IdentityException) {
        }

        self::assertSame([], $this->log->entries);
    }

    public function testAWrongCodeIsRecordedAsAFailedAttemptAndReturnedNotThrown(): void
    {
        $this->present(self::CODE);

        $outcome = $this->checkOut()->handle($this->command('wrong'));

        self::assertSame(ErrorKind::Invalid, $this->refusal($outcome)->kind());
        self::assertSame('Pickup code is invalid or expired. Reload attendance before retrying.', $this->refusal($outcome)->getMessage());
        self::assertSame(['update:attendance', 'audit:pickup.label.rejected'], array_slice($this->log->entries, -2), 'the failure is saved so it survives the commit');
        self::assertSame([1, 2], [$this->world->attendances[self::VISIT]->failedAttempts, $this->world->attendances[self::VISIT]->version]);
        self::assertNull($this->world->attendances[self::VISIT]->checkedOutAt);
        try {
            $outcome->attendance();
            self::fail('The caller must be able to raise the refusal.');
        } catch (ChildrenException $raised) {
            self::assertSame($this->refusal($outcome), $raised);
        }
    }

    public function testTheFifthWrongCodeLocksHandoverEvenForTheRightCode(): void
    {
        $this->present(self::CODE);
        $handler = $this->checkOut();
        for ($i = 1; $i <= 4; ++$i) {
            $outcome = $handler->handle($this->command('wrong', $this->world->attendances[self::VISIT]->version));
            self::assertSame(ErrorKind::Invalid, $this->refusal($outcome)->kind(), "attempt $i");
        }

        $fifth = $handler->handle($this->command('wrong', $this->world->attendances[self::VISIT]->version));
        self::assertSame([ErrorKind::Throttled, 'Wait before trying another pickup code.'], [$this->refusal($fifth)->kind(), $this->refusal($fifth)->getMessage()]);

        $this->log->entries = [];
        $locked = $handler->handle($this->command(self::CODE, $this->world->attendances[self::VISIT]->version));
        self::assertSame(ErrorKind::Throttled, $this->refusal($locked)->kind());
        self::assertNotContains('update:attendance', $this->log->entries, 'a locked attempt changes nothing');

        $this->clock->modify('+16 minutes');
        $released = $handler->handle($this->command(self::CODE, $this->world->attendances[self::VISIT]->version));
        self::assertNull($released->refusal());
        self::assertNotNull($released->attendance()->checkedOutAt);
    }

    public function testMissingAndExpiredLabelsAreRefusedLikeWrongCodes(): void
    {
        $this->present();
        self::assertSame(ErrorKind::Invalid, $this->refusal($this->checkOut()->handle($this->command(version: 0)))->kind(), 'no label issued');
        self::assertNull($this->world->attendances[self::VISIT]->checkedOutAt);

        $this->clock->modify('+25 hours');
        $this->present(self::CODE);
        self::assertSame(ErrorKind::Invalid, $this->refusal($this->checkOut()->handle($this->command()))->kind(), 'expired label');
        self::assertSame(ErrorKind::Invalid, $this->refusal($this->checkOut()->handle($this->command(null, $this->world->attendances[self::VISIT]->version)))->kind(), 'no code supplied');
    }

    public function testIdentityMustBeConfirmedAndTheCollectorMustBeAnAdultWithAGrant(): void
    {
        $this->present(self::CODE);
        $this->refused('Confirm collector identity before handover.', fn () => $this->checkOut()->handle($this->command(verified: false)));
        $this->refused('A recorded birth date confirming adulthood is required.', fn () => $this->checkOut(collectorBirth: '2012-01-01')->handle($this->command()));

        $grant = $this->safeguarding->grants;
        $this->safeguarding->grants = [];
        $this->refused('A current pickup authorization and guardian authority are required.', fn () => $this->checkOut()->handle($this->command()));

        $this->safeguarding->grants = $grant;
        $this->safeguarding->authorities = [];
        $this->refused('A current pickup authorization and guardian authority are required.', fn () => $this->checkOut()->handle($this->command()));
        self::assertNull($this->world->attendances[self::VISIT]->checkedOutAt);
        self::assertSame(0, $this->world->attendances[self::VISIT]->failedAttempts, 'a missing grant is not a code failure');
    }

    public function testAStaleVersionIsAConflictBeforeTheCodeIsTried(): void
    {
        $this->present(self::CODE);

        $this->refused('This record changed. Reload before trying again.', fn () => $this->checkOut()->handle($this->command(version: 0)));

        self::assertSame(0, $this->world->attendances[self::VISIT]->failedAttempts);
    }

    public function testRetryingTheSameCollectionIsIdempotentButAnotherIsRefused(): void
    {
        $this->present(self::CODE);
        $first = $this->checkOut()->handle($this->command())->attendance();
        $this->log->entries = [];

        $again = $this->checkOut()->handle($this->command(code: null, version: 1))->attendance();

        self::assertSame($first->checkedOutAt?->format('c'), $again->checkedOutAt?->format('c'));
        self::assertNotContains('update:attendance', $this->log->entries);
        $this->refused('Child has already been collected.', fn () => $this->checkOut()->handle($this->command(request: '70000000-0000-4000-8000-000000000002')));
    }

    public function testStationStaffOutsideTheSessionCannotHandOver(): void
    {
        $this->present(self::CODE);

        $this->refused('This operation is outside your assigned session.', fn () => $this->checkOut()->handle($this->command(actor: 'stranger')));

        self::assertSame(['read:attendance', 'person:'.self::CHILD, 'person:'.self::COLLECTOR, 'guard:'.self::CHILD, 'forUpdate:session'], $this->log->entries);
    }

    public function testASupervisorReleasesWithoutACodeButMustGiveAReason(): void
    {
        $this->present();
        $handler = new SupervisorCheckoutHandler($this->permissions(['children.supervise']), $this->handover(['children.supervise']));
        $command = fn (?string $reason): SupervisorCheckoutCommand => new SupervisorCheckoutCommand(self::STATION, self::VISIT, 0, self::CHECKOUT, self::COLLECTOR, true, $reason);

        foreach ([null, '', '  ', str_repeat('x', 501)] as $reason) {
            $this->refused('A value is missing or exceeds its maximum length of 500.', fn () => $handler->handle($command($reason)));
        }
        self::assertSame([], array_filter($this->log->entries, static fn (string $entry): bool => str_starts_with($entry, 'update:')));

        $view = $handler->handle($command('Phone dead; parent known to staff'))->attendance();

        self::assertTrue($view->exceptionUsed);
        self::assertSame(['insert:event:SUPERVISOR_COLLECTION', 'audit:attendance.supervisor_collection'], array_slice($this->log->entries, -2));
        self::assertSame('Phone dead; parent known to staff', $this->world->events[0]->reason);
        self::assertSame(0, $this->world->attendances[self::VISIT]->failedAttempts);
    }

    public function testSupervisorReleaseStillNeedsAGrantAndTheSupervisePermission(): void
    {
        $this->present();
        $handler = new SupervisorCheckoutHandler($this->permissions(['children.supervise']), $this->handover(['children.supervise']));
        $this->safeguarding->grants = [];

        $this->refused('A current pickup authorization and guardian authority are required.', fn () => $handler->handle(new SupervisorCheckoutCommand(self::STATION, self::VISIT, 0, self::CHECKOUT, self::COLLECTOR, true, 'Reason')));

        $station = new SupervisorCheckoutHandler($this->permissions(['children.station']), $this->handover(['children.station']));
        $this->log->entries = [];
        $this->expectException(IdentityException::class);
        $station->handle(new SupervisorCheckoutCommand(self::STATION, self::VISIT, 0, self::CHECKOUT, self::COLLECTOR, true, 'Reason'));
    }
}
