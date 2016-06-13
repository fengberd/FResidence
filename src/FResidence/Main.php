<?php
namespace FResidence;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;

use FResidence\provider\YAMLProvider;

use FResidence\event\ResidenceAddEvent;
use FResidence\event\ResidenceRemoveEvent;

class Main extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener
{
	private static $obj;
	public $select=array();
	private $perms=array('move',
		'build',
		'use',
		'pvp',
		'damage',
		'tp',
		'flow',
		'healing');
	public $provider=null;
	public $whiteListWorld=array();
	
	public function getProvider()
	{
		return $this->provider;
	}
	
	public static function getInstance()
	{
		return self::$obj;
	}
	
	public function loadConfig()
	{
		@mkdir($this->getDataFolder());
		$this->config=new Config($this->getDataFolder().'config.yml',Config::YAML,array());
		if($this->config->exists('landItem'))
		{
			$this->selectItem=intval($this->config->get('landItem',Item::WOODEN_HOE));
			$this->moneyPerBlock=$this->config->get('blockMoney',0.05)*1;
			$this->moneyName=$this->config->get('moneyName','元');
			$this->checkMoveTick=intval($this->config->get('checkMoveTick',10));
			$this->playerMaxCount=intval($this->config->get('playerMaxCount',3));
			$this->config->set('WhiteListWorld',$this->config->get('whiteListWorld',array()));
		}
		else
		{
			$this->selectItem=intval($this->config->get('SelectItem',Item::WOODEN_HOE));
			$this->moneyPerBlock=$this->config->get('MoneyPerBlock',0.05)*1;
			$this->moneyName=$this->config->get('MoneyName','元');
			$this->checkMoveTick=intval($this->config->get('CheckMoveTick',10));
			$this->playerMaxCount=intval($this->config->get('MaxResidenceCount',3));
		}
		foreach((array)$this->config->get('WhiteListWorld',array()) as $world)
		{
			$this->whiteListWorld[]=strtolower($world);
			unset($world);
		}
		$this->config->setAll(array(
			'Provider'=>'yaml',
			'MoneyName'=>$this->moneyName,
			'SelectItem'=>$this->selectItem,
			'CheckMoveTick'=>$this->checkMoveTick,
			'MoneyPerBlock'=>$this->moneyPerBlock,
			'MaxResidenceCount'=>$this->playerMaxCount,
			'WhiteListWorld'=>$this->whiteListWorld));
		$this->config->save();
	}
	
	public function onLoad()
	{
		ZXDA::init(40,$this);
		ZXDA::requestCheck();
	}
	
