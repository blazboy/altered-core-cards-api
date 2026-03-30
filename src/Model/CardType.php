<?php

namespace App\Model;

class CardType
{
    public const ALL = [
        self::HERO,
        self::CHARACTER,
        self::FOILER,
        self::SPELL,
        self::EXPEDITION_PERMANENT,
        self::LANDMARK_PERMANENT,
        self::PERMANENT,
        self::TOKEN,
        self::TOKEN_MANA
    ];

    public const HERO = 8;
    public const CHARACTER = 1;
    public const SPELL = 2;
    public const PERMANENT = 3;
    public const FOILER = 4;
    public const TOKEN = 5;
    public const EXPEDITION_PERMANENT = 6;
    public const LANDMARK_PERMANENT = 7;

    public const TOKEN_MANA = 9;

}
