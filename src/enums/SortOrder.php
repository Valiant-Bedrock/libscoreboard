<?php
/**
 *
 * Copyright (C) 2020 - 2022 | Matthew Jordan
 *
 * This program is private software. You may not redistribute this software, or
 * any derivative works of this software, in source or binary form, without
 * the express permission of the owner.
 *
 * @author sylvrs
 */
declare(strict_types=1);

namespace libscoreboard\enums;

use pocketmine\utils\EnumTrait;

/**
 * @method static SortOrder ASCENDING()
 * @method static SortOrder DESCENDING()
 */
class SortOrder
{
    use EnumTrait {
        __construct as private Enum__construct;
    }

    protected static function setup(): void
    {
        self::registerAll(
            new SortOrder("ascending", 0),
            new SortOrder("descending", 1)
        );
    }

    public function __construct(string $enumName, protected int $ordinal)
    {
        $this->Enum__construct($enumName);
    }

    public function ordinal(): int
    {
        return $this->ordinal;
    }
}