	public function onEnable()
	{
		ZXDA::tokenCheck('MTMxODQwODkxOTAwNjQyMzcyNzY1Njg3ODM0NTU5NTQxMzI1OTkzMjAyMTkwNTQwNTYwMzkxNTE1MjA1NjA5OTcxNDc5NjMxNzIxMjMwOTAwOTYwNTc2MTQ1MzI0MTUwMTQ4MjgyMDI4NzAwNDQ0MDQ4OTE1MDUxNjg1MjYwNzc3MDM5Nzg3NDQ2ODU4NjQ0NjA5MTU5NjY2NjA2NTA4NzEyNTUyMTI5ODE0NDk1NzYwOTcxNjcxODQ2MDYyNjYzNDc4MDg1OTg3NDEyMzk3NTIzMzE2NjgyMTk3NzEyMjk2NTk2ODY0Nw==');
		$data=ZXDA::getInfo();
		if($data['success'])
		{
			if(version_compare($data['version'],$this->getDescription()->getVersion())>0)
			{
				$this->getLogger()->info(TextFormat::GREEN.'检测到新版本,最新版:'.$data['version'].",更新日志:\n    ".str_replace("\n","\n    ",$data['update_info']));
			}
		}
		else
		{
			$this->getLogger()->warning('更新检查失败:'.$data['message']);
		}
		if(ZXDA::isTrialVersion())
		{
			$this->getLogger()->warning('当前正在使用试用版授权,试用时间到后将强制关闭服务器');
		}
		if(!defined('EOL'))
		{
			define('EOL',"\n");
		}
		if(!self::$obj instanceof Main)
		{
			self::$obj=$this;
		}
		$this->loadConfig();
		switch(strtolower($this->config->get('Provider','yaml')))
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
		Item::addCreativeItem(Item::get($this->selectItem,0));
		
		$this->systemTask=new SystemTask($this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask($this->systemTask,20);
		
		$reflection=new \ReflectionClass(\get_class($this));
		foreach($reflection->getMethods() as $method)
		{
			if(!$method->isStatic())
			{
				$priority=0;
				$parameters=$method->getParameters();
				if(\count($parameters)===1 and $parameters[0]->getClass() instanceof \ReflectionClass and \is_subclass_of($parameters[0]->getClass()->getName(),\pocketmine\event\Event::class))
				{
					$class=$parameters[0]->getClass()->getName();
					$reflection=new \ReflectionClass($class);
					$this->getServer()->getPluginManager()->registerEvent($class,$this,$priority,new \pocketmine\plugin\MethodEventExecutor($method->getName()),$this,false);
				}
			}
			unset($method);
		}
	}
	
	public function onCommand(\pocketmine\command\CommandSender $sender,\pocketmine\command\Command $command,$label,array $args)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(!isset($args[0]))
		{
			$sender->sendMessage('[FResidence] '.TextFormat::RED.'请使用 /res help 查看帮助');
			unset($sender,$command,$label,$args);
			return true;
		}
		switch($args[0])
		{
		case 'reload':
			if(!$sender->isOp())
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'权限不足');
				unset($sender,$command,$label,$args);
				return false;
			}
			$this->loadConfig();
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'重载完成');
			break;
		case 'parse':
			if($sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限使用这个指令');
				break;
			}
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'开始转换领地数据');
			$land=new Config($this->getDataFolder().'../EconomyLand/Land.yml',Config::YAML,array());
			$cou=0;
			foreach($land->getAll() as $l)
			{
				$this->provider->addResidence(new Vector3($l['startX'],0,$l['startZ']),new Vector3($l['endX'],128,$l['endZ']),$l['owner'],'parse_'.$l['ID'],$l['level']);
				$cou++;
				unset($l);
			}
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'数据转换完成,共处理 '.$cou.' 块领地');
			break;
		case 'create':
			if(!$sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'请在游戏中执行这个指令');
				break;
			}
			if(!isset($this->select[$sender->getName()]) || !$this->select[$sender->getName()]->isSelectFinish())
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'请先使用选点工具来选择两个点再进行圈地');
				break;
			}
			if(!isset($args[1]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res create <名称>');
				break;
			}
			if(strlen($args[1])<=0 || strlen($args[1])>=60)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'无效领地名称');
				break;
			}
			if(!$sender->isOp() && count($this->provider->queryResidencesByOwner($sender->getName()))>=$this->playerMaxCount)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你拥有的的领地数量已经达到了上限 '.$this->playerMaxCount.' 块');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			if($rid!==false && $this->provider->getResidence($rid)!==false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'已存在重名领地');
				break;
			}
			$select1=$this->select[$sender->getName()]->getP1();
			$select2=$this->select[$sender->getName()]->getP2();
			if($select1->getLevel()->getFolderName()!=$select2->getLevel()->getFolderName())
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'请在同一个世界选点圈地');
				break;
			}
			$money=$this->moneyPerBlock*Utils::calcBoxSize($select1,$select2);
			if(!$sender->isOp() && $money>IncludeAPI::Economy_getMoney($sender))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有足够的'.$this->moneyName.'来圈地 ,需要 '.$money.' '.$this->moneyName);
				break;
			}
			$break=false;
			foreach($this->provider->getAllResidences() as $r)
			{
				if($r->getLevel()==$select1->getLevel()->getFolderName() && $this->check($r->getStart(),$r->getEnd(),$select1,$select2))
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'选区与领地 '.$r->getName().' 重叠 ,不能覆盖 !');
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
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'领地创建成功 ,花费 '.$money.' '.$this->moneyName);
			break;
		case 'remove':
			if(!isset($args[1]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res remove <名称>');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'权限不足');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			$res=$this->provider->getResidence($rid);
			if($rid===false || $res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地不存在');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限移除这块领地');
				break;
			}
			$this->getServer()->getPluginManager()->callEvent($ev=new ResidenceRemoveEvent($this,$res));
			if($ev->isCancelled())
			{
				break;
			}
			$this->provider->removeResidence($rid);
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'领地移除成功');
			break;
		case 'give':
			if(!isset($args[2]) || $args[2]=='')
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res give <领地> <玩家>');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'权限不足');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[1]);
			$res=$this->provider->getResidence($rid);
			if($rid===false || $res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地不存在');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限赠送这块领地');
				break;
			}
			$res->setOwner($args[2]);
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'成功把领地 '.$args[1].' 赠送给玩家 '.$args[2]);
			break;
		case 'removeall':
			if(!$sender instanceof Player)
			{
				if(!isset($args[1]))
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'请在游戏中执行这个指令或指定要移除的玩家');
				}
				else
				{
					$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'成功移除玩家 '.$args[1].' 的所有领地 (操作 '.$this->provider->removeResidencesByOwner($args[1]).' 块领地)');
				}
				break;
			}
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'成功移除你的所有领地 (操作 '.$this->provider->removeResidencesByOwner($sender->getName()).' 块领地)');
			break;
		case 'info':
			if(isset($args[1]))
			{
				if(($res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1])))===false)
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'不存在该名字的领地');
				}
				else
				{
					$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'领地信息查询结果:'.EOL.
						'    领地名称 : '.TextFormat::YELLOW.$res->getName().EOL.
						'    领地主人 : '.TextFormat::YELLOW.$res->getOwner().EOL.
						'    领地大小 : '.TextFormat::YELLOW.$res->getSize().' 方块'.EOL.
						'    所在世界 : '.TextFormat::YELLOW.$res->getLevel());
				}
				break;
			}
			//这里不能break
		case 'current':
			if(!$sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'请在游戏中执行这个指令或指定要查询的领地');
				break;
			}
			if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($sender)))===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'当前位置没有领地');
			}
			else
			{
				$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'领地信息查询结果:'.EOL.
					'    领地名称 : '.TextFormat::YELLOW.$res->getName().EOL.
					'    领地主人 : '.TextFormat::YELLOW.$res->getOwner().EOL.
					'    领地大小 : '.TextFormat::YELLOW.$res->getSize().' 方块'.EOL.
					'    所在世界 : '.TextFormat::YELLOW.$res->getLevel());
			}
			break;
		case 'list':
			if($sender->isOp())
			{
				if(!isset($args[2]))
				{
					if(!$sender instanceof Player)
					{
						$sender->sendMessage('[FResidence] '.TextFormat::RED.'请在游戏中执行这个指令或指定要查询的玩家');
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
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'该玩家没有任何一块领地');
				break;
			}
			if(($page-1)*5>count($arr))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'页码超出范围');
				break;
			}
			$all=(int)(count($arr)/5);
			if($all<=0)
			{
				$all=0;
			}
			$all++;
			$help=TextFormat::GREEN.'====Residence List ['.$page.'/'.$all.']===='.EOL;
			$page--;
			foreach($arr as $key=>$res)
			{
				if($page*5<=$key && ($page+1)*5>$key)
				{
					$help.=TextFormat::YELLOW.$res->getName().' - 大小 '.$res->getSize().' 方块 ,所在世界 : '.$res->getLevel().EOL;
				}
				unset($res,$key);
			}
			$sender->sendMessage($help);
			break;
		case 'listall':
			if(!$sender->isOp())
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限使用这个指令');
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
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'服务器里还没创建过任何领地');
				break;
			}
			if(($page-1)*5>count($arr))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'页码超出范围');
				break;
			}
			$all=(int)(count($arr)/5);
			if($all<=0)
			{
				$all=0;
			}
			$all++;
			$help=TextFormat::GREEN.'====All Residences ['.$page.'/'.$all.']===='.EOL;
			$page--;
			foreach($arr as $key=>$res)
			{
				if($page*5<=$key && ($page+1)*5>$key)
				{
					$help.=TextFormat::YELLOW.$res->getName().' - 大小 '.$res->getSize().' 方块 ,所在世界 : '.$res->getLevel().EOL;
				}
				unset($res,$key);
			}
			$sender->sendMessage($help);
			break;
		case 'message':
			if(!isset($args[3]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res message <领地> <索引> <信息> 消息索引如下'.EOL.
					'    enter - 进入消息'.EOL.
					'    leave - 离开消息'.EOL.
					'    permission - 权限提示消息');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'权限不足');
				break;
			}
			$args[2]=strtolower($args[2]);
			if($args[2]!='enter' && $args[2]!='leave' && $args[2]!='permission')
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'错误的消息索引 ,只能为以下值的任意一个 :'.EOL.
					'    enter - 进入消息'.EOL.
					'    leave - 离开消息'.EOL.
					'    permission - 提示没有权限的消息');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地不存在');
				break;
			}
			if(!$sender->isOp() && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限修改这块领地');
				break;
			}
			$res->setMessage($args[2],implode(' ',array_slice($args,3)));
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'成功设置领地 '.$args[1].' 的 '.$args[2].' 消息数据');
			break;
		case 'default':
			if(!isset($args[1]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法 :/res default <领地>');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'权限不足');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地不存在');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限修改这块领地');
				break;
			}
			$res->resetPermission();
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'成功重置领地 '.$args[1].' 的权限数据');
			break;
		case 'set':
			if(!isset($args[3]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res set <领地> <权限索引> <true/false> ,权限索引见下表'.EOL.
					'    move - 玩家移动权限'.EOL.
					'    build - 破坏/放置权限'.EOL.
					'    use - 使用工作台/箱子等权限'.EOL.
					'    pvp - PVP权限'.EOL.
					'    damage - 是否能受到伤害'.EOL.
					'    healing - 是否自动回血'.EOL.
					'    tp - 传送到此领地的权限'.EOL.
					'    flow - 液体流动权限');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'权限不足');
				break;
			}
			$args[2]=strtolower($args[2]);
			if(!in_array($args[2],$this->perms))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'错误的权限索引 ,只能为以下值的任意一个 :'.EOL.
					'    move - 玩家移动权限'.EOL.
					'    build - 破坏/放置权限'.EOL.
					'    use - 使用工作台/箱子等权限'.EOL.
					'    pvp - PVP权限'.EOL.
					'    damage - 是否能受到伤害'.EOL.
					'    healing - 是否自动回血'.EOL.
					'    tp - 传送到此领地的权限'.EOL.
					'    flow - 液体流动权限');
				break;
			}
			$args[3]=strtolower($args[3]);
			if($args[3]!='true' && $args[3]!='false')
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'错误的权限值 ,只能为以下值的任意一个 :'.EOL.
					'    true - 开放此权限'.EOL.
					'    false - 阻止此权限');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地不存在');
				break;
			}
			if(!$sender->isOp () && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限修改这块领地');
				break;
			}
			$res->setPermission($args[2],$args[3]);
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'成功设置领地 '.$args[1].' 的权限 '.$args[2]);
			break;
		case 'pset':
			if(!isset($args[4]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res pset <领地> <玩家> <权限索引> <true/false> 权限索引见下表'.EOL.
					'    move - 玩家移动权限'.EOL.
					'    build - 破坏/放置权限'.EOL.
					'    use - 使用工作台/箱子等权限'.EOL.
					'    pvp - PVP权限'.EOL.
					'    tp - 传送到此领地的权限');
				break;
			}
			if($args[1]=='spawn' && $sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'权限不足');
				break;
			}
			$args[2]=strtolower($args[2]);
			if($args[2]=='' || preg_match('#^[a-zA-Z0-9_]{3,16}$#', $args[2])==0)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'无效的用户名');
				break;
			}
			$args[3]=strtolower($args[3]);
			if($args[3]!='move' && $args[3]!='build' && $args[3]!='use' && $args[3]!='pvp' && $args[3]!='tp')
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'错误的权限索引 ,只能为以下值的任意一个 :'.EOL.
					'    move - 玩家移动权限'.EOL.
					'    build - 破坏/放置权限'.EOL.
					'    use - 使用工作台/箱子等权限'.EOL.
					'    pvp - PVP权限'.EOL.
					'    tp - 传送到此领地的权限');
				break;
			}
			$args[4]=strtolower($args[4]);
			if($args[4]!='true' && $args[4]!='false' && $args[4]!='remove')
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'错误的权限值 ,只能为以下值的任意一个 :'.EOL.TextFormat::RED.'true - 开放此权限'.EOL.TextFormat::RED.'false - 只有你自己能使用这个权限');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地不存在');
				break;
			}
			if(!$sender->isOp() && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限修改这块领地');
				break;
			}
			$res->setPlayerPermission($args[2],$args[3],$args[4]);
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'成功设置玩家 '.$args[2].' 的领地权限 '.$args[3]);
			break;
		case 'tp':
			if(!isset($args[1]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res tp <领地>');
				break;
			}
			if(!$sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'请在游戏中执行这个指令');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地不存在');
				break;
			}
			if(!$sender->isOp() && $res->getOwner()!==strtolower($sender->getName()) && !$res->getPlayerPermission($sender->getName(),'tp'))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限传送到这块领地');
				break;
			}
			if(($pos=$res->getTeleportPos())===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'传送失败,目标领地所在世界未加载');
				break;
			}
			$sender->teleport($pos);
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'传送到领地 '.$args[1]);
			break;
		case 'tpset':
			if(!$sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'请在游戏中执行这个指令');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByPosition($sender));
			if($res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'这个位置没有领地');
				break;
			}
			if(!$sender->isOp() && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限修改该领地的传送点');
				break;
			}
			$res->setTeleportPos($sender);
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'领地 '.$res->getName().' 的传送点修改成功');
			break;
		case 'wl':
		case 'whitelist':
			if(!$sender->isOp())
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限进行此操作');
				break;
			}
			if(!isset($args[1]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res whitelist [add <世界名>|remove <世界名>|list|clear]');
				break;
			}
			switch($args[1])
			{
			case 'add':
				if(!isset($args[2]))
				{
					$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res whitelist add <世界名>');
					break;
				}
				$args[2]=strtolower($args[2]);
				if(in_array($args[2],$this->whiteListWorld))
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'该世界已在白名单列表中');
					break;
				}
				$this->whiteListWorld[]=$args[2];
				$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'白名单世界添加成功');
				break;
			case 'remove':
				if(!isset($args[2]))
				{
					$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res whitelist remove <世界名>');
					break;
				}
				$args[2]=strtolower($args[2]);
				if(!in_array($args[2],$this->whiteListWorld))
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'该世界不在白名单列表中');
					break;
				}
				$data=$this->whiteListWorld;
				$this->whiteListWorld=array();
				foreach($data as $d)
				{
					if($d!=$args[2])
					{
						$this->whiteListWorld[]=$d;
					}
					unset($d);
				}
				unset($data);
				$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'白名单世界移除成功');
				break;
			case 'list':
				$data=TextFormat::GREEN.'====='.TextFormat::YELLOW.'WhiteList Worlds'.TextFormat::GREEN.'=====';
				foreach($this->whiteListWorld as $world)
				{
					$data.=EOL.' - '.$world;
					unset($world);
				}
				$sender->sendMessage($data);
				unset($data);
				break;
			case 'clear':
				$this->whiteListWorld=array();
				$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'白名单世界清空成功');
				break;
			default:
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res whitelist [add <世界名>|remove <世界名>|list|clear]');
				break;
			}
			$this->config->set('whiteListWorld',$this->whiteListWorld);
			$this->config->save();
			break;
		case 'mirror':
			if(!isset($args[2]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res mirror <源领地> <目标领地>');
				break;
			}
			$srcRes=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			$dstRes=$this->provider->getResidence($this->provider->queryResidenceByName($args[2]));
			if($srcRes===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'源领地不存在');
				break;
			}
			if($dstRes===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'目标领地不存在');
				break;
			}
			if(!$sender->isOp())
			{
				if($srcRes->getOwner()!==strtolower($sender->getName()))
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限修改源领地');
					break;
				}
				
				if($dstRes->getOwner()!==strtolower($sender->getName()))
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限修改目标领地');
					break;
				}
			}
			$dstRes->setAllPermission($srcRes->getAllPermission());
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'领地权限数据复制成功');
			break;
		case 'rename':
			if(!isset($args[2]))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res rename <领地> <名称>');
				break;
			}
			if(strlen($args[2])<=0 || strlen($args[2])>=60)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地名称无效');
				break;
			}
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($args[1]));
			if($res===false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'领地不存在');
				break;
			}
			if(!$sender->isOp() && $res->getOwner()!==strtolower($sender->getName()))
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'你没有权限修改这块领地');
				break;
			}
			$rid=$this->provider->queryResidenceByName($args[2]);
			if($rid!==false && $this->provider->getResidence($rid)!==false)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'已存在重名领地');
				break;
			}
			$res->setName($args[2]);
			$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'领地重命名成功');
			break;
		case 'select':
			if(!$sender instanceof Player)
			{
				$sender->sendMessage('[FResidence] '.TextFormat::RED.'请在游戏中执行这个指令');
				break;
			}
			switch(isset($args[1])?$args[1]:'')
			{
			case 'size':
				if(!isset($this->select[$sender->getName()]) || !$this->select[$sender->getName()]->isSelectFinish())
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'请先选择两个点再进行此操作');
					break;
				}
				$p1=$this->select[$sender->getName()]->getP1();
				$p2=$this->select[$sender->getName()]->getP2();
				$size=Utils::calcBoxSize($p1,$p2);
				$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'当前选区信息:'.EOL.
					'    选区大小: '.TextFormat::YELLOW.$size.' 方块'.EOL.
					'    选区价格: '.TextFormat::YELLOW.($this->moneyPerBlock*$size).' '.$this->moneyName.EOL.
					'    选区坐标: '.TextFormat::YELLOW.'('.$p1->getX().','.$p1->getY().','.$p1->getZ().')->('.$p2->getX().','.$p2->getY().','.$p2->getZ().')');
				unset($p1,$p2);
				break;
			case 'chunk':
				$this->select[$sender->getName()]->setP1(new Position(($sender->getX()>>4)*16,0,($sender->getZ()>>4)*16,$sender->getLevel()));
				$this->select[$sender->getName()]->setP2(new Position(($sender->getX()>>4)*16+16,128,($sender->getZ()>>4)*16+16,$sender->getLevel()));
				$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'已选中当前所在区块 ,使用 /res select size 查看选区价格');
				break;
			case 'vert':
				if(!isset($this->select[$sender->getName()]) || !$this->select[$sender->getName()]->isSelectFinish())
				{
					$sender->sendMessage('[FResidence] '.TextFormat::RED.'请先选择两个点再进行此操作');
					break;
				}
				$this->select[$sender->getName()]->p1->y=0;
				$this->select[$sender->getName()]->p2->y=128;
				$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'已将选区Y坐标扩展到0-128格 ,使用 /res select size 查看选区价格');
				break;
			default:
				if(isset($args[3]))
				{
					$p1=$sender->getPosition();
					$p1->x=intval($p1->getX()+$args[1]);
					$p1->y=min(max(intval($p1->getY()+$args[2]),0),128);
					$p1->z=intval($p1->getZ()+$args[3]);
					$p2=$sender->getPosition();
					$p2->x=intval($p2->getX()+$args[1]);
					$p2->y=min(max(intval($p2->getY()+$args[2]),0),128);
					$p2->z=intval($p2->getZ()+$args[3]);
					$this->select[$sender->getName()]->setP1($p1);
					$this->select[$sender->getName()]->setP2($p2);
					unset($p1,$p2);
					$sender->sendMessage('[FResidence] '.TextFormat::GREEN.'已选中以当前坐标为中心的指定范围 ,使用 /res select size 查看选区价格');
				}
				else
				{
					$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res select <size|chunk|vert> 或 /res select <x> <y> <z>');
				}
				break;
			}
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
				$help.='/res create <名称> - 创建一个领地'.EOL;
				$help.='/res remove <名称> - 移除指定名称的领地'.EOL;
				$help.=TextFormat::RED.'/res removeall '.($sender instanceof Player?'':'<玩家ID> ').'- 移除'.($sender instanceof Player?'你':'某玩家').'的所有领地'.EOL;
				$help.='/res message <领地> <索引> <内容> - 设置领地的消息内容'.EOL;
				$help.='/res set <领地> <权限> <true/false> - 设置领地权限'.EOL;
				break;
			case 2:
				$help.='/res pset <领地> <玩家> <权限> <true/false> - 设置某玩家的领地权限'.EOL;
				$help.='/res give <领地> <玩家> - 把领地赠送给某玩家'.EOL;
				$help.='/res tp <领地> - 传送到某领地'.EOL;
				$help.='/res tpset - 设置当前坐标为当前领地传送点'.EOL;
				$help.='/res help - 查看帮助'.EOL;
				break;
			case 3:
				$help.='/res info <领地> - 查询指定领地信息'.EOL;
				$help.='/res current - 查询当前所在领地信息'.EOL;
				$help.='/res whitelist [add <世界名>|remove <世界名>|list|clear] - 操作白名单世界'.EOL;
				$help.='/res mirror <源领地> <目标领地> - 将源领地的权限数据复制到目标领地'.EOL;
				$help.='/res rename <领地> <名字> - 重命名领地'.EOL;
				$help.='/res select <size|chunk|vert> - 查看选区大小/选取整个区块/扩展选区Y坐标到0-128'.EOL;
				$help.='/res select <x> <y> <z> - 选择以当前坐标为起点 ,指定大小的选区'.EOL;
				break;
			}
			$help='=====FResidence commands ['.$page.'/3]====='.EOL.$help;
			$sender->sendMessage($help);
			break;
		default:
			$sender->sendMessage('[FResidence] '.TextFormat::RED.'请使用 /res help 查看帮助');
			break;
		}
		unset($sender,$command,$label,$help,$rid,$res,$resarr,$break,$select1,$select2,$level,$args);
		return true;
	}
	
	public function systemTaskCallback($currentTick)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		foreach($this->select as $player)
		{
			if($player->currentResidence!==false && $player->currentResidence->getPermission('healing'))
			{
				$player=$player->player;
				$ev=new EntityRegainHealthEvent($player,1,EntityRegainHealthEvent::CAUSE_CUSTOM);
				$player->heal($ev->getAmount(),$ev);
				unset($ev);
			}
			unset($player);
		}
		unset($currentTick);
	}
	
	/**
	 * @priority MONITOR
	 */
	public function onPlayerInteract(PlayerInteractEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if($event->getAction()==PlayerInteractEvent::RIGHT_CLICK_BLOCK)
		{
			if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$event->getPlayer()->isOp() && ($this->isProtectBlock($event->getBlock()) || $this->isBlockedItem($event->getItem())) && !$res->getPlayerPermission($event->getPlayer()->getName(),'use'))
			{
				$msg=$res->getMessage('permission');
				$event->getPlayer()->sendMessage($msg);
				$event->setCancelled();
			}
			else if($event->getItem()->getId()==$this->selectItem)
			{
				$this->select[$event->getPlayer()->getName()]->setP1($event->getBlock());
				$event->getPlayer()->sendMessage('[FResidence] 已设置第一个点');
				if(($select2=$this->select[$event->getPlayer()->getName()]->getP2())!==false)
				{
					$select1=$event->getBlock();
					if($select1->getLevel()->getFolderName()!=$select2->getLevel()->getFolderName())
					{
						$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::RED.'请在同一个世界选点圈地');
					}
					$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::YELLOW.'选区已设定,需要 '.($this->moneyPerBlock*Utils::calcBoxSize($select1,$select2)).' '.$this->moneyName.'来创建领地');
				}
				$event->setCancelled();
			}
			else if($res===false && in_array(strtolower($event->getBlock()->getLevel()->getFolderName()),$this->whiteListWorld) && !$event->getPlayer()->isOp())
			{
				$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::YELLOW.'抱歉,当前世界需要先圈地才能进行建筑');
				$event->setCancelled();
			}
		}
		unset($event,$res,$msg,$select1,$select2);
	}
	
	/**
	 * @priority MONITOR
	 */
	public function onPlayerMove(\pocketmine\event\player\PlayerMoveEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		$name=$event->getPlayer()->getName();
		$this->select[$name]->checkMoveTick--;
		$this->select[$name]->move[]=$event->getFrom();
		if(count($this->select[$name]->move)>$this->select[$name]->checkMoveTick)
		{
			array_shift($this->select[$name]->move);
		}
		if($this->select[$name]->checkMoveTick>0)
		{
			unset($name,$event);
			return;
		}
		$this->select[$name]->checkMoveTick=$this->checkMoveTick;
		$res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getTo()));
		if($res!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$event->getPlayer()->isOp() && !$res->getPlayerPermission($event->getPlayer()->getName(),'move'))
		{
			$event->getPlayer()->teleport($this->select[$name]->move[0]);
			$event->getPlayer()->sendPopup($res->getMessage('permission'));
		}
		else if($res===false && $this->select[$name]->currentResidence!==false)
		{
			$msg=$this->select[$name]->currentResidence->getMessage('leave');
			$msg=str_replace('%name',$this->select[$name]->currentResidence->getName(),$msg);
			$msg=str_replace('%owner',$this->select[$name]->currentResidence->getOwner(),$msg);
			$event->getPlayer()->sendMessage($msg);
			$this->select[$name]->currentResidence=false;
		}
		else if($res!==false && ($this->select[$name]->currentResidence===false || $this->select[$name]->currentResidence->getID()!==$res->getID()))
		{
			$this->select[$name]->currentResidence=$res;
			$msg=$res->getMessage('enter');
			$msg=str_replace('%name',$res->getName(),$msg);
			$msg=str_replace('%owner',$res->getOwner(),$msg);
			$event->getPlayer()->sendMessage($msg);
		}
		unset($event,$res,$msg);
	}
	
	/**
	 * @priority MONITOR
	 */
	public function onBlockPlace(\pocketmine\event\block\BlockPlaceEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$res->getPlayerPermission($event->getPlayer()->getName(),'build') && !$event->getPlayer()->isOp())
		{
			$event->getPlayer()->sendMessage($res->getMessage('permission'));
			$event->setCancelled();
		}
		else if($res===false && in_array(strtolower($event->getBlock()->getLevel()->getFolderName()),$this->whiteListWorld) && !$event->getPlayer()->isOp())
		{
			$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::YELLOW.'抱歉,当前世界需要先圈地才能进行建筑');
			$event->setCancelled();
		}
		unset($event,$res);
	}
	
	/**
	 * @priority MONITOR
	 */
	public function onBlockBreak(\pocketmine\event\block\BlockBreakEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$res->getPlayerPermission($event->getPlayer()->getName(),'build') && !$event->getPlayer()->isOp())
		{
			$event->getPlayer()->sendMessage($res->getMessage('permission'));
			$event->setCancelled();
		}
		else if($event->getItem()->getId()==$this->selectItem)
		{
			$this->select[$event->getPlayer()->getName()]->setP2($event->getBlock());
			$event->getPlayer()->sendMessage('[FResidence] 已设置第二个点');
			if(($select1=$this->select[$event->getPlayer()->getName()]->getP1())!==false)
			{
				$select2=$event->getBlock();
				if($select1->getLevel()->getFolderName()!=$select2->getLevel()->getFolderName())
				{
					$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::RED.'请在同一个世界选点圈地');
				}
				$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::YELLOW.'选区已设定,需要 '.($this->moneyPerBlock*Utils::calcBoxSize($select1,$select2)).' '.$this->moneyName.'来创建领地');
			}
			$event->setCancelled();
		}
		else if($res===false && in_array(strtolower($event->getBlock()->getLevel()->getFolderName()),$this->whiteListWorld) && !$event->getPlayer()->isOp())
		{
			$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::YELLOW.'抱歉,当前世界需要先圈地才能进行建筑');
			$event->setCancelled();
		}
		unset($event,$res,$select1,$select2);
	}
	
	/**
	 * @priority MONITOR
	 */
	public function onBlockUpdate(\pocketmine\event\block\BlockUpdateEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res=$event->getBlock()->getId()>=8 && $event->getBlock()->getId()<=11 && !$res->getPermission('flow',true))
		{
			$event->setCancelled();
		}
		unset($event,$res);
	}
	
	/**
	 * @priority MONITOR
	 */
	public function onEntityDamage(\pocketmine\event\entity\EntityDamageEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getEntity())))!==false && !$res->getPermission('damage'))
		{
			$event->setCancelled();
		}
		else if($event instanceof \pocketmine\event\entity\EntityDamageByEntityEvent && $event->getDamager() instanceof Player && $event->getEntity() instanceof Player && $res!==false && strtolower($event->getDamager()->getName())!=$res->getOwner() && !$event->getDamager()->isOp() && !($res->getPlayerPermission($event->getDamager(),'pvp',true) && $res->getPlayerPermission($event->getEntity(),'pvp',true)))
		{
			$event->setCancelled();
			$msg=$res->getMessage('permission');
			$event->getDamager()->sendMessage($msg);
		}
		unset($res,$event,$msg);
	}
	
	/**
	 * @priority NORMAL
	 */
	public function onPlayerJoin(\pocketmine\event\player\PlayerJoinEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		$this->select[$event->getPlayer()->getName()]=new PlayerInfo($event->getPlayer());
		unset($event);
	}
	
	/**
	 * @priority NORMAL
	 */
	public function onPlayerQuit(\pocketmine\event\player\PlayerQuitEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(isset($this->select[$event->getPlayer()->getName()]) && !$this->select[$event->getPlayer()->getName()]->player->isConnected())
		{
			unset($this->select[$event->getPlayer()->getName()]);
		}
		unset($event);
	}
	
	public function isProtectBlock(Block $block)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
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
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
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
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
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
		if(((($A1HX >= $A2LX) && ($A1HX <= $A2HX)) || (($A1LX >= $A2LX) && ($A1LX <= $A2HX)) || (($A2HX >= $A1LX) && ($A2HX <= $A1HX)) || (($A2LX >= $A1LX) && ($A2LX <= $A1HX))) &&
			((($A1HY >= $A2LY) && ($A1HY <= $A2HY)) || (($A1LY >= $A2LY) && ($A1LY <= $A2HY)) || (($A2HY >= $A1LY) && ($A2HY <= $A1HY)) || (($A2LY >= $A1LY) && ($A2LY <= $A1HY))) &&
			((($A1HZ >= $A2LZ) && ($A1HZ <= $A2HZ)) || (($A1LZ >= $A2LZ) && ($A1LZ <= $A2HZ)) || (($A2HZ >= $A1LZ) && ($A2HZ <= $A1HZ)) || (($A2LZ >= $A1LZ) && ($A2LZ <= $A1HZ))))
		{
			return true;
		}
		return false;
	}
}

