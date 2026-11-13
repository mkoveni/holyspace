<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetMemberForm;

use App\Forms\Application\AudienceGate;
use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\FormPage;
use App\Forms\Application\FormPages;
use App\Forms\Domain\Audience;
use App\Forms\Domain\FormException;
use Psr\Clock\ClockInterface;

final readonly class GetMemberFormHandler
{
    public function __construct(private FormReader $reader, private AudienceGate $gate, private ClockInterface $clock)
    {
    }

    public function handle(GetMemberFormQuery $q): FormPage
    {
        $now = $this->clock->now();
        $form = $this->reader->published($q->formId, $now);
        if (null === $form || Audience::Public->value === $form->audience) {
            throw FormException::missing();
        }
        $this->gate->require(Audience::from($form->audience), $form->groupIds, $q->accountId, $now);

        return FormPages::of($form, $now, null);
    }
}
