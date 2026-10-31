<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\ListFormsRecords;

use App\Forms\Application\Query\GetForm\GetFormHandler;
use App\Forms\Application\Query\GetForm\GetFormQuery;
use App\Forms\Application\ReadModel\FormsReadStore;
use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final readonly class ListFormsRecordsHandler
{
    public function __construct(private FormsReadStore $records, private GetFormHandler $getFormHandler)
    {
    }

    public function __invoke(ListFormsRecordsQuery $message): array
    {
        $kind = $message->kind;
        $formId = $message->formId;
        $after = $message->after;
        $limit = $message->limit;
        if (null !== $formId) {
            ($this->getFormHandler)(new GetFormQuery($formId));
        }
        if (null !== $after) {
            Input::id($after);
        }
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Invalid page size.');
        }
        $items = $this->records->page($kind, $formId, $after, $limit + 1);
        $more = count($items) > $limit;
        if ($more) {
            array_pop($items);
        }

        return ['items' => $items, 'nextAfter' => $more ? end($items)->id : null];
    }
}
