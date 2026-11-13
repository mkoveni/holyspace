<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\ExportResponses;

use App\Forms\Application\Contract\FormReader;
use App\Forms\Application\FormAccess;
use App\Forms\Domain\FormException;
use App\Forms\Domain\IdentityMode;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Every stored response of a form. The export is audited once, like reading an identified form's responses. */
final readonly class ExportResponsesHandler implements CommandHandler
{
    private const PAGE = 200;

    public function __construct(private FormAccess $access, private FormReader $reader, private FormRepository $forms, private ClockInterface $clock)
    {
    }

    public function handle(ExportResponsesCommand $c): ResponseExport
    {
        $this->access->readResponses($c->actorId);
        $form = $this->reader->find($c->formId) ?? throw FormException::missing();
        $this->forms->audit($form->id, 'RESPONSES_EXPORTED', (string) $form->responseCount, $c->actorId, $this->clock->now());

        $columns = [];
        for ($v = 1; $v <= $form->publishedVersion; ++$v) {
            $definition = $this->reader->publishedDefinition($form->id, $v);
            foreach (is_array($definition['questions'] ?? null) ? $definition['questions'] : [] as $q) {
                if (is_array($q) && is_string($q['id'] ?? null)) {
                    $columns[$q['id']] = is_string($q['label'] ?? null) ? $q['label'] : $q['id'];
                }
            }
        }
        $items = [];
        for ($offset = 0; $offset < $form->responseCount; $offset += self::PAGE) {
            $page = $this->reader->responses($form->id, self::PAGE, $offset);
            if ([] === $page) {
                break;
            }
            array_push($items, ...$page);
        }

        return new ResponseExport($form->title, IdentityMode::Identified->value === $form->identity, $columns, $items);
    }
}
