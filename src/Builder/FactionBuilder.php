<?php

namespace App\Builder;

use App\Entity\Faction;

class FactionBuilder
{
    public function build(string $code, string $name): Faction
    {
        return (new Faction())
            ->setCode($code)
            ->setName($name);
    }
}
