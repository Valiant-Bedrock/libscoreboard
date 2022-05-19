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
 * @method static DisplaySlot BELOWNAME()
 * @method static DisplaySlot LIST()
 * @method static DisplaySlot SIDEBAR()
 */
class DisplaySlot
{
    use EnumTrait;

    protected static function setup(): void
    {
        self::registerAll(
            new DisplaySlot("belowname"),
            new DisplaySlot("list"),
            new DisplaySlot("sidebar")
        );
    }
}