class ZXDA
{
	private static $_PID=false;
	private static $_TOKEN=false;
	private static $_PLUGIN=null;
	private static $_VERIFIED=false;
	private static $_API_VERSION=5012;
	
	public static function init($pid,$plugin)
	{
		if(!is_numeric($pid))
		{
			self::killit('参数错误,请传入正确的PID(0001)');
			exit();
		}
		self::$_PLUGIN=$plugin;
		if(self::$_PID!==false && self::$_PID!=$pid)
		{
			self::killit('非法访问(0002)');
			exit();
		}
		self::$_PID=$pid;
	}
	
	public static function checkKernelVersion()
	{
		if(self::$_PID===false)
		{
			self::killit('SDK尚未初始化(0003)');
			exit();
		}
		if(!class_exists('\\ZXDAKernel\\Main'))
		{
			self::killit('请到 https://pl.zxda.net/ 下载安装最新版ZXDA Kernel后再使用此插件(0004)');
			exit();
		}
		$version=\ZXDAKernel\Main::getVersion();
		if($version<self::$_API_VERSION)
		{
			self::killit('当前ZXDA Kernel版本太旧,无法使用此插件,请到 https://pl.zxda.net/ 下载安装最新版后再使用此插件(0005)');
			exit();
		}
		return $version;
	}
	
