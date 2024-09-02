<?php

declare(strict_types=1);

namespace wavycraft\mine;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\block\BlockTypeIds;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\Position;
use pocketmine\utils\Config;

use wavycraft\mine\commands\MineFTCommand;
use wavycraft\mine\commands\MineCommand;
use wavycraft\mine\api\FloatingTextAPI;

class Mine extends PluginBase implements Listener {

    private static $instance;
    public $mineData;

    protected function onLoad() : void{
        self::$instance = $this;
    }

    protected function onEnable() : void{
        $this->mineData = new Config($this->getDataFolder() . "mine_data.json", Config::JSON);

        $this->getServer()->getCommandMap()->registerAll("MineTracker", [
            new MineCommand(),
            new MineFTCommand()
        ]);
        
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable() : void{
        $this->mineData->save();
        FloatingTextAPI::saveFile();
    }

    public static function getInstance() : self{
        return self::$instance;
    }

    public function onBlockBreak(BlockBreakEvent $event) {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        if ($this->isOre($block)) {
            $this->incrementOreCount($player, $block);
        }
    }

    public function onChunkLoad(ChunkLoadEvent $event) {
        $filePath = $this->getDataFolder() . "floating_text_data.json";
        FloatingTextAPI::loadFromFile($filePath);
    }

    public function onChunkUnload(ChunkUnloadEvent $event) {
        FloatingTextAPI::saveFile();
    }

    public function onWorldUnload(WorldUnloadEvent $event) {
        FloatingTextAPI::saveFile();
    }

    public function onEntityTeleport(EntityTeleportEvent $event) {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            $fromWorld = $event->getFrom()->getWorld();
            $toWorld = $event->getTo()->getWorld();
        
            if ($fromWorld !== $toWorld) {
                foreach (FloatingTextAPI::$floatingText as $tag => [$position, $floatingText]) {
                    if ($position->getWorld() === $fromWorld) {
                        FloatingTextAPI::makeInvisible($tag);
                    }
                }
            }
        }
    }

    public function isOre($block) : bool{
        return in_array($block->getTypeId(), [
            BlockTypeIds::COAL_ORE,
            BlockTypeIds::IRON_ORE,
            BlockTypeIds::GOLD_ORE,
            BlockTypeIds::DIAMOND_ORE,
            BlockTypeIds::EMERALD_ORE,
            BlockTypeIds::REDSTONE_ORE,
            BlockTypeIds::LAPIS_LAZULI_ORE,
            BlockTypeIds::NETHER_QUARTZ_ORE,
            BlockTypeIds::NETHER_GOLD_ORE,
            BlockTypeIds::COPPER_ORE,
            BlockTypeIds::DEEPSLATE_COAL_ORE,
            BlockTypeIds::DEEPSLATE_IRON_ORE,
            BlockTypeIds::DEEPSLATE_GOLD_ORE,
            BlockTypeIds::DEEPSLATE_DIAMOND_ORE,
            BlockTypeIds::DEEPSLATE_EMERALD_ORE,
            BlockTypeIds::DEEPSLATE_REDSTONE_ORE,
            BlockTypeIds::DEEPSLATE_LAPIS_LAZULI_ORE,
            BlockTypeIds::DEEPSLATE_COPPER_ORE,
            BlockTypeIds::ANCIENT_DEBRIS
        ]);
    }

    public function incrementOreCount(Player $player, $block) {
        $oreType = $this->getOreName($block);

        $topPlayers = $this->mineData->getAll();

        if (isset($topPlayers[$player->getName()])) {
            if (isset($topPlayers[$player->getName()][$oreType])) {
                $topPlayers[$player->getName()][$oreType]++;
            } else {
                $topPlayers[$player->getName()][$oreType] = 1;
            }
        } else {
            $topPlayers[$player->getName()] = [$oreType => 1];
        }

        $this->mineData->setAll($topPlayers);
        $this->mineData->save();

        $this->updateFloatingText($player, $topPlayers);
    }

    public function getOreName($block) : string{
        $typeId = $block->getTypeId();

        return match ($typeId) {
            BlockTypeIds::COAL_ORE => "Coal",
            BlockTypeIds::IRON_ORE => "Iron",
            BlockTypeIds::GOLD_ORE => "Gold",
            BlockTypeIds::DIAMOND_ORE => "Diamond",
            BlockTypeIds::EMERALD_ORE => "Emerald",
            BlockTypeIds::REDSTONE_ORE => "Redstone",
            BlockTypeIds::LAPIS_LAZULI_ORE => "Lapis Lazuli",
            BlockTypeIds::NETHER_QUARTZ_ORE => "Quartz",
            BlockTypeIds::COPPER_ORE => "Copper",
            BlockTypeIds::ANCIENT_DEBRIS => "Ancient Debris",
            BlockTypeIds::NETHER_GOLD_ORE => "Nether Gold",
            BlockTypeIds::DEEPSLATE_COAL_ORE => "Deepslate Coal",
            BlockTypeIds::DEEPSLATE_IRON_ORE => "Deepslate Iron",
            BlockTypeIds::DEEPSLATE_GOLD_ORE => "Deepslate Gold",
            BlockTypeIds::DEEPSLATE_DIAMOND_ORE => "Deepslate Diamond",
            BlockTypeIds::DEEPSLATE_EMERALD_ORE => "Deepslate Emerald",
            BlockTypeIds::DEEPSLATE_REDSTONE_ORE => "Deepslate Redstone",
            BlockTypeIds::DEEPSLATE_LAPIS_LAZULI_ORE => "Deepslate Lapis Lazuli",
            BlockTypeIds::DEEPSLATE_COPPER_ORE => "Deepslate Copper",
            default => "unknown"
        };
    }

    public function updateFloatingText(Player $player, array $topPlayers) {
        $tag = "top_ores";

        if (!isset(FloatingTextAPI::$floatingText[$tag])) {
            $position = $player->getPosition();
            FloatingTextAPI::create($position, $tag, "");
        }

        $text = "§l§7-=Top 10 Ore Miners=-\n";
        $rank = 1;
        foreach ($topPlayers as $playerName => $oreData) {
            $totalOres = is_array($oreData) ? array_sum($oreData) : $oreData;
            $text .= "§e{$rank}.§f {$playerName}: §e{$totalOres} ores\n";
            $rank++;
        }

        FloatingTextAPI::update($tag, $text);
    }

    public function getTopPlayers(int $limit = 10, string $oreType = null) : array{
        $allData = $this->mineData->getAll();
        $topPlayers = [];

        foreach ($allData as $playerName => $ores) {
            $oreCount = $oreType ? ($ores[$oreType] ?? 0) : array_sum($ores);
            $topPlayers[$playerName] = $oreCount;
        }

        arsort($topPlayers);
        return array_slice($topPlayers, 0, $limit, true);
    }

    public function displayTopPlayers(Player $player, string $oreType = null) {
        $topPlayers = $this->getTopPlayers(10, $oreType);

        $text = "§l§7-=Top 10 Ore Miners=-\n";
        $rank = 1;
        foreach ($topPlayers as $playerName => $oreCount) {
            $text .= "§e{$rank}§f. {$playerName}: §e{$oreCount} ores\n";
            $rank++;
        }

        $position = $player->getPosition();
        FloatingTextAPI::create($position, "top_ores", $text);
    }
}