<?php

declare(strict_types=1);

namespace App\Tests\Media;

use App\Events\Domain\Repository\EventRepository;
use App\Forms\Application\Query\GetForm\GetFormHandler;
use App\Forms\Application\Query\GetPublicForm\GetPublicFormHandler;
use App\Forms\Application\ReadModel\FormsReadStore;
use App\Forms\Domain\Model\ChurchForm;
use App\Media\Infrastructure\PortalImageTargets;
use App\People\Application\ReadModel\PersonDirectory;
use App\People\Domain\Repository\FamilyRepository;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PublicEventImageAccessTest extends TestCase
{
    public function testEventImagesRequireAPublishedFormWithAnEventLink(): void
    {
        $event = Uuid::v7()->toRfc4122();
        $occurrence = Uuid::v7()->toRfc4122();
        foreach ([[false, $event, $occurrence], [true, null, null], [true, $event, $occurrence]] as [$published, $eventId, $occurrenceId]) {
            $form = new ChurchForm('Welcome', [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]], published: $published, eventId: $eventId, occurrenceId: $occurrenceId);
            $records = $this->createStub(FormsReadStore::class);
            $records->method('publicForm')->willReturnCallback(static fn ($token) => $token === $form->publicToken ? $form : null);
            $targets = new PortalImageTargets($this->createStub(PersonDirectory::class), $this->createStub(FamilyRepository::class), $this->createStub(EventRepository::class), new GetFormHandler($records), new GetPublicFormHandler($records));
            if ($published && $eventId) {
                self::assertSame($event, $targets->publishedEventId($form->publicToken));
                try {
                    $targets->publishedEventId(str_repeat('0', 64));
                    self::fail('A different public token must not expose this event image.');
                } catch (OutOfBoundsException) {
                    self::assertTrue(true);
                }
            } else {
                try {
                    $targets->publishedEventId($form->publicToken);
                    self::fail('A general or unpublished form must not expose event images.');
                } catch (OutOfBoundsException) {
                    self::assertTrue(true);
                }
            }
        }
    }
}