	public static function isTrialVersion()
	{
		try
		{
			self::checkKernelVersion();
			return \ZXDAKernel\Main::isTrialVersion(self::$_PID);
		}
		catch(\Exception $err)
		{
			@file_put_contents(self::$_PLUGIN->getServer()->getDataPath().'0007_data.dump',var_export($err,true));
			self::killit('未知错误(0007),错误数据已保存到 0007_data.dump 中,请提交到群内获取帮助');
		}
	}
	
	public static function requestCheck()
	{
		try
		{
			self::checkKernelVersion();
			self::$_VERIFIED=false;
			self::$_TOKEN=sha1(uniqid());
			if(!\ZXDAKernel\Main::requestAuthorization(self::$_PID,self::$_PLUGIN,self::$_TOKEN))
			{
				self::killit('请求授权失败,请检查PID是否已正确传入(0006)');
				exit();
			}
		}
		catch(\Exception $err)
		{
			@file_put_contents(self::$_PLUGIN->getServer()->getDataPath().'0007_data.dump',var_export($err,true));
			self::killit('未知错误(0007),错误数据已保存到 0007_data.dump 中,请提交到群内获取帮助');
		}
	}
	
	public static function tokenCheck($key)
	{
		try
		{
			self::checkKernelVersion();
			self::$_VERIFIED=false;
			$manager=self::$_PLUGIN->getServer()->getPluginManager();
			if(!($plugin=$manager->getPlugin('ZXDAKernel')) instanceof \ZXDAKernel\Main)
			{
				self::killit('ZXDA Kernel加载失败,请检查插件是否已正常安装(0008)');
			}
			if(!$manager->isPluginEnabled($plugin))
			{
				$manager->enablePlugin($plugin);
			}
			$key=base64_decode($key);
			if(($token=\ZXDAKernel\Main::getResultToken(self::$_PID))===false)
			{
				self::killit('请勿进行非法破解(0009)');
			}
			if(self::rsa_decode(base64_decode($token),$key,768)!=sha1(strrev(self::$_TOKEN)))
			{
				self::killit('插件Key错误,请更新插件或联系作者(0010)');
			}
			self::$_VERIFIED=true;
		}
		catch(\Exception $err)
		{
			@file_put_contents(self::$_PLUGIN->getServer()->getDataPath().'0007_data.dump',var_export($err,true));
			self::killit('未知错误(0007),错误数据已保存到 0007_data.dump 中,请提交到群内获取帮助');
		}
	}
	
