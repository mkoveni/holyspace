<?php

declare(strict_types=1);

namespace App\Media\Application\Query\GetPortalImage;

use App\Media\Application\ImageTargets;
use App\Media\Application\ReadModel\ImageReader;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final readonly class GetPortalImageHandler
{
    private const CONTEXTS = ['person' => 'people', 'family' => 'families', 'event' => 'events', 'form' => 'forms'];

    public function __construct(
        private ImageReader $storage,
        private ImageTargets $targets,
        private AccessControl $access,
    ) {
    }

    public function __invoke(GetPortalImageQuery $message): array
    {
        $target = $message->target;
        $id = $message->id;
        $content = $message->content;
        $this->authorize($target, $id, 'read');
        $this->targets->require($target, $id);

        return $this->storage->get($target, $id, $content);
    }

    private function authorize(string $target, string $id, string $operation): void
    {
        if (!isset(self::CONTEXTS[$target])) {
            throw new InvalidArgumentException('Unsupported image owner.');
        }
        if (!$this->access->isGranted(self::CONTEXTS[$target].'.'.$operation)) {
            throw new AccessDenied();
        }
        Input::id($id);
    }
}
