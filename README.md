# libscoreboard
A small scoreboard library built for Minecraft: Bedrock Edition

## Installation
### Composer
Run the command `composer require valiant-bedrock/libscoreboard` to install this package.
### Virion
The virion for this project is located & can be installed from [here](https://poggit.pmmp.io/ci/Valiant-Bedrock/libscoreboard/libscoreboard)

## Enums
This library provides two types of enums used when sending the scoreboard:
- `DisplaySlot` - Used to specify where the scoreboard should be displayed
- `SortOrder` - Used as a way to sort the scoreboard (ascending or descending)

## Methods

## Managing View
- `send(?SortOrder $sortOrder = null, ?DisplaySlot $displaySlot = null): void` - Sends the scoreboard to the player
- `remove(): void` - Removes the scoreboard from the client's view
- `update(): void` - Forcefully updates the client's scoreboard (Throws an exception if the scoreboard is not already visible)

### Setting Data
This library provides multiple methods for updating the scoreboard:
- `setLines(array $lines, bool $clear = true, bool $update = true): void` - Sets the values of all lines
- `setLine(int $index, string $value, bool $update = true): void` - Sets the value of a specific line
- `removeLine(int $index, bool $update = true): void` - Removes a specific line
- `clear(bool $update = true): void` - Clears the scoreboard
- `setDisplaySlot(DisplaySlot $slot, bool $update = true): void` - Sets the display slot of the scoreboard
- `setSortOrder(SortOrder $order, bool $update = true): void` - Sets the sort order of the scoreboard

## Example
```php
assert($player instanceof Player);

$scoreboard = new Scoreboard(
    player: $player,
    title: "Test Scoreboard",
    lines: [
        0 => "Name: {$player->getName()}",
        1 => "",
        2 => "Test Entry",
        3 => "",
        4 => "Test Entry 2",
    ]
);

// Sends the scoreboard to the player
$scoreboard->send(
    slot: DisplaySlot::SIDEBAR(),
    order: SortOrder::ASCENDING()
);

// Sets the line and sends it to the player (if visible)
$scoreboard->setLine(index: 2, value: "New Text Entry");

// Set multiple lines
$scoreboard->setLines(
    lines: [
        0 => "Display Name: {$player->getDisplayName()}",
        1 => "Health: " . (string) round($player->getHealth(), 2),
        2 => "",
        3 => "Updated Test Entry",
    ],
    // If clear is set to true, it'll clear the scoreboard before setting the lines
    clear: true
);

// Remove a line
$scoreboard->removeLine(index: 2);
// Changes the current sort order
$scoreboard->setSortOrder(SortOrder::DESCENDING());
// Removes the scoreboard from the player's view
$scoreboard->remove();
```


## Issues
Any issues / suggestions with this library can be reported [here](https://github.com/Valiant-Bedrock/libscoreboard/issues).