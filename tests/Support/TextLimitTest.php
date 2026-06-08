<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Validation\ValidationException;
use NeneInvoice\Support\TextLimit;
use PHPUnit\Framework\TestCase;

final class TextLimitTest extends TestCase
{
    public function test_null_is_allowed(): void
    {
        TextLimit::check(null, 'body.x', TextLimit::NAME);

        $this->expectNotToPerformAssertions();
    }

    public function test_value_at_the_limit_passes(): void
    {
        TextLimit::check(str_repeat('a', TextLimit::NAME), 'body.x', TextLimit::NAME);

        $this->expectNotToPerformAssertions();
    }

    public function test_value_over_the_limit_throws(): void
    {
        $this->expectException(ValidationException::class);

        TextLimit::check(str_repeat('a', TextLimit::NAME + 1), 'body.x', TextLimit::NAME);
    }

    public function test_length_is_counted_in_codepoints_not_bytes(): void
    {
        // 255 multibyte chars = 255 code points (765 bytes) — must pass at NAME=255.
        TextLimit::check(str_repeat('あ', TextLimit::NAME), 'body.x', TextLimit::NAME);

        $this->expectNotToPerformAssertions();
    }
}
