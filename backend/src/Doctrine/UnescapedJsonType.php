<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\JsonType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The "json" type, writing accents as they are ("Zoé") instead of as "Zo\u00e9". MySQL normalises its JSON
 * columns anyway, but MariaDB keeps the text as written: with escaped accents the search could not find
 * an alias or a tag that contains one.
 */
#[Exclude]
final class UnescapedJsonType extends JsonType
{
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        try {
            return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw SerializationFailed::new($value, 'json', $e->getMessage(), $e);
        }
    }
}
