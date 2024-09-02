<?php

declare(strict_types=1);

namespace wavycraft\mine\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

use wavycraft\mine\Mine;
use jojoe77777\FormAPI\SimpleForm;

class MineCommand extends Command implements PluginOwned {

    public function __construct() {
        parent::__construct("mines", "Show your ore mining stats", null, ["m"]);
        $this->setPermission("minetracker.ores");
    }

    public function getOwningPlugin() : Plugin{
        return Mine::getInstance();
    }

    public function execute(CommandSender $sender, string $label, array $args) : bool{
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return false;
        }

        $this->showMineStats($sender);
        return true;
    }

    public function showMineStats(Player $player) {
        $plugin = Mine::getInstance();
        $playerName = $player->getName();
        $playerData = $plugin->mineData->get($playerName, []);

        $form = new SimpleForm(function (Player $player, ?int $data = null) {
            //NOOP
        });

        $form->setTitle($player->getName() . " Ore Mining Status");
        $message = "Here are your ore mining stats:\n \n";

        if (empty($playerData)) {
            $message .= "§cYou haven't mined any ores yet!";
        } else {
            foreach ($playerData as $oreType => $count) {
                $message .= "§e" . ucfirst($oreType) . ": §f" . $count . "\n";
            }
        }

        $form->setContent($message);
        $form->addButton("Close");
        $player->sendForm($form);
    }
}