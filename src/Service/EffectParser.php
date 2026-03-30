<?php

namespace App\Service;

class EffectParser
{
    // Trigger types
    public const TRIGGER_H          = 'H';
    public const TRIGGER_J          = 'J';
    public const TRIGGER_R          = 'R';
    public const TRIGGER_T          = 'T';
    public const TRIGGER_TRIGGERED  = 'TRIGGERED';
    public const TRIGGER_CREPUSCULE = 'CREPUSCULE';
    public const TRIGGER_MIDI       = 'MIDI';
    public const TRIGGER_STATIC     = 'STATIC';
    public const TRIGGER_KEYWORD    = 'KEYWORD';
    public const TRIGGER_SORT       = 'SORT';

    // Keyword names
    public const KW_REPERAGE            = 'REPERAGE';
    public const KW_CORIACE             = 'CORIACE';
    public const KW_GIGANTESQUE         = 'GIGANTESQUE';
    public const KW_DEFENSEUR           = 'DEFENSEUR';
    public const KW_AGUERRI             = 'AGUERRI';
    public const KW_RAFRAICHISSEMENT    = 'RAFRAICHISSEMENT';
    public const KW_FUGACE              = 'FUGACE';
    public const KW_ETERNEL             = 'ETERNEL';
    public const KW_ANCRE               = 'ANCRE';
    public const KW_ENDORMI             = 'ENDORMI';
    public const KW_BOOSTE              = 'BOOSTE';
    // Action keywords
    public const KW_SABOTEZ             = 'SABOTEZ';
    public const KW_RAVITAILLEZ         = 'RAVITAILLEZ';
    public const KW_RAVITAILLEZ_EPUISE  = 'RAVITAILLEZ_EPUISE';
    public const KW_FONCER              = 'FONCER';
    public const KW_DON                 = 'DON';

    private const PURE_KEYWORD_PATTERNS = [
        '/^\#?\[\[Fugace\]\]/u'        => self::KW_FUGACE,
        '/^\#?\[Éternel/u'             => self::KW_ETERNEL,
        '/^\#?\[Gigantesque\]/u'       => self::KW_GIGANTESQUE,
        '/^\#?\[Défenseu/u'            => self::KW_DEFENSEUR,
        '/^\#?\[Aguerri/u'             => self::KW_AGUERRI,
        '/^\#?\[Rafraîchissement\]/u'  => self::KW_RAFRAICHISSEMENT,
        '/^\#?\[Repérage\]/u'          => self::KW_REPERAGE,
        '/^\#?\[Coriace/u'             => self::KW_CORIACE,
        '/^\#?\[Ravitaillez/u'         => null, // action, not a keyword
        '/^\#?\[Sabotez/u'             => null, // action, not a keyword
    ];

    /**
     * Extract the condition part from the French text, or null if none.
     *
     * Examples:
     *   "Lorsque je vais en Réserve — []effet"   → "je vais en Réserve"
     *   "{H} Si j'ai 1 boost : Je gagne..."      → "Si j'ai 1 boost"
     *   "[]Si vous contrôlez un jeton : effet"   → "Si vous contrôlez un jeton"
     *   "{H} Chaque joueur pioche une carte."    → null
     */
    public function parseCondition(string $text): ?string
    {
        $clean = ltrim($text, '#');

        // Strip trigger prefix
        $clean = preg_replace('/^\{[HJRTDI]\}\s*(\[\])?\s*/u', '', $clean);
        $clean = preg_replace('/^\[\]\[\]\s*/u', '', $clean);
        $clean = preg_replace('/^\[\]\s*/u', '', $clean);
        $clean = preg_replace('/^Lorsqu[\'e]\s*/ui', '', $clean);
        $clean = preg_replace('/^Quand\s+/ui', '', $clean);
        $clean = preg_replace('/^Au Crépuscule\s*[—-]\s*/ui', '', $clean);
        $clean = preg_replace('/^À Midi\s*[—-]\s*/ui', '', $clean);
        $clean = trim($clean);

        // Lorsque X — Y  →  X is the condition
        // The em dash may be preceded by a non-breaking space (\u00A0)
        if (preg_match('/^(.+?)\s*\x{2014}\s*/u', $clean, $m)) {
            return trim($m[1]);
        }

        // Si/S'/Sauf/À moins X : Y  →  X is the condition
        // The colon may also be preceded by a non-breaking space
        if (preg_match('/^(.+?)\s*:\s+/u', $clean, $m)) {
            $before = trim($m[1]);
            if (preg_match('/^(Si |S\'|Sauf |À moins|Unless|If )/u', $before)) {
                return $before;
            }
        }

        return null;
    }

