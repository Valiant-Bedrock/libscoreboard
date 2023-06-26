<?php
/**
 * Copyright (C) 2020 - 2023 | Valiant Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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
use RuntimeException;

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
	 */
	public function send(?DisplaySlot $slot = null, ?SortOrder $order = null): void {
		if($this->visible || !$this->player->isConnected()) {
			return;
		}

		$slot = $slot ?? DisplaySlot::SIDEBAR;
		$order = $order ?? SortOrder::ASCENDING;
		$this->player->getNetworkSession()->sendDataPacket(SetDisplayObjectivePacket::create(
			displaySlot: $slot->value,
			objectiveName: self::OBJECTIVE_NAME,
			displayName: $this->title,
			criteriaName: self::CRITERIA_NAME,
			sortOrder: $order->value,
		));

		// Update scoreboard properties as needed
		$this->currentSlot = $slot;
		$this->currentOrder = $order;
		$this->visible = true;
	}

	/**
	 * This method removes the scoreboard from the client (if visible)
	 */
	public function remove(): void {
		if(!$this->visible || !$this->player->isConnected()) {
			return;
		}
		$this->player->getNetworkSession()->sendDataPacket(RemoveObjectivePacket::create(
			objectiveName: self::OBJECTIVE_NAME
		));

		$this->currentSlot = null;
		$this->currentOrder = null;
		$this->visible = false;
	}

	public function setSortOrder(SortOrder $order, bool $update = true): void {
		$this->currentOrder = $order;
		if($this->visible && $update) $this->update();
	}

	public function setDisplaySlot(DisplaySlot $slot, bool $update = true): void {
		$this->currentSlot = $slot;
		if($this->visible && $update) $this->update();
	}

	/**
	 * This method sets an array of lines to the scoreboard, clears if specified, and updates if specified
	 * @param array<int, string> $lines
	 * @param bool $clear - If true, will clear the scoreboard before setting the new lines
	 */
	public function setLines(array $lines, bool $clear = true, bool $update = true): void {
		if (!$this->player->isConnected()) {
			return;
		}
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
	 * This method sets a line with the given index to the given value on the scoreboard and updates it if specified
	 */
	public function setLine(int $index, string $value, bool $update = true): void {
		if (!$this->player->isConnected()) {
			return;
		}

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
	 * This method removes a line from the scoreboard and updates it if specified
	 */
	public function removeLine(int $index, bool $update = true): void {
		if($index < 0 or $index >= self::MAX_LINE_COUNT) {
			throw new InvalidArgumentException("Invalid index $index");
		}
		if (!$this->player->isConnected()) {
			return;
		}
		$this->player->getNetworkSession()->sendDataPacket(SetScorePacket::create(
			type: SetScorePacket::TYPE_REMOVE,
			entries: [self::createEntry($index, "")]
		));
		unset($this->lines[$index]);

		if($this->visible && $update) $this->update();
	}

	/**
	 * This method removes all the lines from the player's scoreboard
	 */
	public function clear(bool $update = true): void {
		if (!$this->player->isConnected()) {
			return;
		}
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
	 * This method attempts to update the scoreboard with the current values
	 * @throws ScoreboardNotVisibleException - If the scoreboard isn't visible, this exception will be thrown
	 */
	public function update(): void {
		if(!$this->visible) {
			throw new ScoreboardNotVisibleException("Cannot update scoreboard when it is not visible. Please use Scoreboard->send() before attempting to update it");
		}
		if (!$this->player->isConnected()) {
			return;
		}
		$this->player->getNetworkSession()->sendDataPacket(SetDisplayObjectivePacket::create(
			displaySlot: $this->currentSlot?->value ?? throw new RuntimeException("Scoreboard slot is not set"),
			objectiveName: self::OBJECTIVE_NAME,
			displayName: $this->title,
			criteriaName: self::CRITERIA_NAME,
			sortOrder: $this->currentOrder?->value ?? throw new RuntimeException("Scoreboard order is not set")
		));
	}

	/**
	 * Given an index and string value, this static method will create a score packet entry
	 * This method is mainly used to create entries for the scoreboard
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