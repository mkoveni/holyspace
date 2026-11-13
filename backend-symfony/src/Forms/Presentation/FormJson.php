<?php

declare(strict_types=1);

namespace App\Forms\Presentation;

use App\Forms\Application\FormDraft;
use App\Forms\Application\FormPage;
use App\Forms\Application\SubmitResult;
use App\Forms\Presentation\Request\FormRequest;
use App\Shared\Presentation\Json;
use Symfony\Component\HttpFoundation\JsonResponse;

final class FormJson
{
    private function __construct()
    {
    }

    public static function draft(FormRequest $b): FormDraft
    {
        return new FormDraft(
            $b->title,
            $b->description,
            $b->kind,
            $b->audience,
            $b->identity,
            null === $b->opensAt ? null : Json::parseInstant($b->opensAt),
            null === $b->closesAt ? null : Json::parseInstant($b->closesAt),
            $b->maxResponses,
            $b->retentionMonths,
            $b->leaderboardEnabled,
            $b->leaderboardSize,
            $b->showScore,
            $b->showAnswers,
            $b->groupIds ?? [],
            $b->definition ?? [],
        );
    }

    /** @return array<string, mixed> */
    public static function page(FormPage $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'description' => $p->description,
            'kind' => $p->kind,
            'identity' => $p->identity,
            'version' => $p->version,
            'questions' => $p->definition['questions'],
            'open' => $p->open,
            'retentionMonths' => $p->retentionMonths,
            'leaderboard' => $p->leaderboard,
            'challenge' => $p->challenge,
        ];
    }

    /** @return array<string, mixed> */
    public static function result(SubmitResult $r): array
    {
        return ['received' => true, 'score' => $r->score, 'maxScore' => $r->maxScore, 'answers' => $r->answerKey];
    }

    /** @param array<string, mixed> $body */
    public static function noStore(array $body, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($body, $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
