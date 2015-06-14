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
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use FResidence\Provider\YAMLProvider;

use FResidence\event\ResidenceAddEvent;
use FResidence\event\ResidenceRemoveEvent;

class Main extends PluginBase implements Listener
{
	private static $obj;
	private $select=array();
	public static $NL="\n";
	private $perms=array('move',
		'build',
		'use',
		'pvp',
		'damage',
		'tp',
		'flow');
	
	public static function getInstance()
	{
		return self::$obj;
	}
	
	public function loadConfig()
	{
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
		$this->config->save();
	}
	
	public function onEnable()
	{
		if(!self::$obj instanceof Main)
		{
			self::$obj=$this;
		}
		$this->loadConfig();
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
		Item::addCreativeItem(Item::get($this->landItem,0));
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args)
	{
		if(!isset($args[0]))
		{
			$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
			unset($sender,$command,$label,$args);
			return true;
		}
		switch($args[0])
		{
		case 'reload':
			if(!$sender->isOp())
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 权限不足');
				unset($sender,$command,$label,$args);
				return false;
			}
			$this->loadConfig();
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 重载完成');
			break;
		case 'parse':
			if($sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限使用这个指令');
				break;
			}
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 开始转换领地数据');
			$land=new Config($this->getDataFolder().'../EconomyLand/Land.yml',Config::YAML,array());
			$cou=0;
			foreach($land->getAll() as $l)
			{
				$this->provider->addResidence(new Vector3($l['startX'],0,$l['startZ']),new Vector3($l['endX'],128,$l['endZ']),$l['owner'],'parse_'.$l['ID'],$l['level']);
				$cou++;
				unset($l);
			}
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 数据转换完成,共处理 '.$cou.' 块领地');
			break;
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
			foreach($this->provider->getAllResidences() as $r)
			{
				if($this->check($r->getStart(),$r->getEnd(),$select1,$select2))
				{
					$sender->sendMessage(TextFormat::RED.'[FResidence] 选区与领地 '.$r->getName().' 重叠 ,不能覆盖 !');
					unset($r);
					$break=true;
					break;
				}
				unset($r);
			}
			if($break)
			{
				break;
			}
			$this->getServer()->getPluginManager()->callEvent($ev=new ResidenceAddEvent($this,$money,$select1,$select2,$args[1],$sender));
			if($ev->isCancelled())
			{
				break;
			}
			$this->provider->addResidence($ev->getPos1(),$ev->getPos2(),$sender,$ev->getResName());
			IncludeAPI::Economy_setMoney($sender,IncludeAPI::Economy_getMoney($sender)-$ev->getMoney());
			$this->select[$sender->getName()]->setP1(false);
			$this->select[$sender->getName()]->setP2(false);
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 领地创建成功 ,花费 '.$money.' '.$this->moneyName);
			break;
		case 'remove':
			if(!isset($args[1]))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 权限不足');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			$res=$this->provider->getResidence($rid);
			if($rid===false || $res===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限移除这块领地');
				break;
			}
			$this->getServer()->getPluginManager()->callEvent($ev=new ResidenceRemoveEvent($this,$res));
			if($ev->isCancelled())
			{
				break;
			}
			$this->provider->removeResidence($rid);
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 领地移除成功');
			break;
		case 'give':
			if(!isset($args[2]) || $args[2]=='')
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 权限不足');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			$res=$this->provider->getResidence($rid);
			if($rid===false || $res===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限赠送这块领地');
				break;
			}
			$res->setOwner($args[2]);
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 成功把领地 '.$args[1].' 赠送给玩家 '.$args[2]);
			break;
		case 'removeall':
			if(!$sender instanceof Player)
			{
				if(!isset($args[1]))
				{
					$sender->sendMessage(TextFormat::RED.'[FResidence] 请在游戏中执行这个指令或指定要移除的玩家');
				}
				else
				{
					$sender->sendMessage(TextFormat::GREEN.'[FResidence] 成功移除玩家 '.$args[1].' 的所有领地 (操作 '.$this->provider->removeResidencesByOwner($args[1]).' 块领地)');
				}
				break;
			}
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 成功移除你的所有领地 (操作 '.$this->provider->removeResidencesByOwner($sender->getName()).' 块领地)');
			break;
		case 'info':
			if(isset($args[1]))
			{
				if(($res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1])))===false)
				{
					$sender->sendMessage(TextFormat::RED.'[FResidence] 不存在该名字的领地');
				}
				else
				{
					$sender->sendMessage('====FResidence 领地查询结果===='.self::$NL.'领地名 :'.$res->getName().self::$NL.'拥有者 :'.$res->getOwner().self::$NL.'大小 : '.$res->getSize().' 方块');
				}
				break;
			}
			//这里不能break
		case 'current':
			if(!$sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请在游戏中执行这个指令或指定要查询的领地');
				break;
			}
			if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($sender)))===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 当前位置没有领地');
			}
			else
			{
				$sender->sendMessage('====FResidence 领地查询结果===='.self::$NL.'领地名 :'.$res->getName().self::$NL.'拥有者 :'.$res->getOwner().self::$NL.'大小 : '.$res->getSize().' 方块');
			}
			break;
		case 'list':
			if($sender->isOp())
			{
				if(!isset($args[2]))
				{
					if(!$sender instanceof Player)
					{
						$sender->sendMessage(TextFormat::RED.'[FResidence] 请在游戏中执行这个指令或指定要查询的玩家');
						break;
					}
				}
				else
				{
					$target=$args[2];
				}
			}
			if(!isset($target))
			{
				$target=$sender->getName();
			}
			if(!isset($args[1]))
			{
				$page=1;
			}
			else
			{
				$page=(int)$args[1];
			}
			if($page<=0)
			{
				$page=1;
			}
			$arr=$this->provider->queryResidencesByOwner($target);
			if(count($arr)==0)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 该玩家没有任何一块领地');
				break;
			}
			if(($page-1)*5>count($arr))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 页码超出范围');
				break;
			}
			$all=(int)(count($arr)/5);
			if($all<=0)
			{
				$all=0;
			}
			$all++;
			$help=TextFormat::GREEN.'====Residence List ['.$page.'/'.$all.']===='.self::$NL;
			$page--;
			foreach($arr as $key=>$res)
			{
				if($page*5<=$key && ($page+1)*5>$key)
				{
					$help.=TextFormat::YELLOW.$res->getName().' - 大小 '.$res->getSize().' 方块'.self::$NL;
				}
				unset($res,$key);
			}
			$sender->sendMessage($help);
			break;
		case 'listall':
			if(!$sender->isOp())
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限使用这个指令');
				break;
			}
			if(!isset($args[1]))
			{
				$page=1;
			}
			else
			{
				$page=(int)$args[1];
			}
			if($page<=0)
			{
				$page=1;
			}
			$arr=$this->provider->getAllResidences();
			if(count($arr)==0)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 服务器里还没创建过任何领地');
				break;
			}
			if(($page-1)*5>count($arr))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 页码超出范围');
				break;
			}
			$all=(int)(count($arr)/5);
			if($all<=0)
			{
				$all=0;
			}
			$all++;
			$help=TextFormat::GREEN.'====All Residences ['.$page.'/'.$all.']===='.self::$NL;
			$page--;
			foreach($arr as $key=>$res)
			{
				if($page*5<=$key && ($page+1)*5>$key)
				{
					$help.=TextFormat::YELLOW.$res->getName().' - 大小 '.$res->getSize().' 方块'.self::$NL;
				}
				unset($res,$key);
			}
			$sender->sendMessage($help);
			break;
		case 'message':
			if(!isset($args[2]))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 权限不足');
				break;
			}
			$args[2]=strtolower($args[2]);
			if($args[2]!='enter' && $args[2]!='leave' && $args[2]!='permission')
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 错误的消息索引 ,只能为以下值的任意一个 :/nenter - 进入消息/nleave - 离开消息/npermission - 没有权限消息');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限修改这块领地的消息');
				break;
			}
			$res->setMessage($args[2],$args[3]);
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 成功设置领地 '.$args[1].' 的 '.$args[2].' 消息数据');
			break;
		case 'default':
			if(!isset($args[1]))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 权限不足');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限修改这块领地的权限');
				break;
			}
			$res->resetPermission();
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 成功重置领地 '.$args[1].' 的权限数据');
			break;
		case 'set':
			if(!isset($args[3]))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 权限不足');
				break;
			}
			$args[2]=strtolower($args[2]);
			if(!in_array($args[2],$this->perms))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 错误的权限索引 ,只能为以下值的任意一个 :'.self::$NL.
					'move - 玩家移动权限'.self::$NL.
					'build - 破坏/放置权限'.self::$NL.
					'use - 使用工作台/箱子等权限'.self::$NL.
					'pvp - PVP权限'.self::$NL.
					'damage - 是否能受到伤害'.self::$NL.
					'tp - 传送到此领地的权限'.self::$NL.
					'flow - 液体流动权限');
				break;
			}
			$args[3]=strtolower($args[3]);
			if($args[3]!='true' && $args[3]!='false')
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 错误的权限值 ,只能为以下值的任意一个 :'.self::$NL.TextFormat::RED.'true - 开放此权限'.self::$NL.TextFormat::RED.'false - 只有你自己能使用这个权限');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限修改这块领地的权限');
				break;
			}
			$res->setPermission($args[2],$args[3]);
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 成功设置领地 '.$args[1].' 的权限 '.$args[2]);
			break;
		case 'pset':
			if(!isset($args[4]))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 权限不足');
				break;
			}
			$args[2]=strtolower($args[2]);
			if($args[2]=='' || preg_match('#^[a-zA-Z0-9_]{3,16}$#', $args[2])==0)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 无效的用户名');
				break;
			}
			$args[3]=strtolower($args[3]);
			if($args[3]!='move' && $args[3]!='build' && $args[3]!='use' && $args[3]!='pvp' && $args[3]!='tp')
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 错误的权限索引 ,只能为以下值的任意一个 :'.self::$NL.
					'move - 玩家移动权限'.self::$NL.
					'build - 破坏/放置权限'.self::$NL.
					'use - 使用工作台/箱子等权限'.self::$NL.
					'pvp - PVP权限'.self::$NL.
					'tp - 传送到此领地的权限');
				break;
			}
			$args[4]=strtolower($args[4]);
			if($args[4]!='true' && $args[4]!='false' && $args[4]!='remove')
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 错误的权限值 ,只能为以下值的任意一个 :'.self::$NL.TextFormat::RED.'true - 开放此权限'.self::$NL.TextFormat::RED.'false - 只有你自己能使用这个权限');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限修改这块领地的权限');
				break;
			}
			$res->setPlayerPermission($args[2],$args[3],$args[4]);
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 成功设置玩家 '.$args[2].' 的领地权限 '.$args[3]);
			break;
		case 'tp':
			if(!isset($args[1]))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请使用 /res help 查看帮助');
				break;
			}
			if(!$sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请在游戏中执行这个指令');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 不存在这块领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()) && !$res->getPlayerPermission($sender->getName(),'tp'))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限传送到这块领地');
				break;
			}
			$sender->teleport($res->getTeleportPos());
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 传送到领地 '.$args[1]);
			break;
		case 'tpset':
			if(!$sender instanceof Player)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 请在游戏中执行这个指令');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByPosition($sender));
			if($res===false)
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 这个位置没有领地');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage(TextFormat::RED.'[FResidence] 你没有权限修改该领地的传送点');
				break;
			}
			$res->setTeleportPos($sender);
			$sender->sendMessage(TextFormat::GREEN.'[FResidence] 领地 '.$res->getName().' 的传送点修改成功');
			break;
		case 'help':
		case '？':
		case '?':
			if(isset($args[1]))
			{
				$page=(int)$args[1];
			}
			else
			{
				$page=1;
			}
			$help='';
			switch($page)
			{
			default:
				$page=1;
			case 1:
				$help.='/res create <名称> - 创建一个领地'.self::$NL;
				$help.='/res remove <名称> - 移除指定名称的领地'.self::$NL;
				$help.=TextFormat::RED.'/res removeall '.($sender instanceof Player?'':'<玩家ID> ').'- 移除'.($sender instanceof Player?'你':'某玩家').'的所有领地'.self::$NL;
				$help.='/res message <领地> <索引> <内容> - 设置领地的消息内容'.self::$NL;
				$help.='/res set <领地> <权限> <true/false> - 设置领地权限'.self::$NL;
				break;
			case 2:
				$help.='/res pset <领地> <玩家> <权限> <true/false> - 设置某玩家的领地权限'.self::$NL;
				$help.='/res give <领地> <玩家> - 把领地赠送给某玩家'.self::$NL;
				$help.='/res tp <领地> - 传送到某领地'.self::$NL;
				$help.='/res tpset - 设置当前坐标为当前领地传送点'.self::$NL;
				$help.='/res help - 查看帮助'.self::$NL;
				break;
			case 3:
				$help.='/res info <领地> - 查询指定领地信息'.self::$NL;
				$help.='/res current - 查询当前所在领地信息'.self::$NL;
				break;
			}
			$help='=====FResidence commands ['.$page.'/3]====='.self::$NL.$help;
			$sender->sendMessage($help);
			break;
		default:
			$sender->sendMessage(TextFormat::RED.'[FResidence] 使用 /res help 查看帮助');
			break;
		}
		unset($sender,$command,$label,$help,$rid,$res,$resarr,$break,$select1,$select2,$level,$args);
		return true;
	}
	
	/**
	 * @param PlayerInteractEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onPlayerInteract(PlayerInteractEvent $event)
	{
		if($event->getAction()==PlayerInteractEvent::RIGHT_CLICK_BLOCK)
		{
			if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$event->getPlayer()->isOp() && ($this->isProtectBlock($event->getBlock()) || $this->isBlockedItem($event->getItem())) && !$res->getPlayerPermission($event->getPlayer()->getName(),'use'))
			{
				$msg=$res->getMessage('permission');
				$event->getPlayer()->sendMessage($msg);
				$event->setCancelled();
			}
			else if($event->getItem()->getId()==$this->landItem)
			{
				$this->select[$event->getPlayer()->getName()]->setP1($event->getBlock());
				$event->getPlayer()->sendMessage('[FResidence] 已设置第一个点');
				$event->setCancelled();
			}
		}
		unset($event,$res,$msg);
	}
	
	/**
	 * @param PlayerMoveEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onPlayerMove(PlayerMoveEvent $event)
	{
		$res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getTo()));
		if($res!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$event->getPlayer()->isOp() && !$res->getPlayerPermission($event->getPlayer()->getName(),'move'))
		{
			$event->setCancelled();
			$event->getPlayer()->sendPopup($res->getMessage('permission'));
		}
		else if($res===false && $this->select[$event->getPlayer()->getName()]->nowland!==false)
		{
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($this->select[$event->getPlayer()->getName()]->nowland));
			if($res!==false)
			{
				$this->select[$event->getPlayer()->getName()]->nowland=false;
				$msg=$res->getMessage('leave');
				$msg=str_replace('%name',$res->getName(),$msg);
				$msg=str_replace('%owner',$res->getOwner(),$msg);
				$event->getPlayer()->sendMessage($msg);
			}
		}
		else if($res!==false && $this->select[$event->getPlayer()->getName()]->nowland!==$res->getName())
		{
			$this->select[$event->getPlayer()->getName()]->nowland=$res->getName();
			$msg=$res->getMessage('enter');
			$msg=str_replace('%name',$res->getName(),$msg);
			$msg=str_replace('%owner',$res->getOwner(),$msg);
			$event->getPlayer()->sendMessage($msg);
		}
		unset($event,$res,$msg);
	}
	
	/**
	 * @param BlockPlaceEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onBlockPlace(BlockPlaceEvent $event)
	{
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$res->getPlayerPermission($event->getPlayer()->getName(),'build') && !$event->getPlayer()->isOp())
		{
			$msg=$res->getMessage('permission');
			$event->getPlayer()->sendMessage($msg);
			$event->setCancelled();
		}
		unset($event,$res,$msg);
	}
	
	/**
	 * @param BlockBreakEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onBlockBreak(BlockBreakEvent $event)
	{
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$res->getPlayerPermission($event->getPlayer()->getName(),'build') && !$event->getPlayer()->isOp())
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
	
	/**
	 * @param BlockUpdateEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onBlockUpdate(BlockUpdateEvent $event)
	{
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res=$event->getBlock()->getId()>=8 && $event->getBlock()->getId()<=11 && !$res->getPermission('flow',true))
		{
			$event->setCancelled();
		}
		unset($event,$res);
	}
	
	/**
	 * @param EntityDamageEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onEntityDamage(EntityDamageEvent $event)
	{
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getEntity())))!==false && !$res->getPermission('damage'))
		{
			$event->setCancelled();
		}
		else if($event instanceof EntityDamageByEntityEvent && $event->getDamager() instanceof Player && $event->getEntity() instanceof Player && $res!==false && strtolower($event->getDamager()->getName())!=$res->getOwner() && !$event->getDamager()->isOp() && !($res->getPlayerPermission($event->getDamager(),'pvp',true) && $res->getPlayerPermission($event->getEntity(),'pvp',true)))
		{
			$event->setCancelled();
			$msg=$res->getMessage('permission');
			$event->getDamager()->sendMessage($msg);
		}
		unset($res,$event,$msg);
	}
	
	/**
	 * @param PlayerJoinEvent $event
	 *
	 * @priority NORMAL
	 */
	public function onPlayerJoin(PlayerJoinEvent $event)
	{
		$this->select[$event->getPlayer()->getName()]=new PlayerInfo($event->getPlayer());
		unset($event);
	}
	
	/**
	 * @param PlayerQuitEvent $event
	 *
	 * @priority NORMAL
	 */
	public function onPlayerQuit(PlayerQuitEvent $event)
	{
		if(isset($this->select[$event->getPlayer()->getName()]) && !$this->select[$event->getPlayer()->getName()]->player->isConnected())
		{
			unset($this->select[$event->getPlayer()->getName()]);
		}
		unset($event);
	}
	
	public function isProtectBlock(Block $block)
	{
		switch($block->getId())
		{
		case Item::BED_BLOCK:
		case Item::CHEST:
		case Item::CRAFTING_TABLE:
		case Item::DOOR_BLOCK:
		case Item::IRON_DOOR_BLOCK:
		case Item::TRAPDOOR:
		case Item::FENCE_GATE:
		case Item::STONECUTTER:
		case Item::NETHER_REACTOR:
		case 126:
			unset($block);
			return true;
		}
		unset($block);
		return false;
	}
	
	public function isBlockedItem(Item $item)
	{
		switch($item->getId())
		{
		case Item::FLINT_STEEL:
		case Item::BOW:
		case Item::SEEDS:
		case Item::BUCKET:
		case Item::MINECART:
		case Item::REDSTONE:
		case Item::DYE:
		case Item::PUMPKIN_SEEDS:
		case Item::MELON_SEEDS:
		case Item::CARROT:
		case Item::POTATO:
		case Item::BEETROOT_SEEDS:
			unset($item);
			return true;
		}
		unset($item);
		return false;
	}
	
	//领地判断算法移植自PC的Residence，若侵犯原作者权益请联系我,Gmail:FENGberd@gmail.com
	public function check($pos1,$pos2,$pos3,$pos4)
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
		if($pos3 instanceof Vector3)
		{
			$x3=$pos3->getX();
			$y3=$pos3->getY();
			$z3=$pos3->getZ();
		}
		else
		{
			$x3=$pos3['x'];
			$y3=$pos3['y'];
			$z3=$pos3['z'];
		}
		if($pos4 instanceof Vector3)
		{
			$x4=$pos4->getX();
			$y4=$pos4->getY();
			$z4=$pos4->getZ();
		}
		else
		{
			$x4=$pos4['x'];
			$y4=$pos4['y'];
			$z4=$pos4['z'];
		}
		$A1LX=min($x1,$x2);
		$A1LY=min($y1,$y2);
		$A1LZ=min($z1,$z2);
		$A1HX=max($x1,$x2);
		$A1HY=max($y1,$y2);
		$A1HZ=max($z1,$z2);
		
		$A2LX=min($x3,$x4);
		$A2LY=min($y3,$y4);
		$A2LZ=min($z3,$z4);
		$A2HX=max($x3,$x4);
		$A2HY=max($y3,$y4);
		$A2HZ=max($z3,$z4);
		
		if((($A1HX >= $A2LX) && ($A1HX <= $A2HX)) || (($A1LX >= $A2LX) && ($A1LX <= $A2HX)) || (($A2HX >= $A1LX) && ($A2HX <= $A1HX)) || (($A2LX >= $A1LX) && ($A2LX <= $A1HX) && 
			((($A1HY >= $A2LY) && ($A1HY <= $A2HY)) || (($A1LY >= $A2LY) && ($A1LY <= $A2HY)) || (($A2HY >= $A1LY) && ($A2HY <= $A1HY)) || (($A2LY >= $A1LY) && ($A2LY <= $A1HY) && 
			((($A1HZ >= $A2LZ) && ($A1HZ <= $A2HZ)) || (($A1LZ >= $A2LZ) && ($A1LZ <= $A2HZ)) || (($A2HZ >= $A1LZ) && ($A2HZ <= $A1HZ)) || (($A2LZ >= $A1LZ) && ($A2LZ <= $A1HZ)))))))
		{
			return true;
		}
		return false;
	}
}
?>
