<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ExportResponses;

use App\Forms\Application\Contract\ResponseView;

final readonly class ResponseExport
{
    /**
     * @param list<ResponseView>    $items
     * @param array<string, string> $columns question id => label, across every published version
     */
    public function __construct(public string $title, public bool $identified, public array $columns, public array $items)
    {
    }
}
