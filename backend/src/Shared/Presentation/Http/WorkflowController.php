<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use ValueError;

abstract class WorkflowController extends AbstractController
{
    protected function result(callable $operation, int $status = 200): JsonResponse
    {
        try {
            $result = $operation();

            return 204 === $status ? new JsonResponse(null, 204) : $this->json($result, $status);
        } catch (OutOfBoundsException $error) {
            return $this->json(['message' => $error->getMessage()], 404);
        } catch (InvalidArgumentException|ValueError $error) {
            return $this->json(['message' => $error->getMessage()], 422);
        } catch (DomainException|\Doctrine\DBAL\Exception\UniqueConstraintViolationException $error) {
            return $this->json(['message' => $error instanceof DomainException ? $error->getMessage() : 'A record with this identity already exists.'], 409);
        }
    }
}
