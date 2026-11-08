<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Giving\Application\Contract\DonorIdentity;
use App\Giving\Application\Contract\DonorIdentityGuard;
use App\Giving\Application\Contract\OnlineFundDirectory;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;

/**
 * Payments authorization (Java `PaymentAccess`). Staff paths need explicit permissions for the trusted caller; donor
 * self-service needs the caller's own verified donor link, taken under its lock (`DonorIdentityGuard`) inside commands.
 * Another donor's order is reported as missing, never forbidden.
 */
final readonly class PaymentAccess
{
    public function __construct(
        private PermissionChecker $auth,
        private DonorIdentity $donors,
        private DonorIdentityGuard $guard,
        private OnlineFundDirectory $funds,
    ) {
    }

    public function start(string $actor, string $donor, string $fund, bool $self, bool $locked): void
    {
        if (!$self) {
            $this->manage($actor);

            return;
        }
        if ($locked) {
            $this->guard->lockOwn($actor, $donor);
        } else {
            $this->donors->requireOwn($actor, $donor);
        }
        if (!$this->funds->enabled($fund)) {
            throw PaymentException::invalid('This fund is not currently offered online.');
        }
    }

    public function order(string $actor, PaymentOrder $order, bool $self, bool $locked): void
    {
        if (!$self) {
            $this->manage($actor);

            return;
        }
        if ($this->donors->requireOwn($actor, null) !== $order->donorId) {
            throw PaymentException::missing();
        }
        if ($locked) {
            $this->guard->lockOwn($actor, $order->donorId);
        } else {
            $this->donors->requireOwn($actor, $order->donorId);
        }
    }

    public function caller(string $actor, bool $self): void
    {
        if (!$self) {
            $this->manage($actor);

            return;
        }
        $this->donors->requireOwn($actor, null);
    }

    public function configureRead(): void
    {
        $this->auth->requirePermission('payments.configure');
    }

    public function configure(string $actor): void
    {
        $this->auth->requireActorPermission($actor, 'payments.configure');
    }

    public function receiptsRead(): void
    {
        $this->read();
        $this->auth->requirePermission('payments.receipts.send');
    }

    public function receipts(string $actor): void
    {
        $this->manage($actor);
        $this->auth->requireActorPermission($actor, 'payments.receipts.send');
    }

    public function read(): void
    {
        $this->auth->requirePermission('payments.manage');
    }

    public function manage(string $actor): void
    {
        $this->auth->requireActorPermission($actor, 'payments.manage');
    }

    public function refund(string $actor): void
    {
        $this->manage($actor);
        $this->auth->requireActorPermission($actor, 'payments.refund');
    }

    public function reconcile(string $actor): void
    {
        $this->manage($actor);
        $this->auth->requireActorPermission($actor, 'payments.reconcile');
    }
}
