<?php

declare(strict_types=1);

namespace App\Communications\Presentation;

use App\Communications\Application\Command\RegisterMyPushDevice\RegisterMyPushDeviceCommand;
use App\Communications\Application\Command\RevokeMyPushDevice\RevokeMyPushDeviceCommand;
use App\Communications\Application\Command\SetMyPushPreference\SetMyPushPreferenceCommand;
use App\Communications\Application\Contract\PushDeviceView;
use App\Communications\Application\Contract\PushPreference;
use App\Communications\Application\Query\GetMyPushSettings\GetMyPushSettingsHandler;
use App\Communications\Application\Query\GetMyPushSettings\GetMyPushSettingsQuery;
use App\Communications\Presentation\Request\PushDeviceRegistrationRequest;
use App\Communications\Presentation\Request\PushTopicPreferenceRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A member's own device push: any live account, scoped to itself and the session that registered each device.
 * Responses never carry tokens, installation secrets or session ids, and are `no-store` as in Java.
 */
final readonly class MemberPushController
{
    public function __construct(private CommandBus $commands, private GetMyPushSettingsHandler $settings)
    {
    }

    /** Read-only: topics default off, and viewing never creates a registration or a job. */
    #[Route('/api/member/push', methods: ['GET'])]
    public function settings(): Response
    {
        $settings = $this->settings->handle(new GetMyPushSettingsQuery());

        return self::private(new JsonResponse([
            'available' => $settings->available,
            'projectId' => $settings->projectId,
            'preferences' => array_map(static fn (PushPreference $p): array => ['topic' => $p->topic, 'enabled' => $p->enabled, 'version' => $p->version], $settings->preferences),
            'devices' => array_map(static fn (PushDeviceView $d): array => [
                'id' => $d->id,
                'platform' => $d->platform,
                'status' => $d->status,
                'expiresAt' => Json::instant($d->expiresAt),
                'updatedAt' => Json::instant($d->updatedAt),
                'currentSession' => $d->currentSession,
            ], $settings->devices),
        ]));
    }

    /** Enables or renews this installation; the account and session come from the caller, never the body. */
    #[Route('/api/member/push/devices/{id}', methods: ['PUT'])]
    public function register(#[PathUuid] string $id, #[MapRequestPayload] PushDeviceRegistrationRequest $request): Response
    {
        $this->commands->dispatch(new RegisterMyPushDeviceCommand(
            $id,
            (string) $request->installationSecret,
            (string) $request->token,
            (string) $request->platform,
            (string) $request->projectId,
        ));

        return self::private(new Response(status: 204));
    }

    #[Route('/api/member/push/devices/{id}', methods: ['DELETE'])]
    public function revoke(#[PathUuid] string $id): Response
    {
        $this->commands->dispatch(new RevokeMyPushDeviceCommand($id));

        return self::private(new Response(status: 204));
    }

    #[Route('/api/member/push/preferences/{topic}', methods: ['PUT'])]
    public function preference(string $topic, #[MapRequestPayload] PushTopicPreferenceRequest $request): Response
    {
        $this->commands->dispatch(new SetMyPushPreferenceCommand($topic, (bool) $request->enabled, $request->expectedVersion));

        return self::private(new Response(status: 204));
    }

    private static function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
