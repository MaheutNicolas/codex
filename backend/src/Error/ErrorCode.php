<?php

namespace App\Error;

/**
 * Every error the API can return. This enum is the single source of truth:
 * the JSON responses and the GET /api/errors documentation are both built from it.
 */
enum ErrorCode: string
{
    case INVALID_JSON = 'INVALID_JSON';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case INVALID_QUERY_PARAMETER = 'INVALID_QUERY_PARAMETER';
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case LOGIN_FAILED = 'LOGIN_FAILED';
    case FORBIDDEN = 'FORBIDDEN';
    case ROUTE_NOT_FOUND = 'ROUTE_NOT_FOUND';
    case BOOK_NOT_FOUND = 'BOOK_NOT_FOUND';
    case KNOWLEDGE_NOT_FOUND = 'KNOWLEDGE_NOT_FOUND';
    case EVENT_NOT_FOUND = 'EVENT_NOT_FOUND';
    case PARTICIPANT_NOT_FOUND = 'PARTICIPANT_NOT_FOUND';
    case API_KEY_NOT_FOUND = 'API_KEY_NOT_FOUND';
    case METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    case ID_ALREADY_EXISTS = 'ID_ALREADY_EXISTS';
    case REFERENCE_NOT_FOUND = 'REFERENCE_NOT_FOUND';
    case TOO_MANY_ATTEMPTS = 'TOO_MANY_ATTEMPTS';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';

    public function status(): int
    {
        return match ($this) {
            self::INVALID_JSON,
            self::VALIDATION_FAILED,
            self::INVALID_QUERY_PARAMETER => 400,
            self::UNAUTHORIZED,
            self::LOGIN_FAILED => 401,
            self::FORBIDDEN => 403,
            self::ROUTE_NOT_FOUND,
            self::BOOK_NOT_FOUND,
            self::KNOWLEDGE_NOT_FOUND,
            self::EVENT_NOT_FOUND,
            self::PARTICIPANT_NOT_FOUND,
            self::API_KEY_NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::ID_ALREADY_EXISTS,
            self::REFERENCE_NOT_FOUND => 409,
            self::TOO_MANY_ATTEMPTS => 429,
            self::INTERNAL_ERROR => 500,
        };
    }

    /** Default message returned in the response. */
    public function message(): string
    {
        return match ($this) {
            self::INVALID_JSON => 'The request body is not valid JSON.',
            self::VALIDATION_FAILED => 'The submitted data is invalid.',
            self::INVALID_QUERY_PARAMETER => 'A query parameter is invalid.',
            self::UNAUTHORIZED => 'Authentication is required: log in or send a valid API key.',
            self::LOGIN_FAILED => 'The username or the password is incorrect.',
            self::FORBIDDEN => 'This action is not allowed with the credentials in use.',
            self::ROUTE_NOT_FOUND => 'This route does not exist.',
            self::BOOK_NOT_FOUND => 'The book was not found.',
            self::KNOWLEDGE_NOT_FOUND => 'The knowledge entry was not found.',
            self::EVENT_NOT_FOUND => 'The event was not found.',
            self::PARTICIPANT_NOT_FOUND => 'The event participant was not found.',
            self::API_KEY_NOT_FOUND => 'The API key was not found.',
            self::METHOD_NOT_ALLOWED => 'This HTTP method is not allowed on this route.',
            self::ID_ALREADY_EXISTS => 'A resource with this identifier already exists.',
            self::REFERENCE_NOT_FOUND => 'A referenced resource does not exist.',
            self::TOO_MANY_ATTEMPTS => 'Too many failed login attempts. Try again later.',
            self::INTERNAL_ERROR => 'An unexpected error occurred.',
        };
    }

