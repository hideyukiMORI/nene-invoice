<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Normalizes a bank remitter name (振込依頼人名) into a stable matching key (#505).
 *
 * Bank deposit lines render the payer in inconsistent ways — half-width kana,
 * mixed spacing, and a 法人格 abbreviation such as `カ)` / `(カ` for 株式会社. This
 * collapses those variations so the same payer maps to one {@see PayerAlias} key
 * and can be compared against a client's `name_kana`.
 *
 * The transform: half-width kana → full-width katakana, combine voiced marks,
 * hiragana → katakana, full-width alphanumerics → half-width, upper-case, strip
 * common 法人格 markers, then drop spaces and separators (the long-vowel mark `ー`
 * is preserved). It is a heuristic, not a canonical identity — scoring tolerates
 * residual differences.
 */
final class PayerNameNormalizer
{
    /** Common 法人格 abbreviations as they appear in bank transfers, plus full words. */
    private const LEGAL_FORM_MARKERS = [
        'カブシキガイシャ', 'ユウゲンガイシャ', 'ゴウドウガイシャ', 'ゴウシガイシャ', 'ゴウメイガイシャ',
        '株式会社', '有限会社', '合同会社', '合資会社', '合名会社',
        '（カ）', '(カ)', 'カ）', 'カ)', '（カ', '(カ',
        '（ユ）', '(ユ)', 'ユ）', 'ユ)', '（ユ', '(ユ',
        '（ド）', '(ド)', 'ド）', 'ド)', '（ド', '(ド',
        '㈱', '㈲',
    ];

    public static function normalize(string $raw): string
    {
        $name = trim($raw);
        if ($name === '') {
            return '';
        }

        // Unify width/kana: half-width kana → full katakana (K), combine voiced
        // marks (V), hiragana → katakana (C), full-width alnum → half-width (a).
        $name = mb_convert_kana($name, 'KVCa');
        $name = mb_strtoupper($name, 'UTF-8');

        $name = str_replace(self::LEGAL_FORM_MARKERS, '', $name);

        // Drop spaces and separators, but keep the long-vowel mark ー (U+30FC).
        $stripped = preg_replace('/[\s\x{3000}・,，、。\.\-\/（）()「」『』\[\]　]+/u', '', $name);

        return $stripped ?? $name;
    }
}
