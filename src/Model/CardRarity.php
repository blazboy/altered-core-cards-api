<?php

namespace App\Model;

class CardRarity
{
    public const ALL = [
        self::COMMON,
        self::UNIQUE,
        self::RARE,
        self::EXALTED
    ];

    public const RARE = 1;
    public const UNIQUE = 2;
    public const COMMON = 3;
    public const EXALTED = 4;

}