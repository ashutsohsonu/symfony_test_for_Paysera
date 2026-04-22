<?php

declare(strict_types=1);

namespace App\Http\Exception;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Global JSON exception handler.
 * Maps domain, validation, and HTTP exceptions to structured JSON error responses.
 * Follows RFC 7807 Problem Details structure.
 */
final class ApiExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $env,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Unwrap Messenger's HandlerFailedException to get the real domain exception
        if ($exception instanceof HandlerFailedException) {
            $nested = $exception->getPrevious();
            if ($nested !== null) {
                $exception = $nested;
            }
        }

        [$status, $type, $detail] = $this->resolveException($exception);

        if ($status >= 500) {
            $this->logger->error('Unhandled exception', [
                'type'      => get_class($exception),
                'message'   => $exception->getMessage(),
                'trace'     => $exception->getTraceAsString(),
            ]);
        }

        $body = [
            'status' => 'error',
            'type'   => $type,
            'message' => $detail,
        ];

        // Include real message and stack trace in non-prod environments
        if ($this->env !== 'prod') {
            $body['message'] = $exception->getMessage();
            $body['debug'] = [
                'exception' => get_class($exception),
                'trace'     => explode("\n", $exception->getTraceAsString()),
            ];
        }

        $event->setResponse(new JsonResponse($body, $status));
    }

    private function resolveException(\Throwable $e): array
    {
        if ($e instanceof HttpExceptionInterface) {
            return [$e->getStatusCode(), 'https://tools.ietf.org/html/rfc7231', $e->getMessage()];
        }

        if ($e instanceof \InvalidArgumentException) {
            return [Response::HTTP_BAD_REQUEST, '/errors/validation', $e->getMessage()];
        }

        if ($e instanceof \DomainException) {
            return [Response::HTTP_UNPROCESSABLE_ENTITY, '/errors/domain', $e->getMessage()];
        }

        return [Response::HTTP_INTERNAL_SERVER_ERROR, '/errors/internal', 'An unexpected error occurred.'];
    }
}
