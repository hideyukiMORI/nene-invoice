<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use NeneInvoice\BankTransaction\PayerNameNormalizer;
use PHPUnit\Framework\TestCase;

final class PayerNameNormalizerTest extends TestCase
{
    public function test_collapses_half_width_kana_and_legal_form_marker(): void
    {
        // 半角カナ＋前置「カ)」 と 全角＋後置「（カ」 が同じキーに正規化される。
        self::assertSame(
            PayerNameNormalizer::normalize('ネネシヨウカイ（カ'),
            PayerNameNormalizer::normalize('ｶ)ﾈﾈｼﾖｳｶｲ'),
        );
    }

    public function test_hiragana_and_katakana_match(): void
    {
        self::assertSame(
            PayerNameNormalizer::normalize('ネネシヨウカイ'),
            PayerNameNormalizer::normalize('ねねしようかい'),
        );
    }

    public function test_strips_spaces_including_ideographic(): void
    {
        self::assertSame('ヤマダタロウ', PayerNameNormalizer::normalize('ヤマダ　タロウ'));
        self::assertSame('ヤマダタロウ', PayerNameNormalizer::normalize('ヤマダ タロウ'));
    }

    public function test_strips_full_word_legal_form(): void
    {
        self::assertSame('ネネ', PayerNameNormalizer::normalize('株式会社ネネ'));
    }

    public function test_preserves_long_vowel_mark(): void
    {
        self::assertSame('メーカー', PayerNameNormalizer::normalize('メーカー'));
    }

    public function test_blank_input_normalizes_to_empty(): void
    {
        self::assertSame('', PayerNameNormalizer::normalize('   '));
    }
}