    /** What the error means and when it is raised. */
    public function description(): string
    {
        return match ($this) {
            self::INVALID_JSON => 'The request body could not be decoded as JSON, or it decoded to something other than a JSON object. Raised on POST and PATCH requests, and on the login request when the "username" or "password" key is missing.',
            self::VALIDATION_FAILED => 'The body is valid JSON but one or more fields break a constraint (missing required field, value too long, unknown type, malformed slug...). The "details.fields" object maps each faulty field to the list of its error messages.',
            self::INVALID_QUERY_PARAMETER => 'A query parameter has a value the API cannot use (non-numeric "limit" or "offset", "limit" above 200, "revealed" that is not a boolean...). The "details.parameter" field names the culprit.',
            self::UNAUTHORIZED => 'The request carries neither a valid session cookie nor a valid "X-API-Key" header: the user is not logged in, the session expired, or the API key does not exist or was deleted.',
            self::LOGIN_FAILED => 'POST /api/auth/login received a username that does not exist or a wrong password. The response does not tell which one, on purpose.',
            self::FORBIDDEN => 'The credentials are valid but not sufficient: an API key with the "read" scope tried to create, modify or delete something, or an API key tried an action reserved to a logged-in session (managing books and API keys).',
            self::ROUTE_NOT_FOUND => 'No route matches the requested URL. This is about the URL itself, not about a missing resource: a missing resource returns its own *_NOT_FOUND code.',
            self::BOOK_NOT_FOUND => 'No book has the identifier given in the URL, or it does not belong to the account or API key in use. The two cases are not distinguished, so that nobody can discover which books exist.',
            self::KNOWLEDGE_NOT_FOUND => 'No knowledge entry (character, place, system...) has the identifier given in the URL.',
            self::EVENT_NOT_FOUND => 'No event has the identifier given in the URL.',
            self::PARTICIPANT_NOT_FOUND => 'The given knowledge entry does not participate in the given event.',
            self::API_KEY_NOT_FOUND => 'No API key of the logged-in account has the identifier given in the URL.',
            self::METHOD_NOT_ALLOWED => 'The route exists but does not accept this HTTP method. The "Allow" response header lists the accepted methods.',
            self::ID_ALREADY_EXISTS => 'A POST tried to create a resource with an identifier that is already taken, or a participant link that already exists.',
            self::REFERENCE_NOT_FOUND => 'A field of the body points to a resource that does not exist, for example an event participant whose "knowledgeId" matches no knowledge entry. The "details" object names the field and the missing identifier.',
            self::TOO_MANY_ATTEMPTS => 'Too many failed logins came from the same address or for the same username in a short time. Login is blocked temporarily, even with the right password.',
            self::INTERNAL_ERROR => 'An unexpected server-side failure. The cause is written to the server logs and never exposed in the response.',
        };
    }

    /** How to fix it. */
    public function solution(): string
    {
        return match ($this) {
            self::INVALID_JSON => 'Send a JSON object and set the "Content-Type: application/json" header. Check for trailing commas and unescaped quotes.',
            self::VALIDATION_FAILED => 'Read "details.fields", fix each listed field and send the request again.',
            self::INVALID_QUERY_PARAMETER => 'Use an integer "limit" between 1 and 200, an integer "offset" of 0 or more, and "true" or "false" for boolean filters.',
            self::UNAUTHORIZED => 'Log in with POST /api/auth/login and keep the session cookie, or send the "X-API-Key" header with a key created from a logged-in session (POST /api/books/{bookId}/api-keys).',
            self::LOGIN_FAILED => 'Check the username and the password. Accounts are created by the administrator with the app:user:create command, there is no registration.',
            self::FORBIDDEN => 'Use an API key with the "write" scope for changes, or log in with the account itself for book and API key management.',
            self::ROUTE_NOT_FOUND => 'Check the URL against the route table in the README. Routes are prefixed with /api, and the content of a book lives under /api/books/{bookId}/.',
            self::BOOK_NOT_FOUND => 'List the books of the account with GET /api/books and use the numeric identifier of one of them in the URL: /api/books/{bookId}/... An API key only gives access to the book it was created for.',
            self::KNOWLEDGE_NOT_FOUND => 'Look the identifier up through GET /api/books/{bookId}/index (names and aliases with their identifiers) and retry with an existing one.',
            self::EVENT_NOT_FOUND => 'List events with GET /api/books/{bookId}/events and retry with an existing identifier.',
            self::PARTICIPANT_NOT_FOUND => 'List the links with GET /api/books/{bookId}/event-participants?eventId=...&knowledgeId=... to check whether the link exists.',
            self::API_KEY_NOT_FOUND => 'List the keys of a book with GET /api/books/{bookId}/api-keys and use one of the returned identifiers.',
            self::METHOD_NOT_ALLOWED => 'Use one of the methods listed in the "Allow" response header.',
            self::ID_ALREADY_EXISTS => 'Choose another identifier, or use PATCH to update the existing resource.',
            self::REFERENCE_NOT_FOUND => 'Create the referenced resource first, or correct the identifier named in "details".',
            self::TOO_MANY_ATTEMPTS => 'Wait for the delay given in the "Retry-After" response header (in seconds), then try again with the right credentials.',
            self::INTERNAL_ERROR => 'Retry once. If the error persists, check the server logs (var/log) to find the cause.',
        };
    }
}
