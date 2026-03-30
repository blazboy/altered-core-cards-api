<?php

namespace App\Model;

class CardRarityConstant
{
    public const ALL = [
        self::COMMON,
        self::UNIQUE,
        self::RARE,
        self::EXALTED
    ];

    public const RARE = 'RARE';
    public const UNIQUE = 'UNIQUE';
    public const COMMON = 'COMMON';
    public const EXALTED = 'EXALTED';
}
