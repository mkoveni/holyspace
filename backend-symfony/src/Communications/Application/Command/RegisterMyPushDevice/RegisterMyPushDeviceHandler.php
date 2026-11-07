<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\RegisterMyPushDevice;

use App\Communications\Application\Contract\PushSettings;
use App\Communications\Application\Contract\PushTokens;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\PushDevice;
use App\Communications\Domain\Repository\PushDeviceRepository;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\IdentityAccess\Application\Contract\CurrentActor;
use App\IdentityAccess\Application\Contract\SessionReader;
use App\IdentityAccess\Application\Contract\TokenCodec;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Enables or renews push for this installation. The account and session come from the trusted caller, never the
 * request; the installation's private secret proves a renewal comes from the same install, and moving an installation
 * to another account needs the previous account's session to have ended. Lock order: the device row only.
 */
final readonly class RegisterMyPushDeviceHandler implements CommandHandler
{
    public function __construct(
        private AuthenticatedAccount $account,
        private CurrentActor $actor,
        private SessionReader $sessions,
        private PushDeviceRepository $devices,
        private TokenCodec $hashes,
        private PushTokens $tokens,
        private PushSettings $settings,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RegisterMyPushDeviceCommand $command): void
    {
        $account = $this->account->requireAccount();
        $session = $this->actor->current()->sessionId ?? throw CommunicationException::forbidden();
        PushDevice::requireValidRegistration($command->installationSecret, $command->token, $command->platform);
        $project = strtolower($command->projectId);
        if (!$this->settings->enabled() || !$this->tokens->available() || $this->settings->projectId() !== $project) {
            throw CommunicationException::conflict('Your church has not completed device push setup.');
        }

        $now = $this->clock->now();
        $current = $this->sessions->findById($session);
        if (null === $current || $current->accountId !== $account || $current->expiresAt <= $now) {
            throw CommunicationException::forbidden();
        }
        $draft = PushDevice::register(
            $command->deviceId,
            $account,
            $session,
            $this->hashes->hash($command->installationSecret),
            $this->hashes->hash($command->token),
            $this->tokens->encrypt($command->token),
            $project,
            $command->platform,
            PushDevice::lease($current->expiresAt, $now),
            $now,
        );
        $this->devices->ensure($draft);
        $existing = $this->devices->lock($command->deviceId) ?? throw CommunicationException::missing('Push device');
        $this->account->requireAccount();
        if (!$existing->heldBy($draft->secretHash)) {
            throw CommunicationException::forbidden();
        }
        if ($existing->accountId !== $account) {
            $previous = $this->sessions->findById($existing->sessionId);
            if (null !== $previous && $previous->expiresAt > $now) {
                throw CommunicationException::conflict('Sign out of the previous account before enabling push on this device.');
            }
        }
        $this->devices->save($existing->renew($draft));
        $this->account->requireAccount();
    }
}
