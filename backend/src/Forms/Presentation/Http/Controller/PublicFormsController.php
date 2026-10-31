<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Http\Controller;

use App\Forms\Application\Command\SubmitPublicForm\SubmitPublicFormCommand;
use App\Forms\Application\Command\SubmitPublicForm\SubmitPublicFormHandler;
use App\Forms\Application\Query\GetPublicFormPage\GetPublicFormPageHandler;
use App\Forms\Application\Query\GetPublicFormPage\GetPublicFormPageQuery;
use App\Forms\Presentation\Http\DTO\SubmissionPayload;
use App\Forms\Presentation\Http\PublicFormGuard;
use App\Shared\Presentation\Http\WorkflowController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/forms/{token}', format: 'json', requirements: ['token' => '[a-f0-9]{64}'])]
final class PublicFormsController extends WorkflowController
{
    public function __construct(
        private readonly PublicFormGuard $guard,
        private readonly GetPublicFormPageHandler $getPublicFormPageHandler,
        private readonly SubmitPublicFormHandler $submitPublicFormHandler,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function get(string $token): JsonResponse
    {
        $response = $this->result(function () use ($token) {
            $form = ($this->getPublicFormPageHandler)(new GetPublicFormPageQuery($token));

            return $form + ['challenge' => $this->guard->challenge($token, $form['version'])];
        });
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    #[Route('', methods: ['POST'])]
    public function submit(
        string $token,
        Request $request,
        #[MapRequestPayload]
        SubmissionPayload $p,
    ): JsonResponse {
        $response = $this->result(function () use ($token, $p) {
            $this->guard->check($token, $p->formVersion, $p->challenge, $p->website);
            ($this->submitPublicFormHandler)(new SubmitPublicFormCommand($token, $p->formVersion, $p->answers, $p->submissionKey));

            return ['message' => 'Your response has been received for staff review.'];
        }, 202);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
