<?php

namespace App\EventListener;

use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Error\ErrorResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Turns every exception into the JSON error format documented by ErrorCode. */
#[AsEventListener(event: 'kernel.exception')]
final class ExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $details = [];

        if ($exception instanceof ApiException) {
            $code = $exception->errorCode;
            $message = $exception->getMessage();
            $details = $exception->details;
        } else {
            $code = match (true) {
                $exception instanceof NotFoundHttpException => ErrorCode::ROUTE_NOT_FOUND,
                $exception instanceof MethodNotAllowedHttpException => ErrorCode::METHOD_NOT_ALLOWED,
                // Thrown by the login when the body is not JSON or lacks a key: its message says which.
                $exception instanceof BadRequestHttpException => ErrorCode::INVALID_JSON,
                default => ErrorCode::INTERNAL_ERROR,
            };
            $message = $exception instanceof BadRequestHttpException ? $exception->getMessage() : $code->message();
        }

        // The response replaces Symfony's own error handling, so server errors must be logged here.
        if (ErrorCode::INTERNAL_ERROR === $code) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }

        $event->setResponse(ErrorResponse::create($code, $message, $details, $headers));
    }
}
