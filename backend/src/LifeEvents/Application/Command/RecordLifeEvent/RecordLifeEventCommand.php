<?php
declare(strict_types=1);
namespace App\LifeEvents\Application\Command\RecordLifeEvent;
final readonly class RecordLifeEventCommand { public function __construct(public string $type,public array $participants,public string $eventDate,public array $details=[],public ?string $notes=null){} }
