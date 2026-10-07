<?php

namespace App\Service;

final class Hydrator
{
    /**
     * Calls the setter of every field. The fields must have been checked by Validate first.
     * The public "id" of knowledge entries and events is their slug.
     *
     * @param array<string, mixed> $data
     */
    public function apply(object $object, array $data): void
    {
        foreach ($data as $field => $value) {
            $object->{'id' === $field ? 'setSlug' : 'set'.ucfirst($field)}($value);
        }
    }
}
