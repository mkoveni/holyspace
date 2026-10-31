<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetPublicForm;

use App\Forms\Application\ReadModel\FormsReadStore;
use App\Forms\Domain\Model\ChurchForm;
use OutOfBoundsException;

final readonly class GetPublicFormHandler
{
    public function __construct(private FormsReadStore $records)
    {
    }

    public function __invoke(GetPublicFormQuery $message): ChurchForm
    {
        $token = $message->token;
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new OutOfBoundsException('Form unavailable.');
        }
        $form = $this->records->publicForm($token);
        if (!$form || !$form->published) {
            throw new OutOfBoundsException('Form unavailable.');
        }

        return $form;
    }
}
