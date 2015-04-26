<?php
namespace FResidence;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;

use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerInteractEvent;

use FResidence\Provider\YAMLProvider;

class Main extends PluginBase implements Listener
{
	private static $obj;
	private $select=array();
	
	public static function getInstance()
	{
		return self::$obj;
	}
	
	public function onEnable()
	{
		if(!self::$obj instanceof Main)
		{
			self::$obj=$this;
		}
		@mkdir($this->getDataFolder());
		$this->config=new Config($this->getDataFolder()."config.yml",Config::YAML,array());
		$names=array("Provider","landItem","blockMoney","moneyName");
		$defaults=array("yaml",Item::WOODEN_HOE,0.05,"节操");
		foreach($names as $key=>$_name)
		{
			if(!$this->config->exists($_name))
			{
				$this->config->set($_name,$defaults[$key]);
			}
		}
		$this->landItem=(int)$this->config->get("landItem");
		$this->blockMoney=$this->config->get("blockMoney")*1;
		$this->moneyName=$this->config->get("moneyName");
		switch(strtolower($this->config->get("Provider")))
		{
			default:
				$this->getLogger()->warning("配置错误:不支持的Provider类型,已切换为yaml模式");
			case "yaml":
			case "yml":
				$this->provider=new YAMLProvider($this);
				break;
		}
		$this->config->save();
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args)
	{
		switch($args[0])
		{
		case "create":
			if(!$sender instanceof Player)
			{
				$sender->sendMessage("[FResidence] 请在游戏中执行这个指令");
				break;
			}
			if(!isset($this->select[$sender->getName()]) || !$this->select[$sender->getName()]->isSelectFinish())
			{
				$sender->sendMessage("[FResidence] 请先使用选点工具来选择两个点再进行圈地");
				break;
			}
			if(!isset($args[1]))
			{
				$sender->sendMessage("[FResidence] 请使用 /res help 查看帮助");
				break;
			}
			if(strlen($args[1])<=0 || strlen($args[1])>=60)
			{
				$sender->sendMessage("[FResidence] 无效领地名称");
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			if($rid!==false && $this->provider->getResidenceByID($rid)!==false)
			{
				$sender->sendMessage("[FResidence] 已存在重名领地");
				break;
			}
			$resarr=$this->provider->getResidenceVector3Array($this->select[$sender->getName()]->getP1(),$this->select[$sender->getName()]->getP2());
			if(!$sender->isOp() && count($resarr)*$this->blockMoney>IncludeAPI::Economy_getMoney($sender))
			{
				$sender->sendMessage("[FResidence] 你没有足够的".$this->moneyName."来圈地");
				break;
			}
			$break=false;
			foreach($resarr as $vec3)
			{
				if($this->provider->queryResidenceByPosition($vec3,$sender->getLevel()->getFolderName())!==false)
				{
					$break=true;
					$sender->sendMessage("[FResidence] 不能覆盖别人的领地 ,检测到覆盖坐标 :");
					$sender->sendMessage("[FResidence] X:".$vec3->getX().",Y:".$vec3->getY().",Z:".$vec3->getZ());
					break;
				}
			}
			if($break)
			{
				break;
			}
			$this->provider->addResidence($this->select[$sender->getName()]->getP1(),$this->select[$sender->getName()]->getP2(),$sender,$args[1]);
			IncludeAPI::Economy_setMoney($sender,IncludeAPI::Economy_getMoney($sender)-(count($resarr)*$this->blockMoney));
			$this->select[$sender->getName()]->setP1(false);
			$this->select[$sender->getName()]->setP2(false);
			$sender->sendMessage("[FResidence] 领地创建成功 ,大小 ".count($resarr)." 方块 ,花费 ".count($resarr)*$this->blockMoney." ".$this->moneyName);
			break;
		case "remove":
			if(!isset($args[1]))
			{
				$sender->sendMessage("[FResidence] 请使用 /res help 查看帮助");
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			$res=$this->provider->getResidenceByID($rid);
			if($rid===false || $res===false)
			{
				$sender->sendMessage("[FResidence] 不存在这块领地");
				break;
			}
			if(!$sender->isOp () && $res["owner"]!==strtolower($sender->getName()))
			{
				$sender->sendMessage("[FResidence] 你没有权限移除这块领地");
				break;
			}
			$this->provider->removeResidenceByID($rid);
			$sender->sendMessage("[FResidence] 领地移除成功");
			break;
		case "message":
			if(!isset($args[2]))
			{
				$sender->sendMessage("[FResidence] 请使用 /res help 查看帮助");
				break;
			}
			$args[2]=strtolower($args[2]);
			if($args[2]!="enter" && $args[2]!="leave" && $args[2]!="permission")
			{
				$sender->sendMessage("[FResidence] 错误的消息索引 ,只能为以下值的任意一个 :\nenter - 进入消息\nleave - 离开消息\npermission - 没有权限消息");
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			if($rid===false)
			{
				$sender->sendMessage("[FResidence] 不存在这块领地");
				break;
			}
			if(!$sender->isOp () && $res["owner"]!==strtolower($sender->getName()))
			{
				$sender->sendMessage("[FResidence] 你没有权限修改这块领地的消息");
				break;
			}
			$this->provider->setResidenceMessage($rid,$args[2],$args[3]);
			$sender->sendMessage("[FResidence] 领地消息设置成功");
			break;
		case "help":
		case "？":
		case "?":
			$help="=====FResidence commands=====\n";
			$help.="/res create <名称> - 创建一个领地\n";
			$help.="/res remove <名称> - 移除指定名称的领地\n";
			$help.="/res message <领地> <索引> <内容> - 设置领地的消息内容\n    - 注 :索引可为enter/leave/permission\n";
			$help.="/res help - 查看帮助\n";
			$sender->sendMessage($help);
			break;
		default:
			$sender->sendMessage("[FResidence] 使用 /res help 查看帮助");
			break;
		}
		unset($sender,$command,$label,$help,$rid,$res,$resarr,$break,$vec3,$args);
		return true;
	}
	
	public function onPlayerInteract(PlayerInteractEvent $event)
	{
		if($event->getBlock()->getX()!==0 && $event->getBlock()->getY()!==0 && $event->getBlock()->getZ()!==0 && ($res=$this->provider->queryResidenceByPosition($event->getBlock()))!==false && ($res=$this->provider->getResidenceByID($res))!==false && $res["owner"]!==$event->getPlayer()->getName())
		{
			$msg=$this->provider->getResidenceMessage($res,"permission");
			$event->getPlayer()->sendMessage($msg);
			$event->setCancelled();
		}
		else if($event->getBlock()->getX()!==0 && $event->getBlock()->getY()!==0 && $event->getBlock()->getZ()!==0 && $event->getItem()->getId()==$this->landItem)
		{
			$this->select[$event->getPlayer()->getName()]->setP1($event->getBlock());
			$event->getPlayer()->sendMessage("[FResidence] 已设置第一个点");
			$event->setCancelled();
		}
		unset($event,$res,$msg);
	}
	
	public function onPlayerJoin(PlayerJoinEvent $event)
	{
		$this->select[$event->getPlayer()->getName()]=new PlayerInfo();
		unset($event);
	}
	
	public function onPlayerQuit(PlayerQuitEvent $event)
	{
		unset($this->select[$event->getPlayer()->getName()]);
		unset($event);
	}
	
	public function onPlayerMove(PlayerMoveEvent $event)
	{
		$rid=$this->provider->queryResidenceByPosition($event->getTo());
		$res=$this->provider->getResidenceByID($rid);
		if(($rid===false || $res===false) && $this->select[$event->getPlayer()->getName()]->nowland!==false)
		{
			$res=$this->provider->getResidenceByID($this->select[$event->getPlayer()->getName()]->nowland);
			$this->select[$event->getPlayer()->getName()]->nowland=false;
			$msg=$this->provider->getResidenceMessage($res,"leave");
			$msg=str_replace("%name",$res["name"],$msg);
			$msg=str_replace("%owner",$res["owner"],$msg);
			$event->getPlayer()->sendMessage($msg);
		}
		else if($res!==false && $this->select[$event->getPlayer()->getName()]->nowland!==$rid)
		{
			$this->select[$event->getPlayer()->getName()]->nowland=$rid;
			$msg=$this->provider->getResidenceMessage($res,"enter");
			$msg=str_replace("%name",$res["name"],$msg);
			$msg=str_replace("%owner",$res["owner"],$msg);
			$event->getPlayer()->sendMessage($msg);
		}
		unset($event,$res,$msg,$rid);
	}
	
	public function onBlockPlace(BlockPlaceEvent $event)
	{
		if(($res=$this->provider->queryResidenceByPosition($event->getBlock()))!==false && ($res=$this->provider->getResidenceByID($res))!==false && $res["owner"]!==$event->getPlayer()->getName())
		{
			$msg=$this->provider->getResidenceMessage($res,"permission");
			$event->getPlayer()->sendMessage($msg);
			$event->setCancelled();
		}
		unset($event,$res,$msg);
	}
	
	public function onBlockBreak(BlockBreakEvent $event)
	{
		if(($res=$this->provider->queryResidenceByPosition($event->getBlock()))!==false && ($res=$this->provider->getResidenceByID($res))!==false && $res["owner"]!==$event->getPlayer()->getName())
		{
			$msg=$this->provider->getResidenceMessage($res,"permission");
			$event->getPlayer()->sendMessage($msg);
			$event->setCancelled();
		}
		else if($event->getItem()->getId()==$this->landItem)
		{
			$this->select[$event->getPlayer()->getName()]->setP2($event->getBlock());
			$event->getPlayer()->sendMessage("[FResidence] 已设置第二个点");
			$event->setCancelled();
		}
		unset($event,$res,$msg);
	}
}

class PlayerInfo
{
	public $p1=false;
	public $p2=false;
	public $nowland=false;
	
	public function isSelectFinish()
	{
		return ($this->p1!==false && $this->p2!==false);
	}
	
	public function getP1()
	{
		return $this->p1;
	}
	
	public function getP2()
	{
		return $this->p2;
	}
	
	public function setP1($pos)
	{
		$this->p1=$pos;
		unset($pos);
	}
	
	public function setP2($pos)
	{
		$this->p2=$pos;
		unset($pos);
	}
}
