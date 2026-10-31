<?php

declare(strict_types=1);

namespace App\Media\Application\Command\ChangePortalImage;

use App\Media\Application\ImageTargets;
use App\Media\Application\Port\ImageProcessor;
use App\Media\Domain\ImageStorage;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ChangePortalImageHandler
{
    private const CONTEXTS = ['person' => 'people', 'family' => 'families', 'event' => 'events', 'form' => 'forms'];

    public function __construct(
        private ImageStorage $storage,
        private ImageTargets $targets,
        private ImageProcessor $processor,
        private WorkflowTransaction $transactions,
        private AccessControl $access,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(ChangePortalImageCommand $message): array
    {
        $target = $message->target;
        $id = $message->id;
        $file = $message->file;
        $expectedVersion = $message->expectedVersion;
        $this->authorize($target, $id, 'manage');
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Invalid image version.');
        }
        $this->targets->require($target, $id);
        $image = $file ? $this->processor->process($file) : null;

        return $this->transactions->run(function () use ($target, $id, $image, $expectedVersion) {
            $this->targets->require($target, $id);

            return $this->storage->save($target, $id, $image, $expectedVersion);
        });
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
