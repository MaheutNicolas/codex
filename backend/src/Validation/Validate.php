<?php

namespace App\Validation;

use App\Api\Page;
use App\Error\ApiException;
use App\Error\ErrorCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Every validation of the API lives here: the accepted fields of each resource, the types of the
 * values, the entity constraints and the query parameters. All failures are thrown as ApiException.
 *
 * Field types: string, int, bool, list (of strings); a leading "?" also accepts null.
 */
final class Validate
{
    private const KNOWLEDGE_FIELDS = [
        'type' => 'string',
        'name' => 'string',
        'summary' => 'string',
        'description' => '?string',
        'aliases' => 'list',
    ];

    private const EVENT_FIELDS = [
        'title' => 'string',
        'summary' => 'string',
        'detail' => '?string',
        'worldOrder' => 'int',
        'worldDate' => '?string',
        'chapter' => '?int',
        'revealed' => 'bool',
        'tags' => 'list',
    ];

    private const PARTICIPANT_FIELDS = [
        'eventId' => 'string',
        'knowledgeId' => 'string',
        'role' => '?string',
    ];

    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    // ---- Request body ----------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    public function knowledge(array $data, bool $creating): void
    {
        $this->fields($data, $creating ? self::KNOWLEDGE_FIELDS + ['id' => 'string'] : self::KNOWLEDGE_FIELDS);
    }

    /** @param array<string, mixed> $data */
    public function event(array $data, bool $creating): void
    {
        $this->fields($data, $creating ? self::EVENT_FIELDS + ['id' => 'string'] : self::EVENT_FIELDS);
    }

    /** @param array<string, mixed> $data */
    public function book(array $data): void
    {
        $this->fields($data, ['name' => 'string']);
    }

    /** Creating a link needs both identifiers; updating one can only change its role. */
    public function participant(array $data, bool $creating): void
    {
        if (!$creating) {
            $this->fields($data, ['role' => '?string']);

            return;
        }

        $this->fields($data, self::PARTICIPANT_FIELDS);

        $missing = [];
        foreach (['eventId', 'knowledgeId'] as $field) {
            if (!isset($data[$field]) || '' === $data[$field]) {
                $missing[$field][] = "The field \"$field\" is required.";
            }
        }
        if ([] !== $missing) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, ['fields' => $missing]);
        }
    }

    /** Checks the constraints declared on the entity (required values, lengths, slug format...). */
    public function entity(object $entity): void
    {
        $fields = [];
        foreach ($this->validator->validate($entity) as $violation) {
            // The slug is exposed as "id" by the API.
            $path = 'slug' === $violation->getPropertyPath() ? 'id' : $violation->getPropertyPath();
            $fields[$path][] = $violation->getMessage();
        }

        if ([] !== $fields) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, ['fields' => $fields]);
        }
    }

    // ---- Query parameters ------------------------------------------------------------------

    /** The "limit" and "offset" parameters of every list. */
    public function page(Request $request): Page
    {
        return new Page(
            $this->intQuery($request, 'limit', 1, Page::MAX_LIMIT) ?? Page::DEFAULT_LIMIT,
            $this->intQuery($request, 'offset', 0) ?? 0,
        );
    }

    public function stringQuery(Request $request, string $name): ?string
    {
        $value = $this->rawQuery($request, $name);
        if (null === $value) {
            return null;
        }
        if (!\is_string($value) || '' === $value) {
            throw $this->invalidQuery($name, 'a non-empty string');
        }

        return $value;
    }

    /** @param list<string> $choices */
    public function choiceQuery(Request $request, string $name, array $choices): ?string
    {
        $value = $this->stringQuery($request, $name);
        if (null !== $value && !\in_array($value, $choices, true)) {
            throw $this->invalidQuery($name, 'one of: '.implode(', ', $choices));
        }

        return $value;
    }

    public function intQuery(Request $request, string $name, int $min, ?int $max = null): ?int
    {
        $value = $this->rawQuery($request, $name);
        if (null === $value) {
            return null;
        }

        $expected = null === $max ? "an integer of {$min} or more" : "an integer between {$min} and {$max}";
        if (!\is_string($value) || 1 !== preg_match('/^-?\d{1,10}$/', $value)) {
            throw $this->invalidQuery($name, $expected);
        }
        $int = (int) $value;
        if ($int < $min || (null !== $max && $int > $max)) {
            throw $this->invalidQuery($name, $expected);
        }

        return $int;
    }

    public function boolQuery(Request $request, string $name): ?bool
    {
        $value = $this->rawQuery($request, $name);

        return match ($value) {
            null => null,
            'true', '1' => true,
            'false', '0' => false,
            default => throw $this->invalidQuery($name, '"true" or "false"'),
        };
    }

    // ---- Internals -------------------------------------------------------------------------

    /**
     * Rejects unknown fields and wrongly typed values, reporting every faulty field at once.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $types
     */
    private function fields(array $data, array $types): void
    {
        $fields = [];
        foreach ($data as $field => $value) {
            if (!isset($types[$field])) {
                $fields[$field][] = 'Unknown or read-only field.';
            } elseif (!$this->matchesType($value, $types[$field])) {
                $fields[$field][] = 'Expected '.$this->describeType($types[$field]).'.';
            }
        }

        if ([] !== $fields) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, ['fields' => $fields]);
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        if (null === $value) {
            return str_starts_with($type, '?');
        }

        return match (ltrim($type, '?')) {
            'string' => \is_string($value),
            'int' => \is_int($value),
            'bool' => \is_bool($value),
            'list' => \is_array($value) && array_is_list($value) && [] === array_filter($value, static fn ($item) => !\is_string($item)),
        };
    }

    private function describeType(string $type): string
    {
        $description = match (ltrim($type, '?')) {
            'string' => 'a string',
            'int' => 'an integer',
            'bool' => 'a boolean',
            'list' => 'a list of strings',
        };

        return str_starts_with($type, '?') ? 'null or '.$description : $description;
    }

    private function rawQuery(Request $request, string $name): mixed
    {
        return $request->query->all()[$name] ?? null;
    }

    private function invalidQuery(string $name, string $expected): ApiException
    {
        return new ApiException(
            ErrorCode::INVALID_QUERY_PARAMETER,
            ['parameter' => $name, 'expected' => $expected],
            \sprintf('The query parameter "%s" is invalid: expected %s.', $name, $expected),
        );
    }
}
