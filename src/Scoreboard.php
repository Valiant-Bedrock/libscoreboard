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

namespace libscoreboard;

use InvalidArgumentException;
use libscoreboard\enums\DisplaySlot;
use libscoreboard\enums\SortOrder;
use libscoreboard\exceptions\ScoreboardNotVisibleException;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;

class Scoreboard {
	public const MAX_LINE_COUNT = 15;
	/** Constants used when sending the scoreboard to a player */
	protected const OBJECTIVE_NAME = "scoreboard";
	protected const CRITERIA_NAME = "dummy";

	protected ?DisplaySlot $currentSlot = null;
	protected ?SortOrder $currentOrder = null;
	/** @var bool - Returns true if the player can currently see the scoreboard */
	protected bool $visible = false;

	/**
	 * @param Player $player
	 * @param string $title
	 * @param array<int, string> $lines
	 */
	public function __construct(
		protected Player $player,
		protected string $title,
		protected array $lines = []
	) {
	}

	public function isVisible(): bool {
		return $this->visible;
	}

    /**
     * This method sends the scoreboard to the player if not visible
     *
     * @param DisplaySlot|null $slot
     * @param SortOrder|null $order
     * @return void
     */
	public function send(?DisplaySlot $slot = null, ?SortOrder $order = null): void {
        // If no arguments are passed, we'll set default ones
        $slot = $slot ?? DisplaySlot::SIDEBAR();
        $order = $order ?? SortOrder::ASCENDING();
		if($this->visible) {
			return;
		}

		$this->player->getNetworkSession()->sendDataPacket(SetDisplayObjectivePacket::create(
			displaySlot: $slot->name(),
			objectiveName: self::OBJECTIVE_NAME,
			displayName: $this->title,
			criteriaName: self::CRITERIA_NAME,
			sortOrder: $order->ordinal(),
		));

		// Update scoreboard properties as needed
		$this->currentSlot = $slot;
		$this->currentOrder = $order;
		$this->visible = true;
	}

    /**
     * This method removes the scoreboard from the client (if visible)
     *
     * @return void
     */
	public function remove(): void {
		if(!$this->visible) {
			return;
		}
		$this->player->getNetworkSession()->sendDataPacket(RemoveObjectivePacket::create(
			objectiveName: self::OBJECTIVE_NAME
		));

		$this->currentSlot = null;
		$this->currentOrder = null;
		$this->visible = false;
	}

    /**
     * This method updates the current sorting order
     *
     * @param SortOrder $order
     * @param bool $update
     * @return void
     */
    public function setSortOrder(SortOrder $order, bool $update = true): void {
        $this->currentOrder = $order;
        if($this->visible && $update) $this->update();
    }

    /**
     * This method updates the current display slot
     *
     * @param DisplaySlot $slot
     * @param bool $update
     * @return void
     */
    public function setDisplaySlot(DisplaySlot $slot, bool $update = true): void {
        $this->currentSlot = $slot;
        if($this->visible && $update) $this->update();
    }

	/**
	 * Sets the lines of the scoreboard
	 *
	 * @param array<int, string> $lines
	 * @param bool $clear - If true, will clear the scoreboard before setting the new lines
	 * @param bool $update
	 * @return void
	 */
	public function setLines(array $lines, bool $clear = true, bool $update = true): void {
		if(count($lines) > self::MAX_LINE_COUNT) {
			throw new InvalidArgumentException("Scoreboard lines cannot be more than " . self::MAX_LINE_COUNT . " lines");
		}

		if($clear) $this->clear();
		$this->player->getNetworkSession()->sendDataPacket(SetScorePacket::create(
			type: SetScorePacket::TYPE_CHANGE,
			entries: array_map(
				fn(int $index, string $line) => self::createEntry($index, $line),
				array_keys($lines),
				array_values($lines)
			)
		));
		$this->lines = $lines;
		if($this->visible && $update) $this->update();
	}

	/**
	 * Sets a single line on the scoreboard
	 *
	 * @param int $index
	 * @param string $value
	 * @param bool $update
	 * @return void
	 */
	public function setLine(int $index, string $value, bool $update = true): void {
		if($index < 0 or $index >= self::MAX_LINE_COUNT) {
			throw new InvalidArgumentException("Invalid index $index");
		}

		// Remove line before sending a new one
		if(isset($this->lines[$index])) {
			$this->removeLine($index);
		}

		$this->player->getNetworkSession()->sendDataPacket(SetScorePacket::create(
			type: SetScorePacket::TYPE_CHANGE,
			entries: [self::createEntry($index, $value)]
		));
		$this->lines[$index] = $value;
		if($this->visible && $update) $this->update();
	}

	/**
	 * Removes a line from the scoreboard
	 *
	 * @param int $index
	 * @param bool $update
	 * @return void
	 */
	public function removeLine(int $index, bool $update = true): void {
		if($index < 0 or $index >= self::MAX_LINE_COUNT) {
			throw new InvalidArgumentException("Invalid index $index");
		}
		$this->player->getNetworkSession()->sendDataPacket(SetScorePacket::create(
			type: SetScorePacket::TYPE_REMOVE,
			entries: [self::createEntry($index, "")]
		));
		unset($this->lines[$index]);

		if($this->visible && $update) $this->update();
	}

	/**
	 * This method clears the scoreboard
	 *
	 * @param bool $update
	 * @return void
	 */
	public function clear(bool $update = true): void {
		$this->player->getNetworkSession()->sendDataPacket(SetScorePacket::create(
			type: SetScorePacket::TYPE_REMOVE,
			entries: array_map(
				fn(int $index, string $line) => self::createEntry($index, ""),
				array_keys($this->lines),
				array_values($this->lines)
			)
		));
		$this->lines = [];

		if($this->visible && $update) $this->update();
	}

	/**
     * Attempts to update the scoreboard with the current values
     *
	 * @throws ScoreboardNotVisibleException - If the scoreboard isn't visible, this exception will be thrown
	 */
	public function update(): void {
		if(!$this->visible) {
			throw new ScoreboardNotVisibleException("Cannot update scoreboard when it is not visible. Please use Scoreboard->send() before attempting to update it");
		}

		$this->player->getNetworkSession()->sendDataPacket(SetDisplayObjectivePacket::create(
			displaySlot: $this->currentSlot?->name(),
			objectiveName: self::OBJECTIVE_NAME,
			displayName: $this->title,
			criteriaName: self::CRITERIA_NAME,
			sortOrder: $this->currentOrder?->ordinal(),
		));
	}

    /**
     * Given an index and string value, this static method will create a score packet entry
     * This method is mainly used to create entries for the scoreboard
     *
     * @param int $index
     * @param string $value
     * @return ScorePacketEntry
     */
	protected static function createEntry(int $index, string $value): ScorePacketEntry {
		$entry = new ScorePacketEntry();
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
		$entry->objectiveName = self::OBJECTIVE_NAME;
		$entry->scoreboardId = $index;
		$entry->score = $index;
		// Add zero-padding according to the index for the scoreboard as to ensure all lines show up properly
		$entry->customName = $value . str_repeat("\0", $index);
		return $entry;
	}
}