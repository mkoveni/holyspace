<?php

declare(strict_types=1);

namespace App\Communications\Domain;

use DateTimeImmutable;

/**
 * One app installation's push registration, bound to the account and session that registered it and leased until the
 * earlier of that session's expiry and 30 days. Its revision changes whenever what a queued job was aimed at changes,
 * so stale jobs are cancelled rather than sent. The token is only ever held encrypted.
 */
final readonly class PushDevice
{
    public const string ACTIVE = 'ACTIVE';
    public const string REVOKED = 'REVOKED';
    public const string INVALID = 'INVALID';

    private const int MAX_LEASE_SECONDS = 2592000;

    private function __construct(
        public string $id,
        public string $accountId,
        public string $sessionId,
        public string $secretHash,
        public string $tokenHash,
        public string $encryptedToken,
        public string $projectId,
        public string $platform,
        public string $status,
        public int $revision,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $updatedAt,
    ) {
        if (1 !== preg_match('/\A[a-f0-9]{64}\z/', $secretHash) || 1 !== preg_match('/\A[a-f0-9]{64}\z/', $tokenHash)
            || '' === trim($encryptedToken) || $revision < 0 || !in_array($platform, ['android', 'ios'], true)
            || !in_array($status, [self::ACTIVE, self::REVOKED, self::INVALID], true)) {
            throw CommunicationException::invalid('Invalid push registration.');
        }
    }

    /** Checks what the installation sent before anything is hashed, encrypted or stored. */
    public static function requireValidRegistration(string $installationSecret, string $token, string $platform): void
    {
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{43,100}\z/', $installationSecret)
            || 1 !== preg_match('/\A(?:Exponent|Expo)PushToken\[[A-Za-z0-9_-]{10,200}\]\z/', $token)
            || !in_array($platform, ['android', 'ios'], true)) {
            throw CommunicationException::invalid('Choose a valid device push registration.');
        }
    }

    /** The lease ends with the issuing session, and never more than 30 days ahead. */
    public static function lease(DateTimeImmutable $sessionExpiry, DateTimeImmutable $now): DateTimeImmutable
    {
        $cap = $now->modify('+'.self::MAX_LEASE_SECONDS.' seconds');

        return $sessionExpiry < $cap ? $sessionExpiry : $cap;
    }

    public static function register(
        string $id,
        string $accountId,
        string $sessionId,
        string $secretHash,
        string $tokenHash,
        string $encryptedToken,
        string $projectId,
        string $platform,
        DateTimeImmutable $lease,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $accountId, $sessionId, $secretHash, $tokenHash, $encryptedToken, $projectId, $platform, self::ACTIVE, 0, $lease, $now);
    }

    public static function reconstitute(
        string $id,
        string $accountId,
        string $sessionId,
        string $secretHash,
        string $tokenHash,
        string $encryptedToken,
        string $projectId,
        string $platform,
        string $status,
        int $revision,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $accountId, $sessionId, $secretHash, $tokenHash, $encryptedToken, $projectId, $platform, $status, $revision, $expiresAt, $updatedAt);
    }

    public function active(DateTimeImmutable $at): bool
    {
        return self::ACTIVE === $this->status && $this->expiresAt > $at;
    }

    /** Constant-time check that the caller holds this installation's private secret. */
    public function heldBy(string $secretHash): bool
    {
        return hash_equals($this->secretHash, $secretHash);
    }

    /**
     * Renews the registration from a fresh draft of the same installation (its secret is kept). The revision only moves
     * when the account, session, token, project, platform or active state changes, so a plain renewal keeps queued jobs.
     */
    public function renew(self $draft): self
    {
        $same = $this->accountId === $draft->accountId && $this->sessionId === $draft->sessionId
            && $this->tokenHash === $draft->tokenHash && $this->projectId === $draft->projectId
            && $this->platform === $draft->platform && self::ACTIVE === $this->status;

        return new self(
            $this->id,
            $draft->accountId,
            $draft->sessionId,
            $this->secretHash,
            $draft->tokenHash,
            $draft->encryptedToken,
            $draft->projectId,
            $draft->platform,
            self::ACTIVE,
            $same ? $this->revision : $this->revision + 1,
            $draft->expiresAt,
            $draft->updatedAt,
        );
    }

    /** @param self::REVOKED|self::INVALID $state */
    public function revoke(string $state, DateTimeImmutable $at): self
    {
        return new self($this->id, $this->accountId, $this->sessionId, $this->secretHash, $this->tokenHash, $this->encryptedToken, $this->projectId, $this->platform, $state, $this->revision + 1, $this->expiresAt, $at);
    }

    /** @return array<string, string> never the token, its hash or the installation secret */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'status' => $this->status];
    }
}
