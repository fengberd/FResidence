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
		$this->config=new Config($this->getDataFolder().'config.yml',Config::YAML,array());
		$names=array(
			'Provider',
			'landItem',
			'blockMoney',
			'moneyName');
		$defaults=array(
			'yaml',
			Item::WOODEN_HOE,
			0.05,
			'节操');
		foreach($names as $key=>$_name)
		{
			if(!$this->config->exists($_name))
			{
				$this->config->set($_name,$defaults[$key]);
			}
		}
		$this->landItem=(int)$this->config->get('landItem');
		$this->blockMoney=$this->config->get('blockMoney')*1;
		$this->moneyName=$this->config->get('moneyName');
		switch(strtolower($this->config->get('Provider')))
		{
		/*case 'mysql':
			break;
		case 'sqlite':
		case 'sqlite3':
			break;*/
		default:
			$this->getLogger()->warning('配置错误:不支持的Provider类型,已切换为yaml模式');
		case 'yaml':
		case 'yml':
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
		case 'create':
			if(!$sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请在游戏中执行这个指令');
				break;
			}
			if(!isset($this->select[$sender->getName()]) || !$this->select[$sender->getName()]->isSelectFinish())
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请先使用选点工具来选择两个点再进行圈地');
				break;
			}
			if(!isset($args[1]))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			if(strlen($args[1])<=0 || strlen($args[1])>=60)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 无效领地名称');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			if($rid!==false && $this->provider->getResidence($rid)!==false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 已存在重名领地');
				break;
			}
			$select1=$this->select[$sender->getName()]->getP1();
			$select2=$this->select[$sender->getName()]->getP2();
			$money=$this->blockMoney*abs($select1->getX()-$select2->getX())*abs($select1->getY()-$select2->getY())*abs($select1->getZ()-$select2->getZ());
			if(!$sender->isOp() && $money>IncludeAPI::Economy_getMoney($sender))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有足够的'.$this->moneyName.'来圈地 ,需要 '.$money.' '.$this->moneyName);
				break;
			}
			$break=false;
			$arr=$this->getVector3Array($select1,$select2);
			$level=$sender->getLevel()->getFolderName();
			foreach($arr as $v3)
			{
				if($this->provider->queryResidenceByPosition($v3,$level)!==false)
				{
					$break=true;
					$sender->sendMessage('[FResidence] 不能覆盖别人的领地 ,检测到覆盖坐标 :');
					$sender->sendMessage('[FResidence] X:'.$v3->getX().',Y:'.$v3->getY().',Z:'.$v3->getZ());
					break;
				}
				unset($v3);
			}
			if($break)
			{
				break;
			}
			$this->provider->addResidence($select1,$select2,$sender,$args[1]);
			IncludeAPI::Economy_setMoney($sender,IncludeAPI::Economy_getMoney($sender)-$money);
			$this->select[$sender->getName()]->setP1(false);
			$this->select[$sender->getName()]->setP2(false);
			$sender->sendMessage('[FResidence] 领地创建成功 ,花费 '.$money.' '.$this->moneyName);
			break;
		case 'remove':
			if(!isset($args[1]))
			{
				$sender->sendMessage('[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			$res=$this->provider->getResidence($rid);
			if($rid===false || $res===false)
			{
				$sender->sendMessage('[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] 你没有权限移除这块领地');
				break;
			}
			$this->provider->removeResidence($rid);
			$sender->sendMessage('[FResidence] 领地移除成功');
			break;
		case 'message':
			if(!isset($args[2]))
			{
				$sender->sendMessage('[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			$args[2]=strtolower($args[2]);
			if($args[2]!='enter' && $args[2]!='leave' && $args[2]!='permission')
			{
				$sender->sendMessage('[FResidence] 错误的消息索引 ,只能为以下值的任意一个 :\nenter - 进入消息\nleave - 离开消息\npermission - 没有权限消息');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			if($rid===false)
			{
				$sender->sendMessage('[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] 你没有权限修改这块领地的消息');
				break;
			}
			$this->provider->setResidenceMessage($rid,$args[2],$args[3]);
			$sender->sendMessage('[FResidence] 领地消息设置成功');
			break;
		case 'help':
		case '？':
		case '?':
			$help='=====FResidence commands=====\n';
			$help.='/res create <名称> - 创建一个领地\n';
			$help.='/res remove <名称> - 移除指定名称的领地\n';
			$help.='/res message <领地> <索引> <内容> - 设置领地的消息内容\n     - 注 :索引可为enter/leave/permission\n';
			$help.='/res help - 查看帮助\n';
			$sender->sendMessage($help);
			break;
		default:
			$sender->sendMessage('[FResidence] 使用 /res help 查看帮助');
			break;
		}
		unset($sender,$command,$label,$help,$rid,$res,$resarr,$break,$select1,$select2,$level,$args);
		return true;
	}
	
	public function onPlayerInteract(PlayerInteractEvent $event)
	{
		if($event->getBlock()->getX()!==0 && $event->getBlock()->getY()!==0 && $event->getBlock()->getZ()!==0 && ($res=$this->provider->queryResidenceByPosition($event->getBlock()))!==false && ($res=$this->provider->getResidence($res))!==false && $res->getOwner()!==$event->getPlayer()->getName())
		{
			$msg=$res->getMessage('permission');
			$event->getPlayer()->sendMessage($msg);
			$event->setCancelled();
		}
		else if($event->getBlock()->getX()!==0 && $event->getBlock()->getY()!==0 && $event->getBlock()->getZ()!==0 && $event->getItem()->getId()==$this->landItem)
		{
			$this->select[$event->getPlayer()->getName()]->setP1($event->getBlock());
			$event->getPlayer()->sendMessage('[FResidence] 已设置第一个点');
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
		$res=$this->provider->getResidence($rid);
		if(($rid===false || $res===false) && $this->select[$event->getPlayer()->getName()]->nowland!==false)
		{
			$res=$this->provider->getResidence($this->select[$event->getPlayer()->getName()]->nowland);
			$this->select[$event->getPlayer()->getName()]->nowland=false;
			$msg=$res->getMessage('leave');
			$msg=str_replace('%name',$res->getName(),$msg);
			$msg=str_replace('%owner',$res->getOwner(),$msg);
			$event->getPlayer()->sendMessage($msg);
		}
		else if($res!==false && $this->select[$event->getPlayer()->getName()]->nowland!==$rid)
		{
			$this->select[$event->getPlayer()->getName()]->nowland=$rid;
			$msg=$res->getMessage('enter');
			$msg=str_replace('%name',$res->getName(),$msg);
			$msg=str_replace('%owner',$res->getOwner(),$msg);
			$event->getPlayer()->sendMessage($msg);
		}
		unset($event,$res,$msg,$rid);
	}
	
	public function onBlockPlace(BlockPlaceEvent $event)
	{
		if(($res=$this->provider->queryResidenceByPosition($event->getBlock()))!==false && ($res=$this->provider->getResidence($res))!==false && $res->getOwner()!==$event->getPlayer()->getName())
		{
			$msg=$res->getMessage('permission');
			$event->getPlayer()->sendMessage($msg);
			$event->setCancelled();
		}
		unset($event,$res,$msg);
	}
	
	public function onBlockBreak(BlockBreakEvent $event)
	{
		if(($res=$this->provider->queryResidenceByPosition($event->getBlock()))!==false && ($res=$this->provider->getResidence($res))!==false && $res->getOwner()!==$event->getPlayer()->getName())
		{
			$msg=$res->getMessage('permission');
			$event->getPlayer()->sendMessage($msg);
			$event->setCancelled();
		}
		else if($event->getItem()->getId()==$this->landItem)
		{
			$this->select[$event->getPlayer()->getName()]->setP2($event->getBlock());
			$event->getPlayer()->sendMessage('[FResidence] 已设置第二个点');
			$event->setCancelled();
		}
		unset($event,$res,$msg);
	}
	
	public function getVector3Array($pos1,$pos2)
	{
		if($pos1 instanceof Vector3)
		{
			$x1=$pos1->getX();
			$y1=$pos1->getY();
			$z1=$pos1->getZ();
		}
		else
		{
			$x1=$pos1['x'];
			$y1=$pos1['y'];
			$z1=$pos1['z'];
		}
		if($pos2 instanceof Vector3)
		{
			$x2=$pos2->getX();
			$y2=$pos2->getY();
			$z2=$pos2->getZ();
		}
		else
		{
			$x2=$pos2['x'];
			$y2=$pos2['y'];
			$z2=$pos2['z'];
		}
		$return=array();
		$lowestX=min($x1,$x2);
		$lowestY=min($y1,$y2);
		$lowestZ=min($z1,$z2);
		$highestX=max($x1,$x2);
		$highestY=max($y1,$y2);
		$highestZ=max($z1,$z2);
		for($x=$lowestX;$x<=$highestX;$x++)
		{
			for($y=$lowestY;$y<=$highestY;$y++)
			{
				for($z=$lowestZ;$z<=$highestZ;$z++)
				{
					$return[]=new Vector3($x,$y,$z);
				}
			}
		}
		unset($pos1,$pos2,$x1,$x2,$y1,$y2,$z1,$z2,$x,$y,$z,$lowestX,$lowestY,$lowestZ,$highestX,$highestY,$highestZ);
		return $return;
	}
	
	public function getBoxVector3Array($pos1,$pos2)
	{
		if($pos1 instanceof Vector3)
		{
			$x1=$pos1->getX();
			$y1=$pos1->getY();
			$z1=$pos1->getZ();
		}
		else
		{
			$x1=$pos1['x'];
			$y1=$pos1['y'];
			$z1=$pos1['z'];
		}
		if($pos2 instanceof Vector3)
		{
			$x2=$pos2->getX();
			$y2=$pos2->getY();
			$z2=$pos2->getZ();
		}
		else
		{
			$x2=$pos2['x'];
			$y2=$pos2['y'];
			$z2=$pos2['z'];
		}
		$return=array();
		$n1=min($x1,$x2);
		$n2=min($y1,$y2);
		$n3=min($z1,$z2);
		for($x=0;$x<=max($x1,$x2)-min($x1,$x2);$x++)
		{
			for($z=0;$z<=max($z1,$z2)-min($z1,$z2);$z++)
			{
				$return[]=new Vector3($n1+$x,$y1,$n3+$z);
				$return[]=new Vector3($n1+$x,$y2,$n3+$z);
			}
		}
		for($y=0;$y<=max($y1,$y2)-min($y1,$y2);$y++)
		{
			for($x=0;$x<=max($x1,$x2)-min($x1,$x2);$x++)
			{
				$return[]=new Vector3($n1+$x,$n2+$y,$z1);
				$return[]=new Vector3($n1+$x,$n2+$y,$z2);
			}
		}
		for($z=0;$z<=max($z1,$z2)-min($z1,$z2);$z++)
		{
			for($y=0;$y<=max($y1,$y2)-min($y1,$y2);$y++)
			{
				$return[]=new Vector3($x1,$n2+$y,$n3+$z);
				$return[]=new Vector3($x1,$n2+$y,$n3+$z);
			}
		}
		unset($pos1,$pos2,$x1,$x2,$y1,$y2,$z1,$z2,$x,$y,$z,$n1,$n2,$n3);
		return $return;
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
