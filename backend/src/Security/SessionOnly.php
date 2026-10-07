<?php

namespace App\Security;

/**
 * Marks a controller (or one of its actions) as reserved to a logged-in session: a request authenticated
 * by an API key is refused with FORBIDDEN. Used for managing books and API keys, so that a leaked key can
 * neither create other keys nor delete a book.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class SessionOnly
{
}