    /**
     * Determine the trigger_type from the French text.
     */
    public function parseTriggerType(string $text): string
    {
        $clean = ltrim($text, '#');

        if (str_starts_with($clean, '{H}')) return self::TRIGGER_H;
        if (str_starts_with($clean, '{J}')) return self::TRIGGER_J;
        if (str_starts_with($clean, '{R}')) return self::TRIGGER_R;
        if (str_starts_with($clean, '{T}')) return self::TRIGGER_T;
        if (str_starts_with($clean, 'Au Crépuscule')) return self::TRIGGER_CREPUSCULE;
        if (str_starts_with($clean, 'À Midi')) return self::TRIGGER_MIDI;
        if (str_starts_with($clean, 'Lorsqu') || str_starts_with($clean, 'Quand ')) return self::TRIGGER_TRIGGERED;

        if (str_starts_with($clean, '[]')) return self::TRIGGER_STATIC;

        // Pure keyword effect: starts with [Keyword] or [[Keyword]]
        foreach (self::PURE_KEYWORD_PATTERNS as $pattern => $kw) {
            if (preg_match($pattern, $clean)) {
                return $kw !== null ? self::TRIGGER_KEYWORD : self::TRIGGER_SORT;
            }
        }

        return self::TRIGGER_SORT;
    }

    /**
     * Extract all keywords from the text.
     * Returns array of ['k' => 'KEYWORD_NAME', 'v' => int|null]
     */
    public function parseKeywords(string $text): array
    {
        $keywords = [];
        $seen     = [];

        $this->extractReperage($text, $keywords, $seen);
        $this->extractCoriace($text, $keywords, $seen);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Gigantesque\]/u', self::KW_GIGANTESQUE);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Défenseu[re]s?\]/u', self::KW_DEFENSEUR);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Aguerri[e]?s?\]/u', self::KW_AGUERRI);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Rafraîchissement\]/u', self::KW_RAFRAICHISSEMENT);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[\[Fugace\]\]/u', self::KW_FUGACE);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Éternel[le]?\]/u', self::KW_ETERNEL);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[\[Ancré[e]?\]\]/u', self::KW_ANCRE);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[\[Endormi[e]?s?\]\]/u', self::KW_ENDORMI);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[\[Boosté[e]?s?\]\]/u', self::KW_BOOSTE);
        // Action keywords — Épuisé variant checked first
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Ravitaillez Épuisé\]/u', self::KW_RAVITAILLEZ_EPUISE);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Ravitaillez(?! Épuisé)\]/u', self::KW_RAVITAILLEZ);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Sabotez\]/u', self::KW_SABOTEZ);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Foncer\]/u', self::KW_FONCER);
        $this->extractSimpleKeyword($text, $keywords, $seen, '/\[Don\]/u', self::KW_DON);

        return $keywords;
    }

    private function extractReperage(string $text, array &$keywords, array &$seen): void
    {
        if (preg_match('/\[Repérage\]\s*\{(\d+)\}/u', $text, $m)) {
            $entry = ['k' => self::KW_REPERAGE, 'v' => (int) $m[1]];
            $key   = self::KW_REPERAGE . ':' . $m[1];
            if (!isset($seen[$key])) {
                $keywords[]  = $entry;
                $seen[$key]  = true;
            }
        }
    }

    private function extractCoriace(string $text, array &$keywords, array &$seen): void
    {
        // [Coriace N] or [Coriaces N] — numeric value
        if (preg_match_all('/\[Coriaces?\s+(\d+)\]/u', $text, $matches)) {
            foreach ($matches[1] as $val) {
                $key = self::KW_CORIACE . ':' . $val;
                if (!isset($seen[$key])) {
                    $keywords[] = ['k' => self::KW_CORIACE, 'v' => (int) $val];
                    $seen[$key] = true;
                }
            }
            return;
        }

        // [Coriace X] — variable value
        if (preg_match('/\[Coriaces?\s+X\]/u', $text)) {
            $key = self::KW_CORIACE . ':X';
            if (!isset($seen[$key])) {
                $keywords[] = ['k' => self::KW_CORIACE, 'v' => null];
                $seen[$key] = true;
            }
        }
    }

    private function extractSimpleKeyword(string $text, array &$keywords, array &$seen, string $pattern, string $name): void
    {
        if (!isset($seen[$name]) && preg_match($pattern, $text)) {
            $keywords[]   = ['k' => $name];
            $seen[$name]  = true;
        }
    }
}
