<?php

declare(strict_types=1);

namespace wavycraft\mine\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

use wavycraft\mine\Mine;

class MineFTCommand extends Command implements PluginOwned {

    public function __construct() {
        parent::__construct("mineft", "Spawn in the mine leaderboard", "/mineft", ["mft"]);
        $this->setPermission("minetracker.oresft");
    }

    public function getOwningPlugin() : plugin{
        return Crops::getInstance();
    }

    public function execute(CommandSender $sender, string $label, array $args) : bool{
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return false;
        }

        Mine::getInstance()->displayTopPlayers($sender);
        return true;
    }
}