<?php

namespace App\Tests\Unit;

use App\Error\ErrorCode;
use PHPUnit\Framework\TestCase;

/** The enum is the single source of truth of the errors: every code must be fully documented. */
final class ErrorCodeTest extends TestCase
{
    public function testEveryCodeHasAStatusAndAllItsTexts(): void
    {
        foreach (ErrorCode::cases() as $code) {
            self::assertGreaterThanOrEqual(400, $code->status(), $code->name);
            self::assertLessThan(600, $code->status(), $code->name);
            self::assertNotSame('', $code->message(), $code->name.' needs a message');
            self::assertNotSame('', $code->description(), $code->name.' needs a description');
            self::assertNotSame('', $code->solution(), $code->name.' needs a solution');
        }
    }

    public function testTheValueOfACodeIsItsName(): void
    {
        foreach (ErrorCode::cases() as $code) {
            self::assertSame($code->name, $code->value);
        }
    }

    public function testTheStatusesTheAppRelyOnAreStable(): void
    {
        self::assertSame(400, ErrorCode::INVALID_JSON->status());
        self::assertSame(400, ErrorCode::VALIDATION_FAILED->status());
        self::assertSame(401, ErrorCode::UNAUTHORIZED->status());
        self::assertSame(403, ErrorCode::FORBIDDEN->status());
        self::assertSame(404, ErrorCode::BOOK_NOT_FOUND->status());
        self::assertSame(409, ErrorCode::ID_ALREADY_EXISTS->status());
        self::assertSame(409, ErrorCode::REFERENCE_NOT_FOUND->status());
        self::assertSame(429, ErrorCode::TOO_MANY_ATTEMPTS->status());
    }
}
