<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Shared\Domain\ContextException;
use App\Shared\Infrastructure\Persistence\ConnectionFactory;
use Doctrine\DBAL\Connection;
use RuntimeException;
use Throwable;

/**
 * A second database session in a forked process, so a test can hold locks in its own session while this one runs a use
 * case to the end: the child opens its own connection and transaction, blocks on the parent's locks, and finishes once the
 * parent commits. The outcome is reported as `ok:<result>`, `error:<ErrorKind>:<message>` or `crash:<class>:<message>`.
 */
final class ParallelSession
{
    private const int WAIT_MICROSECONDS = 20_000;

    private bool $finished = false;

    /** @param non-empty-string $backendFile @param non-empty-string $outcomeFile */
    private function __construct(private readonly int $pid, private readonly string $backendFile, private readonly string $outcomeFile)
    {
    }

    /** @param callable(Connection): string $operation runs inside the child's transaction */
    public static function start(callable $operation): self
    {
        $backendFile = self::temporaryFile();
        $outcomeFile = self::temporaryFile();
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new RuntimeException('Could not fork a second database session.');
        }
        if (0 === $pid) {
            self::runChild($operation, $backendFile, $outcomeFile);
        }

        return new self($pid, $backendFile, $outcomeFile);
    }

    /**
     * True once the child's backend is waiting for a lock; false if it finished first or never waited, which means the use
     * case did not serialise on the parent's locks.
     */
    public function awaitsLock(Connection $observer, float $seconds = 5.0): bool
    {
        $deadline = microtime(true) + $seconds;
        while (microtime(true) < $deadline) {
            if ($this->exited()) {
                return false;
            }
            $backend = (string) file_get_contents($this->backendFile);
            if ('' !== $backend && 'Lock' === $observer->fetchOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [(int) $backend])) {
                return true;
            }
            usleep(self::WAIT_MICROSECONDS);
        }

        return false;
    }

    /** Waits for the child to finish and returns its outcome. */
    public function outcome(): string
    {
        if (!$this->finished) {
            pcntl_waitpid($this->pid, $status);
            $this->finished = true;
        }
        $outcome = (string) file_get_contents($this->outcomeFile);
        @unlink($this->backendFile);
        @unlink($this->outcomeFile);

        return $outcome;
    }

    private function exited(): bool
    {
        if (!$this->finished && $this->pid === pcntl_waitpid($this->pid, $status, \WNOHANG)) {
            $this->finished = true;
        }

        return $this->finished;
    }

    /** @return non-empty-string */
    private static function temporaryFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'giving-session-');
        if (false === $file) {
            throw new RuntimeException('Could not create a temporary file.');
        }

        return $file;
    }

    /** @param callable(Connection): string $operation */
    private static function runChild(callable $operation, string $backendFile, string $outcomeFile): never
    {
        try {
            $connection = ConnectionFactory::create((string) getenv('DATABASE_URL'));
            $connection->executeStatement("SET lock_timeout = '10s'");
            file_put_contents($backendFile, (string) $connection->fetchOne('SELECT pg_backend_pid()'));
            try {
                $outcome = 'ok:'.$connection->transactional(static fn (): string => $operation($connection));
            } finally {
                $connection->close();
            }
        } catch (ContextException $error) {
            $outcome = 'error:'.$error->kind()->name.':'.$error->getMessage();
        } catch (Throwable $error) {
            $outcome = 'crash:'.$error::class.':'.$error->getMessage();
        }
        file_put_contents($outcomeFile, $outcome);
        // SIGKILL skips PHPUnit's shutdown handlers and the parent's inherited connection, which the child must not close.
        posix_kill(posix_getpid(), \SIGKILL);
        exit(1);
    }
}
