<?php

namespace App\Security;

use App\Entity\ApiKey;
use Symfony\Bundle\SecurityBundle\Security;

/** The API key the current request was authenticated with, or null when it comes from a logged-in session. */
final class CurrentApiKey
{
    public function __construct(private readonly Security $security)
    {
    }

    public function get(): ?ApiKey
    {
        $token = $this->security->getToken();

        return $token?->hasAttribute('api_key') ? $token->getAttribute('api_key') : null;
    }
}
