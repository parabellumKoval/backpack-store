<?php

namespace Backpack\Store\app\Services\Search;

class QueryNormalizer
{
    public function variants(string $q, string $locale): array
    {
        $q = trim($q);
        if ($q === '') return [];

        $out = [$q];

        // 1) Без диакритики
        if (\Settings::get('dress.search.normalizer.strip_accents.enabled', true)) {
            $out[] = $this->stripAccents($q);
        }

        // 2) Починка раскладки (по парам из настроек)
        if (\Settings::get('dress.search.normalizer.keyboard_fix.enabled', true)) {
            foreach ((array) \Settings::get('dress.search.normalizer.keyboard_fix.layouts', ['en-ru','en-uk']) as $pair) {
                [$from, $to] = array_pad(explode('-', $pair, 2), 2, null);
                if (!$from || !$to) continue;
                $out[] = $this->fixKeyboard($q, $from, $to);
                $out[] = $this->fixKeyboard($q, $to, $from); // и обратный вариант
            }
        }

        // 3) Транслитерация (ICU)
        if (\Settings::get('dress.search.normalizer.transliteration.enabled', true)) {
            foreach ((array) \Settings::get('dress.search.normalizer.transliteration.pairs', ['Latin-Cyrillic','Cyrillic-Latin','Any-Latin']) as $rule) {
                $out[] = $this->icuTransliterate($q, $rule);
            }
        }

        // Уникализация + выкинуть пустые
        $out = array_values(array_unique(array_filter($out, fn($s) => is_string($s) && $s !== '')));

        // Ограничь количество вариантов (чтобы не взорвать Meili при пустом индексе)
        return array_slice($out, 0, 6);
    }

    protected function stripAccents(string $s): string
    {
        // 1) попытка через intl Normalizer + Transliterator Any-ASCII
        if (class_exists(\Transliterator::class)) {
            $tr = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($tr) {
                $t = $tr->transliterate($s);
                if (is_string($t)) return $t;
            }
        }
        // 2) запасной вариант через iconv
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return is_string($t) ? $t : $s;
    }

    protected function icuTransliterate(string $s, string $rule): string
    {
        if (!class_exists(\Transliterator::class)) return $s;
        $tr = \Transliterator::create($rule);
        if (!$tr) return $s;
        $t = $tr->transliterate($s);
        return is_string($t) ? $t : $s;
    }

    /**
     * Починка раскладки «по позициям клавиш». Карты можно хранить в Settings.
     * $from/$to: коды раскладок ('en','ru','uk','cs','de', ...).
     */
    protected function fixKeyboard(string $s, string $from, string $to): string
    {
        $maps = (array) \Settings::get('dress.search.normalizer.keyboard_fix.maps', []);
        $key = strtolower($from).'-'.strtolower($to);

        // Готовая карта в настройках?
        if (!empty($maps[$key]) && is_array($maps[$key])) {
            return strtr($s, $maps[$key]);
        }

        // Встроенные дефолты для популярных пар (en-ru, en-uk)
        $builtin = $this->builtinLayoutMap($from, $to);
        return $builtin ? strtr($s, $builtin) : $s;
    }

    protected function builtinLayoutMap(string $from, string $to): ?array
    {
        $from = strtolower($from); $to = strtolower($to);

        // en -> ru и ru -> en
        static $en = '`qwertyuiop[]asdfghjkl;\'\\zxcvbnm,./~QWERTYUIOP{}ASDFGHJKL:"|ZXCVBNM<>?';
        static $ru = 'ёйцукенгшщзхъфывапролджэ\ячсмитьбю/ЁЙЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭ|ЯЧСМИТЬБЮ?';

        if ($from === 'en' && $to === 'ru') return array_combine(preg_split('//u', $en, -1, PREG_SPLIT_NO_EMPTY), preg_split('//u',$ru,-1,PREG_SPLIT_NO_EMPTY));
        if ($from === 'ru' && $to === 'en') return array_combine(preg_split('//u', $ru, -1, PREG_SPLIT_NO_EMPTY), preg_split('//u',$en,-1,PREG_SPLIT_NO_EMPTY));

        // en <-> uk (украинская)
        static $uk = 'ґйцукенгшщзхїфівапролджє\ячсмитьбю/ҐЙЦУКЕНГШЩЗХЇФІВАПРОЛДЖЄ|ЯЧСМИТЬБЮ?';
        if ($from === 'en' && $to === 'uk') return array_combine(preg_split('//u',$en,-1,PREG_SPLIT_NO_EMPTY), preg_split('//u',$uk,-1,PREG_SPLIT_NO_EMPTY));
        if ($from === 'uk' && $to === 'en') return array_combine(preg_split('//u',$uk,-1,PREG_SPLIT_NO_EMPTY), preg_split('//u',$en,-1,PREG_SPLIT_NO_EMPTY));

        // Для cs/de/pl (латиница↔латиница) «неверная раскладка» обычно не даёт букв (там AltGr/Dead keys), так что пользы мало.
        // Для них полезнее stripAccents + synonyms. Если очень нужно — добавь карты в Settings.

        return null;
    }
}
