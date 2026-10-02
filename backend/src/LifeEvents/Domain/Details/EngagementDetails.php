<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Details;
use DateTimeImmutable;
final readonly class EngagementDetails implements LifeEventDetails
{
    public function __construct(public ?DateTimeImmutable $expectedMarriageDate=null, public ?string $venue=null) {}
    public function toArray(): array { return ['expectedMarriageDate'=>$this->expectedMarriageDate?->format('Y-m-d'),'venue'=>$this->venue]; }
    public static function fromArray(array $data): self {
        $date=$data['expectedMarriageDate']??null; $venue=$data['venue']??null;
        return new self($date?new DateTimeImmutable((string)$date):null, $venue===null?null:(trim((string)$venue)===''?null:trim((string)$venue)));
    }
}
