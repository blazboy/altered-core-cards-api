<?php

namespace App\Model;

interface TimestampInterface
{
    public function getCreationDate(): \DateTimeImmutable;
    public function getUpdateDate(): ?\DateTimeImmutable;
}