	public static function isVerified()
	{
		return self::$_VERIFIED;
	}
	
	public static function getInfo()
	{
		try
		{
			self::checkKernelVersion();
			$manager=self::$_PLUGIN->getServer()->getPluginManager();
			if(!($plugin=$manager->getPlugin('ZXDAKernel')) instanceof \ZXDAKernel\Main)
			{
				self::killit('ZXDA Kernel加载失败,请检查插件是否已正常安装(0008)');
			}
			if(($data=\ZXDAKernel\Main::getPluginInfo(self::$_PID))===false)
			{
				self::killit('请勿进行非法破解(0009)');
			}
			if(count($data=explode(',',$data))!=2)
			{
				return array(
					'success'=>false,
					'message'=>'未知错误');
			}
			return array(
				'success'=>true,
				'version'=>base64_decode($data[0]),
				'update_info'=>base64_decode($data[1]));
		}
		catch(\Exception $err)
		{
			@file_put_contents(self::$_PLUGIN->getServer()->getDataPath().'0007_data.dump',var_export($err,true));
			self::killit('未知错误(0007),错误数据已保存到 0007_data.dump 中,请提交到群内获取帮助');
		}
	}
	
	public static function killit($msg)
	{
		if(self::$_PLUGIN===null)
		{
			echo('抱歉,插件授权验证失败[SDK:'.self::$_API_VERSION."]\n附加信息:".$msg);
		}
		else
		{
			@self::$_PLUGIN->getLogger()->warning('§e抱歉,插件授权验证失败[SDK:'.self::$_API_VERSION.']');
			@self::$_PLUGIN->getLogger()->warning('§e附加信息:'.$msg);
			@self::$_PLUGIN->getServer()->forceShutdown();
		}
		exit();
	}
	
