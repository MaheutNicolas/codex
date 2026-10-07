<?php

namespace App\EventListener;

use App\Error\ApiException;
use App\Error\ErrorCode;
use App\Security\CurrentApiKey;
use App\Security\SessionOnly;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/** Enforces the #[SessionOnly] attribute before the controller runs. */
#[AsEventListener(event: 'kernel.controller')]
final class SessionOnlyListener
{
    public function __construct(private readonly CurrentApiKey $currentKey)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if ([] === $event->getAttributes(SessionOnly::class)) {
            return;
        }

        if (null !== $this->currentKey->get()) {
            throw new ApiException(
                ErrorCode::FORBIDDEN,
                [],
                'This action is reserved to a logged-in session: an API key cannot be used for it.',
            );
        }
    }
}
