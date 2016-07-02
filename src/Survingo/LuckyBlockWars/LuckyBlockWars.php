<?php

/*
   Copyright 2016 Survingo
   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at
       http://www.apache.org/licenses/LICENSE-2.0
   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
limitations under the License.
*/

namespace Survingo\LuckyBlockWars;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use Survingo\LuckyBlockWars\tasks\WaitPopupTask;
use Survingo\LuckyBlockWars\tasks\StartGameTask;
use Survingo\LuckyBlockWars\tasks\StatusSignTask;

class LuckyBlockWars extends PluginBase implements Listener{
   
  public $running = false;
  
  public $prefix = ("[§6Lucky §eBlock §cWars§f] ");
  
  public $players = array();
  
  public $cfg;
   
  public function onEnable(){
     $this->getServer()->getLogger()->info($this->prefix . "Enabling " . $this->getDescription()->getFullName() . " by Survingo...");
     @mkdir($this->getDataFolder());
     $this->saveResource("config.yml");//$this->saveDefaultConfig();
     $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
     $this->cfg = $cfg->getAll();
     $this->getServer()->getScheduler()->scheduleRepeatingTask(new StatusSignTask($this), 20 * 3);
  }
  
  public function onDisable(){
     $this->getServer()->getLogger()->info($this->prefix . "Disabling plugin...");
  }
  
  public function onBlockBreak(BlockBreakEvent $event){
     if($event->getBlock()->getId() == $this->cfg["luckyblock-id"]){
        if($this->running === true){
           if($event->getPlayer()->hasPermission("lbw.game.use")){
              switch (mt_rand(1,3)){
                 case 1: $this->getRandom($this->unluckyBlockStuff($event->getBlock()));
                 break;
                 case 2: $this->getRandom($this->normalBlockStuff($event->getBlock()));
                 break;
                 case 3: $this->getRandom($this->luckyBlockStuff($event->getBlock()));
                 break;
              }
           }else{
              $event->setCancelled(true);
              $event->getPlayer()->sendMessage($this->cfg["not-allowed-to-use-luckyblock"]);
           }
        }else{
           $event->getPlayer()->sendMessage($this->cfg["game-is-not-running"]);
        }
     }
  }
 
 public function getRandom(array $things){
    if(is_array($things)) return $things[array_rand($things, 1)];
 }
 
 public function unluckyBlockStuff($block){
    return array(
       $boom = new \pocketmine\level\Explosion($block, mt_rand($this->cfg["min-explosion"], $this->cfg["max-explosion"]));
       $boom->explodeA();
 );}
 
 public function normalBlockStuff($block){
    return array(
       //$test->test();
 );}
 
 public function luckyBlockStuff($block){
    return array(
       //$test->test();
 );}
 
 public function onInteract(PlayerInteractEvent $event){
    if($event->getBlock()->getX() === $this->cfg["sign-x"] and $event->getBlock()->getY() === $this->cfg["sign-y"] and $event->getBlock()->getZ() === $this->cfg["sign-z"]){
       if($this->running == false){
          $this->addToGame($event->getPlayer()->getName());
       }else{
          $event->getPlayer()->sendMessage($this->cfg["game-is-running"]);
       }
    }
 }
 
 public function onDeath(PlayerDeathEvent $event){
    if($this->running == true){
       if(in_array($event->getEntity()->getName(), $this->players)){
          unset($this->players{array_search($event->getEntity()->getName(), $this->players)});
          $event->setDeathMessage($this->cfg["death-message"]);
          $event->getEntity()->teleport($this->getServer()->getLevelByName($this->cfg["respawn-level"])->getSafeSpawn());
       }
       if(count($this->players == 1)){
          $this->getServer()->getPlayer($this->players)->teleport($this->getServer()->getLevelByName($this->cfg["respawn-level"])->getSafeSpawn());
          $this->getServer()->broadcastMessage(str_replace(["{name}", "health"], [$this->players, $this->getServer()->getPlayer($this->players)->getHealth()], $this->cfg["won-broadcast"]));
          $this->getServer()->getPlayer(this->players)->setHealth(20);
          unset($this->players{array_search($this->players, $this->players)});
          $this->running = false;
       }
    }
 }
 
