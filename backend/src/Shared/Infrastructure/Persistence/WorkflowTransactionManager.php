<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\WorkflowTransaction;
use Doctrine\DBAL\Connection;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Lock\LockFactory;

#[AsAlias(WorkflowTransaction::class)]
final class WorkflowTransactionManager implements WorkflowTransaction
{
    private int $depth = 0;

    public function __construct(private readonly Connection $db, private readonly LockFactory $locks, private readonly RequestStack $requests)
    {
    }

    public function run(callable $operation): mixed
    {
        if ($this->depth > 0) {
            return $this->db->transactional($operation);
        }
        $lock = $this->locks->createLock('onechurch-operational-workflows', 60);

        $lock->acquire(true);
        try {
            return $this->db->transactional(function () use ($operation, $lock) {
                $this->checkVersion();
                ++$this->depth;
                try {
                    $result = $operation();
                } finally {
                    --$this->depth;
                }
                if (!$lock->isAcquired() || $lock->isExpired()) {
                    throw new DomainException('The workflow lock was lost. Changes were rolled back; reload and retry.');
                }

                return $result;
            });
        } finally {
            $lock->release();
        }
    }

    private function checkVersion(): void
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request || $request->isMethodSafe() || !$request->headers->has('If-Match')) {
            return;
        }
        $id = $request->attributes->get('id');
        if (!is_string($id)) {
            return;
        }
        $context = explode('/', trim($request->getPathInfo(), '/'))[1] ?? '';
        $tables = ['volunteering' => 'volunteering_records', 'children' => 'childrenministry_records', 'giving' => 'giving_records', 'attendance' => 'attendance_records', 'ministries' => 'ministries_records', 'communications' => 'communications_records'];
        if (!isset($tables[$context])) {
            return;
        }
        $header = $request->headers->get('If-Match');
        if (!preg_match('/^"([1-9][0-9]*)"$/D', $header, $matches)) {
            throw new InvalidArgumentException('If-Match must contain a quoted positive record version.');
        }
        $version = $this->db->fetchOne('SELECT version FROM '.$tables[$context].' WHERE id = ?', [$id]);
        if (false === $version) {
            throw new OutOfBoundsException('Record not found.');
        }
        if ((string) $version !== $matches[1]) {
            throw new DomainException('This record changed. Reload it before trying again.');
        }
    }
}
