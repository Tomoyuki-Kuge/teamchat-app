<?php

namespace App\Support\Search;

use Illuminate\Support\HtmlString;
use LogicException;
use Normalizer;

class SearchText
{
    public static function normalize(string $value): string
    {
        return strtolower(Normalizer::normalize($value, Normalizer::FORM_KC));
    }

    public static function words(string $query): array
    {
        return array_values(array_unique(preg_split('/[\s\p{Z}]+/u', self::normalize($query), -1, PREG_SPLIT_NO_EMPTY)));
    }

    public static function highlight(string $body, array $words): HtmlString
    {
        preg_match_all('/\X/u', $body, $matches, PREG_OFFSET_CAPTURE);
        $units = [];
        foreach ($matches[0] as [$cluster, $start]) {
            $units[] = ['start' => $start, 'end' => $start + strlen($cluster), 'text' => self::normalize($cluster)];
            // 互換ハングル等で正規化の境界をまたぐ場合だけ元範囲を統合します。
            while (count($units) >= 2) {
                $last = count($units) - 1;
                $joined = $units[$last - 1]['text'].$units[$last]['text'];
                $normalized = self::normalize($joined);
                if ($joined === $normalized) {
                    break;
                }
                $right = array_pop($units);
                $left = array_pop($units);
                $units[] = ['start' => $left['start'], 'end' => $right['end'], 'text' => $normalized];
            }
        }
        $normalized = implode('', array_column($units, 'text'));
        if ($normalized !== self::normalize($body)) {
            throw new LogicException('Search highlighting normalization boundaries do not match.');
        }
        $map = [];
        $position = 0;
        foreach ($units as $unit) {
            $length = strlen($unit['text']);
            for ($i = 0; $i < $length; $i++) {
                $map[$position + $i] = [$unit['start'], $unit['end']];
            }
            $position += $length;
        }
        // 一致数によらず、原文の各バイトに強調の有無を1バイトだけ保持します。
        $marked = str_repeat("\0", strlen($body));
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $offset = 0;
            while (($found = strpos($normalized, $word, $offset)) !== false) {
                $start = $map[$found][0];
                $length = $map[$found + strlen($word) - 1][1] - $start;
                $marked = substr_replace($marked, str_repeat("\1", $length), $start, $length);
                $offset = $found + 1;
            }
        }
        $html = '';
        $position = 0;
        while (($start = strpos($marked, "\1", $position)) !== false) {
            $end = strpos($marked, "\0", $start);
            if ($end === false) {
                $end = strlen($body);
            }
            $html .= e(substr($body, $position, $start - $position)).'<mark>'.e(substr($body, $start, $end - $start)).'</mark>';
            $position = $end;
        }

        return new HtmlString($html.e(substr($body, $position)));
    }
}
