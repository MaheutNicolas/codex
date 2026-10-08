<?php

namespace App\Tests\Unit;

use App\Api\Viewpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The rule that decides whether a reader may see an event (the SQL queries repeat it: keep them in step). */
final class ViewpointTest extends TestCase
{
    /** @return iterable<string, array{Viewpoint, bool, ?int, bool}> viewpoint, revealed, chapter, visible */
    public static function cases(): iterable
    {
        $reader = new Viewpoint();
        $readerAtThree = new Viewpoint(3);
        $author = new Viewpoint(null, true);
        $authorAtThree = new Viewpoint(3, true);

        yield 'a reader sees a revealed event' => [$reader, true, 5, true];
        yield 'a reader sees a revealed event that is never told' => [$reader, true, null, true];
        yield 'a reader does not see a secret' => [$reader, false, 5, false];
        yield 'at chapter 3, an event of chapter 3' => [$readerAtThree, true, 3, true];
        yield 'at chapter 3, an event of chapter 4' => [$readerAtThree, true, 4, false];
        yield 'at chapter 3, an event never told' => [$readerAtThree, true, null, false];
        yield 'at chapter 3, a secret of chapter 1' => [$readerAtThree, false, 1, false];
        yield 'the author sees a secret' => [$author, false, 5, true];
        yield 'the author sees an event never told' => [$author, false, null, true];
        yield 'the author at chapter 3, an event of chapter 4' => [$authorAtThree, true, 4, false];
        yield 'the author at chapter 3, a secret of chapter 4' => [$authorAtThree, false, 4, false];
        yield 'the author at chapter 3, a secret of chapter 2' => [$authorAtThree, false, 2, true];
        yield 'the author at chapter 3, an event never told' => [$authorAtThree, true, null, true];
        yield 'before chapter 1 nothing has been read' => [new Viewpoint(0), true, 1, false];
    }

    #[DataProvider('cases')]
    public function testAllows(Viewpoint $viewpoint, bool $revealed, ?int $chapter, bool $expected): void
    {
        self::assertSame($expected, $viewpoint->allows($revealed, $chapter));
    }
}