 public function onSignChange(SignChangeEvent $event){
    if($event->getLine(0) == "[LBW]" or $event->getLine(0) == "[LuckyBlock]" or $event->getLine(0) == "/lbw join"){
       if($event->getPlayer()->hasPermission("lbw.game.create-signs")){
          $this->getConfig()->set("sign-x", $event->getBlock()->getX());
          $this->getConfig()->save();
          $this->getConfig()->set("sign-y", $event->getBlock()->getY());
          $this->getConfig()->save();
          $this->getConfig()->set("sign-z", $event->getBlock()->getZ());
          $this->getConfig()->save();
          $this->getConfig()->set("sign-world", $event->getPlayer()->getLevel()->getName());
          $this->getConfig()->save();
          $this->getConfig()->set("sign-mode", true);
          $this->getConfig()->save;
          $event->setLine(0, "§l[§6L§eB§cW§f]");
          $event->setLine(1, "§aJoin");
       }else{
          $event->setLine(0, "No");
          $event->setLine(1, "Permission");
       }
    }
 }

 public function addToGame($name){
    if(count($this->players !== $this->cfg["needed-players"])){
       if(!in_array($name, $this->players)){
          array_push($this->players, $name);
          $this->getServer()->getPlayer($name)->teleport(new Position($this->cfg["lobby-x"], $this->cfg["lobby-y"], $this->cfg["lobby-z"], $this->cfg["lobby-world"]));
          return true;
       }
    }
 }
 
 public function startGame(){
    if(count($this->players == $this->cfg["needed-players"])){
       foreach($this->getServer()->getPlayer($this->players) as $player){
          $player->sendMessage("[LuckyBlockWars] Starting game...");
          $this->getServer()->getScheduler()->scheduleRepeatingTask(new StartGameTask($plugin), 20)->getTaskId();
          $this->getServer()->getScheduler()->cancelTask($this->waitPopup);
       }
    }else{
      $this->waitPopup = $this->getServer()->getScheduler()->scheduleRepeatingTask(new WaitPopupTask($plugin), 20)->getTaskId();
    }
  }
  
 public function onCommand(CommandSender $sender, Command $command, $label, array $args){
    switch(strtolower($command->getName())){
       case "lbw":
          if($sender instanceof Player){
             if(!(isset($args[0]))){
                if($sender->hasPermission("lbw.command")){
                   $this->getServer()->dispatchCommand($sender, "lbw help");
                }
             }
          }
          $arg = array_shift($args);
          switch($args){
             case "help":
             case "list-commands":
             case "list-cmds":
             case "list":
             case "?":
                if($sender->hasPermission("lbw.command.help")){
                   $sender->sendMessage("----------");
                   $sender->sendMessage($this->prefix . "List of sub-commands");
                   $sender->sendMessage("----------");
                   $sender->sendMessage("§2version: §fShows information about this plugin");
                   return true;
                }else{
                   $sender->sendMessage("§cYou don't have the permission to run the help!");
                   return true;
                }
                break;
             case "version":
             case "info":
             case "information":
                if($sender->hasPermission("lbw.command.version")){
                   $sender->sendMessage($this->prefix . "Developed by §lSurvingo§r.\nCurrent version installed: §7" . $this->getDescription()->getVersion());
                   return true;
                }
                break;
             case "join":
             case "enter":
             case "play":
                if($sender instanceof Player){
                   if($sender->hasPermission("lbw.command.join")){
                      $this->addToGame($sender);
                   }else{
                      $sender->sendMessage("§cYou do not have the permission to do that!");
                   }
                }else{
                   $sender->sendMessage("§cYou can not run that command via the console!");
                }
             default:
                $sender->sendMessage($this->prefix . "Unknown command. Type §7/lbw §fto get a list of commands.");
                return true;
                break;
          }
    }
 }
 
}
