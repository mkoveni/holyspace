<?php

declare(strict_types=1);

namespace App\Communications\Presentation;

use App\Communications\Application\Command\SetMyNotificationPreference\SetMyNotificationPreferenceCommand;
use App\Communications\Application\Command\SetMyNotificationRead\SetMyNotificationReadCommand;
use App\Communications\Application\Contract\MemberNotification;
use App\Communications\Application\Contract\MemberNotificationPreference;
use App\Communications\Application\Query\GetMyNotification\GetMyNotificationHandler;
use App\Communications\Application\Query\GetMyNotification\GetMyNotificationQuery;
use App\Communications\Application\Query\GetMyNotificationPreferences\GetMyNotificationPreferencesHandler;
use App\Communications\Application\Query\GetMyNotificationPreferences\GetMyNotificationPreferencesQuery;
use App\Communications\Application\Query\ListMyNotifications\ListMyNotificationsHandler;
use App\Communications\Application\Query\ListMyNotifications\ListMyNotificationsQuery;
use App\Communications\Presentation\Request\NotificationPreferenceRequest;
use App\Communications\Presentation\Request\NotificationReadRequest;
use App\Shared\Application\CommandBus;
use App\Shared\Presentation\Json;
use App\Shared\Presentation\PathUuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A member's own inbox: any live account, scoped to itself; another account's notice is a 404. Notices carry fixed
 * wording and a fixed destination, never account ids, source keys or URLs.
 */
final readonly class MemberInboxController
{
    public function __construct(
        private CommandBus $commands,
        private ListMyNotificationsHandler $list,
        private GetMyNotificationHandler $notification,
        private GetMyNotificationPreferencesHandler $preferences,
    ) {
    }

    #[Route('/api/member/notifications', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL, validationFailedStatusCode: 400)] bool $unreadOnly = false,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $limit = 20,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, validationFailedStatusCode: 400)] int $offset = 0,
    ): Response {
        $inbox = $this->list->handle(new ListMyNotificationsQuery($unreadOnly, $limit, $offset));

        return self::private(new JsonResponse([
            'items' => array_map(self::notice(...), $inbox->items),
            'unreadCount' => $inbox->unreadCount,
            'hasMore' => $inbox->hasMore,
        ]));
    }

    /** Resolves a notice before a push is opened; it does not mark it read or grant access to the destination. */
    #[Route('/api/member/notifications/{id}', methods: ['GET'])]
    public function notification(#[PathUuid] string $id): Response
    {
        return self::private(new JsonResponse(self::notice($this->notification->handle(new GetMyNotificationQuery($id)))));
    }

    /** Idempotent desired state: reading again keeps the first read time. */
    #[Route('/api/member/notifications/{id}/read', methods: ['PUT'])]
    public function read(#[PathUuid] string $id, #[MapRequestPayload] NotificationReadRequest $request): Response
    {
        $this->commands->dispatch(new SetMyNotificationReadCommand($id, (bool) $request->read));

        return self::private(new Response(status: 204));
    }

    /** Defaults are enabled. Inbox preferences do not change email consent or push settings. */
    #[Route('/api/member/notification-preferences', methods: ['GET'])]
    public function preferences(): Response
    {
        return self::private(new JsonResponse(array_map(
            static fn (MemberNotificationPreference $p): array => ['topic' => $p->topic, 'enabled' => $p->enabled, 'version' => $p->version],
            $this->preferences->handle(new GetMyNotificationPreferencesQuery()),
        )));
    }

    /** Versioned; controls future notices only. */
    #[Route('/api/member/notification-preferences/{topic}', methods: ['PUT'])]
    public function preference(string $topic, #[MapRequestPayload] NotificationPreferenceRequest $request): Response
    {
        $this->commands->dispatch(new SetMyNotificationPreferenceCommand($topic, (bool) $request->enabled, $request->expectedVersion));

        return self::private(new Response(status: 204));
    }

    /** Java sets exactly `no-store` on the inbox, replacing the default cache headers. */
    private static function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** @return array<string, mixed> */
    private static function notice(MemberNotification $n): array
    {
        return [
            'id' => $n->id,
            'topic' => $n->topic,
            'title' => $n->title,
            'message' => $n->message,
            'destination' => $n->destination,
            'createdAt' => Json::instant($n->createdAt),
            'readAt' => null === $n->readAt ? null : Json::instant($n->readAt),
        ];
    }
}
