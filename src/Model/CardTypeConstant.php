<?php

namespace App\Model;

class CardTypeConstant
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

    public const HERO = 'HERO';
    public const CHARACTER = 'CHARACTER';
    public const SPELL = 'SPELL';
    public const PERMANENT = 'PERMANENT';
    public const FOILER = 'FOILER';
    public const TOKEN = 'TOKEN';
    public const EXPEDITION_PERMANENT = 'EXPEDITION_PERMANENT';
    public const LANDMARK_PERMANENT = 'LANDMARK_PERMANENT';

    public const TOKEN_MANA = 'TOKEN_MANA';
}
