<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\Command\UpdateLifeEvent;
final readonly class UpdateLifeEventCommand { public function __construct(public string $lifeEventId,public string $type,public array $participants,public string $eventDate,public array $details=[],public ?string $notes=null){} }
