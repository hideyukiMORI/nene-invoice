<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Company\Seal;

use Nene2\Validation\ValidationException;
use NeneInvoice\Company\Seal\SealImageValidator;
use PHPUnit\Framework\TestCase;

final class SealImageValidatorTest extends TestCase
{
    public function test_accepts_a_png_and_returns_bare_base64(): void
    {
        $base64 = self::pngBase64(200, 200);

        self::assertSame($base64, SealImageValidator::validate($base64));
    }

    public function test_strips_a_data_uri_prefix(): void
    {
        $base64 = self::pngBase64(64, 64);

        $result = SealImageValidator::validate('data:image/png;base64,' . $base64);

        self::assertSame($base64, $result);
        self::assertStringStartsNotWith('data:', $result);
    }

    public function test_rejects_missing_value(): void
    {
        $this->expectException(ValidationException::class);
        SealImageValidator::validate(null);
    }

    public function test_rejects_invalid_base64(): void
    {
        $this->expectException(ValidationException::class);
        SealImageValidator::validate('!!! not base64 !!!');
    }

    public function test_rejects_non_png_image(): void
    {
        $this->expectException(ValidationException::class);
        SealImageValidator::validate(self::jpegBase64(64, 64));
    }

    public function test_rejects_oversize_dimensions(): void
    {
        $this->expectException(ValidationException::class);
        SealImageValidator::validate(self::pngBase64(SealImageValidator::MAX_DIMENSION + 1, 10));
    }

    public function test_rejects_oversize_bytes_before_format(): void
    {
        // A blob larger than the byte cap is rejected on size, regardless of type.
        $oversize = base64_encode(str_repeat("\0", SealImageValidator::MAX_BYTES + 1024));

        $this->expectException(ValidationException::class);
        SealImageValidator::validate($oversize);
    }

    private static function pngBase64(int $width, int $height): string
    {
        $img = imagecreatetruecolor(max(1, $width), max(1, $height));
        ob_start();
        imagepng($img);
        $binary = (string) ob_get_clean();
        imagedestroy($img);

        return base64_encode($binary);
    }

    private static function jpegBase64(int $width, int $height): string
    {
        $img = imagecreatetruecolor(max(1, $width), max(1, $height));
        ob_start();
        imagejpeg($img);
        $binary = (string) ob_get_clean();
        imagedestroy($img);

        return base64_encode($binary);
    }
}
