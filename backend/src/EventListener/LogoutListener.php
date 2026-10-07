<?php

namespace App\EventListener;

use App\Api\ApiHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/** Answers a logout with JSON instead of Symfony's default redirection. */
#[AsEventListener]
final class LogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(ApiHelper::json(['status' => 'logged out']));
    }
}