	//RSA加密算法实现
	public static function rsa_encode($message,$modulus,$keylength=1024,$isPriv=true){$result=array();while(strlen($msg=substr($message,0,$keylength/8-5))>0){$message=substr($message,strlen($msg));$result[]=self::number_to_binary(self::pow_mod(self::binary_to_number(self::add_PKCS1_padding($msg,$isPriv,$keylength/8)),'65537',$modulus),$keylength/8);unset($msg);}return implode('***&&&***',$result);}
	public static function rsa_decode($message,$modulus,$keylength=1024){$result=array();foreach(explode('***&&&***',$message) as $message){$result[]=self::remove_PKCS1_padding(self::number_to_binary(self::pow_mod(self::binary_to_number($message),'65537',$modulus),$keylength/8),$keylength/8);unset($message);}return implode('',$result);}
	private static function pow_mod($p,$q,$r){$factors=array();$div=$q;$power_of_two=0;while(bccomp($div,'0')==1){$rem=bcmod($div,2);$div=bcdiv($div,2);if($rem){array_push($factors,$power_of_two);}$power_of_two++;}$partial_results=array();$part_res=$p;$idx=0;foreach($factors as $factor){while($idx<$factor){$part_res=bcpow($part_res,'2');$part_res=bcmod($part_res,$r);$idx++;}array_push($partial_results,$part_res);}$result='1';foreach($partial_results as $part_res){$result=bcmul($result,$part_res);$result=bcmod($result,$r);}return $result;}
	private static function add_PKCS1_padding($data,$isprivateKey,$blocksize){$pad_length=$blocksize-3-strlen($data);if($isprivateKey){$block_type="\x02";$padding='';for($i=0;$i<$pad_length;$i++){$rnd=mt_rand(1,255);$padding .= chr($rnd);}}else{$block_type="\x01";$padding=str_repeat("\xFF",$pad_length);}return "\x00".$block_type.$padding."\x00".$data;}
	private static function remove_PKCS1_padding($data,$blocksize){assert(strlen($data)==$blocksize);$data=substr($data,1);if($data{0}=='\0'){return '';}assert(($data{0}=="\x01") || ($data{0}=="\x02"));$offset=strpos($data,"\0",1);return substr($data,$offset+1);}
	private static function binary_to_number($data){$radix='1';$result='0';for($i=strlen($data)-1;$i>=0;$i--){$digit=ord($data{$i});$part_res=bcmul($digit,$radix);$result=bcadd($result,$part_res);$radix=bcmul($radix,'256');}return $result;}
	private static function number_to_binary($number,$blocksize){$result='';$div=$number;while($div>0){$mod=bcmod($div,'256');$div=bcdiv($div,'256');$result=chr($mod).$result;}return str_pad($result,$blocksize,"\x00",STR_PAD_LEFT);}
}
