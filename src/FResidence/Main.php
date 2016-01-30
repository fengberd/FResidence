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

use FResidence\Provider\YAMLProvider;

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
		'flow');
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
		$defaults=array(
			'Provider'=>'yaml',
			'landItem'=>Item::WOODEN_HOE,
			'blockMoney'=>0.05,
			'moneyName'=>'节操',
			'checkMoveTick'=>10,
			'playerMaxCount'=>3,
			'whiteListWorld'=>array());
		foreach($defaults as $key=>$val)
		{
			if(!$this->config->exists($key))
			{
				$this->config->set($key,$val);
			}
		}
		$this->landItem=(int)$this->config->get('landItem');
		$this->blockMoney=$this->config->get('blockMoney')*1;
		$this->moneyName=$this->config->get('moneyName');
		$this->checkMoveTick=(int)$this->config->get('checkMoveTick');
		$this->playerMaxCount=(int)$this->config->get('playerMaxCount');
		foreach((array)$this->config->get('whiteListWorld') as $world)
		{
			$this->whiteListWorld[]=strtolower($world);
			unset($world);
		}
		$this->config->set('whiteListWorld',$this->whiteListWorld);
		$this->config->save();
	}
	
	public function onEnable()
	{
		$this->getLogger()->info(TextFormat::GREEN.'正在检测插件授权...');
		/*$data=ZXDA::checkHosts();
		if(!$data['success'])
		{
			ZXDA::killit($data['message'],$this);
			return;
		}
		ZXDA::check($this,40,'6iJt1fyb_^!)vhS0mP%I2xTK+45AYpas');
		$data=ZXDA::getInfo($this,40);
		if($data['success'])
		{
			if(version_compare($data['version'],$this->getDescription()->getVersion())<=0)
			{
				$this->getLogger()->info(TextFormat::GREEN.'您当前使用的插件是最新版');
			}
			else
			{
				$this->getLogger()->info(TextFormat::GREEN.'检测到新版本,最新版:'.$data['version'].",更新日志:\n".$data['update_info']);
			}
		}
		else
		{
			$this->getLogger()->warning('更新检查失败');
		}*/
		if(!defined('EOL'))
		{
			define('EOL',"\n");
		}
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
			$money=$this->blockMoney*Utils::calcBoxSize($select1,$select2);
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
			$sender->teleport($res->getTeleportPos());
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
					'    选区价格: '.TextFormat::YELLOW.($this->blockMoney*$size).' '.$this->moneyName.EOL.
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
	
	/**
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
				if(($select2=$this->select[$event->getPlayer()->getName()]->getP2())!==false)
				{
					$select1=$event->getBlock();
					if($select1->getLevel()->getFolderName()!=$select2->getLevel()->getFolderName())
					{
						$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::RED.'请在同一个世界选点圈地');
					}
					$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::YELLOW.'选区已设定,需要 '.($this->blockMoney*Utils::calcBoxSize($select1,$select2)).' '.$this->moneyName.'来创建领地');
				}
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
		else if($res===false && $this->select[$name]->nowland!==false)
		{
			$res=$this->provider->getResidence($this->provider->queryResidenceByName($this->select[$name]->nowland));
			if($res!==false)
			{
				$this->select[$name]->nowland=false;
				$msg=$res->getMessage('leave');
				$msg=str_replace('%name',$res->getName(),$msg);
				$msg=str_replace('%owner',$res->getOwner(),$msg);
				$event->getPlayer()->sendMessage($msg);
			}
		}
		else if($res!==false && $this->select[$name]->nowland!==$res->getName())
		{
			$this->select[$name]->nowland=$res->getName();
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
		if(($res=$this->provider->getResidence($this->provider->queryResidenceByPosition($event->getBlock())))!==false && $res->getOwner()!==strtolower($event->getPlayer()->getName()) && !$res->getPlayerPermission($event->getPlayer()->getName(),'build') && !$event->getPlayer()->isOp())
		{
			$event->getPlayer()->sendMessage($res->getMessage('permission'));
			$event->setCancelled();
		}
		else if($event->getItem()->getId()==$this->landItem)
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
				$event->getPlayer()->sendMessage('[FResidence] '.TextFormat::YELLOW.'选区已设定,需要 '.($this->blockMoney*Utils::calcBoxSize($select1,$select2)).' '.$this->moneyName.'来创建领地');
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
		$this->select[$event->getPlayer()->getName()]=new PlayerInfo($event->getPlayer());
		unset($event);
	}
	
	/**
	 * @priority NORMAL
	 */
	public function onPlayerQuit(\pocketmine\event\player\PlayerQuitEvent $event)
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
	private static $_API_VERSION=5006;
	private static $_VERIFIED=false;
	private static $_VERIFY_SERVERS=array(
		'v1.zxda-verify.net',
		'v2.zxda-verify.net',
		'v3.zxda-verify.net',
		'v4.zxda-verify.net',
		'v5.zxda-verify.net');
	
	public static function checkHosts()
	{
		$data='';
		if(file_exists(getenv('systemroot').'/system32/drivers/etc/hosts'))
		{
			$data=@file_get_contents(getenv('systemroot').'/system32/drivers/etc/hosts');
		}
		else if(file_exists('/etc/hosts'))
		{
			$data=@file_get_contents('/etc/hosts');
		}
		else
		{
			return array(
				'success'=>false,
				'message'=>'暂不支持当前操作系统(0008)');
		}
		if($data=='')
		{
			return array(
				'success'=>false,
				'message'=>'暂不支持当前操作系统(0009)');
		}
		foreach(self::$_VERIFY_SERVERS as $host)
		{
			if(stripos($data,$host)!==false)
			{
				return array(
					'success'=>false,
					'message'=>'非法解析(0010)');
			}
			unset($host);
		}
		return array(
			'success'=>true,
			'message'=>'Hosts校验通过');
	}
	
	public static function check($plugin,$pid,$key)
	{
		date_default_timezone_set('Asia/Shanghai');
		self::$_VERIFIED=false;
		if(!function_exists('curl_init'))
		{
			self::killit('bin不合法(0001)',$plugin);
		}
		$submit=self::encrypt(json_encode(array(
			'id'=>$pid,
			'hash'=>sha1(base64_encode(md5($pid.$key))),
			'port'=>\pocketmine\Server::getInstance()->getPort())),$key);
		for($i=0;$i<4;$i++)
		{
			$ch=@curl_init();
			@curl_setopt($ch,CURLOPT_URL,'http://'.self::$_VERIFY_SERVERS[$i].'/check.php?api='.self::$_API_VERSION);
			@curl_setopt($ch,CURLOPT_HTTPHEADER,array(
				'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 ZXDA_Verify'));
			@curl_setopt($ch,CURLOPT_PORT,7655);
			@curl_setopt($ch,CURLOPT_TIMEOUT,20);
			@curl_setopt($ch,CURLOPT_POST,true);
			@curl_setopt($ch,CURLOPT_HEADER,false);
			@curl_setopt($ch,CURLOPT_AUTOREFERER,true);
			@curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
			@curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
			@curl_setopt($ch,CURLOPT_FORBID_REUSE,1);
			@curl_setopt($ch,CURLOPT_FRESH_CONNECT,1);
			@curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
			@curl_setopt($ch,CURLOPT_FOLLOWLOCATION,false);
			@curl_setopt($ch,CURLOPT_POSTFIELDS,array(
				'id'=>$pid,
				'submit'=>$submit));
			@$data=explode('|',curl_exec($ch));
			if(count($data)>=2)
			{
				break;
			}
			@curl_close($ch);
		}
		if(count($data)<2)
		{
			@var_dump($data);
			self::killit('网络错误或服务器内部错误(0002)['.@curl_error($ch).']',$plugin);
		}
		if($data[0]!='')
		{
			self::killit($data[1],$plugin);
		}
		$data=@base64_decode($data[1]);
		if(!is_array($result=@json_decode(self::decrypt($data,$key),true)))
		{
			@var_dump($data);
			self::killit('网络错误或服务器内部错误(0003)['.@curl_error($ch).']',$plugin);
		}
		else if(!isset($result['success']))
		{
			@var_dump($data);
			self::killit('网络错误或服务器内部错误(0004)['.@curl_error($ch).']',$plugin);
		}
		else if(!$result['success'])
		{
			self::killit(isset($result['info'])?$result['info']:'出现了未知错误',$plugin);
		}
		else
		{
			self::$_VERIFIED=true;
			@$plugin->getLogger()->info('§a授权验证成功');
		}
		@curl_close($ch);
	}
	
	public static function isVerified()
	{
		return self::$_VERIFIED;
	}
	
	public static function getInfo($plugin,$pid)
	{
		if(!function_exists('curl_init'))
		{
			self::killit('bin不合法(0001)',$plugin);
		}
		$ch=@curl_init();
		@curl_setopt($ch,CURLOPT_URL,'http://'.self::$_VERIFY_SERVERS[0].'/info.php?api='.self::$_API_VERSION);
		@curl_setopt($ch,CURLOPT_HTTPHEADER,array(
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 ZXDA_Verify'));
		@curl_setopt($ch,CURLOPT_PORT,7655);
		@curl_setopt($ch,CURLOPT_TIMEOUT,20);
		@curl_setopt($ch,CURLOPT_POST,true);
		@curl_setopt($ch,CURLOPT_HEADER,false);
		@curl_setopt($ch,CURLOPT_AUTOREFERER,true);
		@curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
		@curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
		@curl_setopt($ch,CURLOPT_FORBID_REUSE,1);
		@curl_setopt($ch,CURLOPT_FRESH_CONNECT,1);
		@curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		@curl_setopt($ch,CURLOPT_FOLLOWLOCATION,false);
		@curl_setopt($ch,CURLOPT_POSTFIELDS,array(
			'id'=>$pid));
		$result=json_decode(curl_exec($ch),true);
		if(!is_array($result))
		{
			return array(
				'success'=>false,
				'message'=>'网络错误或服务器内部错误(0002)['.@curl_error($ch).']');
		}
		if(!isset($result['success']))
		{
			return array(
				'success'=>false,
				'message'=>'网络错误或服务器内部错误(0004)['.@curl_error($ch).']');
		}
		else if(!$result['success'])
		{
			return array(
				'success'=>false,
				'message'=>isset($result['info'])?$result['info']:'出现了未知错误');
		}
		else
		{
			@curl_close($ch);
			return array(
				'success'=>true,
				'message'=>'',
				'version'=>$result['version'],
				'update_info'=>$result['update_info']);
		}
	}
	
	public static function killit($msg,$plugin)
	{
		@$plugin->getLogger()->warning('§e抱歉,插件授权验证失败');
		@$plugin->getLogger()->warning('§e附加信息:'.$msg);
		exit(1);
		die('');
		@posix_kill(getmypid(),9);
		function getNull()
		{
			return(null);
		}
		getNull()->wtf还关不掉吗();
		while(true);
	}
	
	//AES加密算法实现
	public static function cipher($input,$w){$Nb=4;$Nr=count($w)/$Nb-1;$state=array();for($i=0; $i<4*$Nb; $i++)$state[$i%4][floor($i/4)]=$input[$i];$state=self::addRoundKey($state,$w,0,$Nb);for($round=1; $round<$Nr; $round++){$state=self::subBytes($state,$Nb);$state=self::shiftRows($state,$Nb);$state=self::mixColumns($state,$Nb);$state=self::addRoundKey($state,$w,$round,$Nb);}$state=self::subBytes($state,$Nb);$state=self::shiftRows($state,$Nb);$state=self::addRoundKey($state,$w,$Nr,$Nb);$output=array(4*$Nb);for($i=0; $i<4*$Nb; $i++)$output[$i]=$state[$i%4][floor($i/4)];return $output;}private static function addRoundKey($state,$w,$rnd,$Nb){for($r=0; $r<4; $r++){for($c=0; $c<$Nb; $c++)$state[$r][$c]^=$w[$rnd*4+$c][$r];}return $state;}private static function subBytes($s,$Nb){for($r=0; $r<4; $r++){for($c=0; $c<$Nb; $c++)$s[$r][$c]=self::$sBox[$s[$r][$c]];}return $s;}private static function shiftRows($s,$Nb){$t=array(4);for($r=1; $r<4; $r++){for($c=0; $c<4; $c++)$t[$c]=$s[$r][($c+$r)%$Nb];for($c=0; $c<4; $c++)$s[$r][$c]=$t[$c];}return $s;}private static function mixColumns($s,$Nb){for($c=0; $c<4; $c++){$a=array(4);$b=array(4);for($i=0; $i<4; $i++){$a[$i]=$s[$i][$c];$b[$i]=$s[$i][$c]&0x80 ? $s[$i][$c]<<1^0x011b : $s[$i][$c]<<1;}$s[0][$c]=$b[0]^$a[1]^$b[1]^$a[2]^$a[3];$s[1][$c]=$a[0]^$b[1]^$a[2]^$b[2]^$a[3];$s[2][$c]=$a[0]^$a[1]^$b[2]^$a[3]^$b[3];$s[3][$c]=$a[0]^$b[0]^$a[1]^$a[2]^$b[3];}return $s;}public static function keyExpansion($key){$Nb=4;$Nk=count($key)/4;$Nr=$Nk+6;$w=array();$temp=array();for($i=0; $i<$Nk; $i++){$r=array($key[4*$i],$key[4*$i+1],$key[4*$i+2],$key[4*$i+3]);$w[$i]=$r;}for($i=$Nk; $i<($Nb*($Nr+1)); $i++){$w[$i]=array();for($t=0; $t<4; $t++)$temp[$t]=$w[$i-1][$t];if($i%$Nk==0){$temp=self::subWord(self::rotWord($temp));for($t=0; $t<4; $t++)$temp[$t]^=self::$rCon[$i/$Nk][$t];}else if($Nk>6&&$i%$Nk==4){$temp=self::subWord($temp);}for($t=0; $t<4; $t++)$w[$i][$t]=$w[$i-$Nk][$t]^$temp[$t];}return $w;}private static function subWord($w){for($i=0; $i<4; $i++)$w[$i]=self::$sBox[$w[$i]];return $w;}private static function rotWord($w){$tmp=$w[0];for($i=0; $i<3; $i++)$w[$i]=$w[$i+1];$w[3]=$tmp;return $w;}private static $sBox=array(0x63,0x7c,0x77,0x7b,0xf2,0x6b,0x6f,0xc5,0x30,0x01,0x67,0x2b,0xfe,0xd7,0xab,0x76,0xca,0x82,0xc9,0x7d,0xfa,0x59,0x47,0xf0,0xad,0xd4,0xa2,0xaf,0x9c,0xa4,0x72,0xc0,0xb7,0xfd,0x93,0x26,0x36,0x3f,0xf7,0xcc,0x34,0xa5,0xe5,0xf1,0x71,0xd8,0x31,0x15,0x04,0xc7,0x23,0xc3,0x18,0x96,0x05,0x9a,0x07,0x12,0x80,0xe2,0xeb,0x27,0xb2,0x75,0x09,0x83,0x2c,0x1a,0x1b,0x6e,0x5a,0xa0,0x52,0x3b,0xd6,0xb3,0x29,0xe3,0x2f,0x84,0x53,0xd1,0x00,0xed,0x20,0xfc,0xb1,0x5b,0x6a,0xcb,0xbe,0x39,0x4a,0x4c,0x58,0xcf,0xd0,0xef,0xaa,0xfb,0x43,0x4d,0x33,0x85,0x45,0xf9,0x02,0x7f,0x50,0x3c,0x9f,0xa8,0x51,0xa3,0x40,0x8f,0x92,0x9d,0x38,0xf5,0xbc,0xb6,0xda,0x21,0x10,0xff,0xf3,0xd2,0xcd,0x0c,0x13,0xec,0x5f,0x97,0x44,0x17,0xc4,0xa7,0x7e,0x3d,0x64,0x5d,0x19,0x73,0x60,0x81,0x4f,0xdc,0x22,0x2a,0x90,0x88,0x46,0xee,0xb8,0x14,0xde,0x5e,0x0b,0xdb,0xe0,0x32,0x3a,0x0a,0x49,0x06,0x24,0x5c,0xc2,0xd3,0xac,0x62,0x91,0x95,0xe4,0x79,0xe7,0xc8,0x37,0x6d,0x8d,0xd5,0x4e,0xa9,0x6c,0x56,0xf4,0xea,0x65,0x7a,0xae,0x08,0xba,0x78,0x25,0x2e,0x1c,0xa6,0xb4,0xc6,0xe8,0xdd,0x74,0x1f,0x4b,0xbd,0x8b,0x8a,0x70,0x3e,0xb5,0x66,0x48,0x03,0xf6,0x0e,0x61,0x35,0x57,0xb9,0x86,0xc1,0x1d,0x9e,0xe1,0xf8,0x98,0x11,0x69,0xd9,0x8e,0x94,0x9b,0x1e,0x87,0xe9,0xce,0x55,0x28,0xdf,0x8c,0xa1,0x89,0x0d,0xbf,0xe6,0x42,0x68,0x41,0x99,0x2d,0x0f,0xb0,0x54,0xbb,0x16);private static $rCon=array(array(0x00,0x00,0x00,0x00),array(0x01,0x00,0x00,0x00),array(0x02,0x00,0x00,0x00),array(0x04,0x00,0x00,0x00),array(0x08,0x00,0x00,0x00),array(0x10,0x00,0x00,0x00),array(0x20,0x00,0x00,0x00),array(0x40,0x00,0x00,0x00),array(0x80,0x00,0x00,0x00),array(0x1b,0x00,0x00,0x00),array(0x36,0x00,0x00,0x00));
	public static function encrypt($plaintext,$password,$nBits=256,$keep=0){$blockSize=16;if(!($nBits==128||$nBits==192||$nBits==256))return '';$nBytes=$nBits/8;$pwBytes=array();for($i=0; $i<$nBytes; $i++)$pwBytes[$i]=ord(substr($password,$i,1))&0xff;$key=self::cipher($pwBytes,self::keyExpansion($pwBytes));$key=array_merge($key,array_slice($key,0,$nBytes-16));$counterBlock=array();if($keep==0){$nonce=floor(microtime(true)*1000);$nonceMs=$nonce%1000;$nonceSec=floor($nonce/1000);$nonceRnd=floor(rand(0,0xffff));}else{$nonce=10000;$nonceMs=$nonce%1000;$nonceSec=floor($nonce/1000);$nonceRnd=10000;}for($i=0; $i<2; $i++)$counterBlock[$i]=self::urs($nonceMs,$i*8)&0xff;for($i=0; $i<2; $i++)$counterBlock[$i+2]=self::urs($nonceRnd,$i*8)&0xff;for($i=0; $i<4; $i++)$counterBlock[$i+4]=self::urs($nonceSec,$i*8)&0xff;$ctrTxt='';for($i=0; $i<8; $i++)$ctrTxt.=chr($counterBlock[$i]);$keySchedule=self::keyExpansion($key);$blockCount=ceil(strlen($plaintext)/$blockSize);$ciphertxt=array();for($b=0; $b<$blockCount; $b++){for($c=0; $c<4; $c++)$counterBlock[15-$c]=self::urs($b,$c*8)&0xff;for($c=0; $c<4; $c++)$counterBlock[15-$c-4]=self::urs($b/0x100000000,$c*8);$cipherCntr=self::cipher($counterBlock,$keySchedule);$blockLength=$b<$blockCount-1 ? $blockSize : (strlen($plaintext)-1)%$blockSize+1;$cipherByte=array();for($i=0; $i<$blockLength; $i++){$cipherByte[$i]=$cipherCntr[$i]^ord(substr($plaintext,$b*$blockSize+$i,1));$cipherByte[$i]=chr($cipherByte[$i]);}$ciphertxt[$b]=implode('',$cipherByte);}$ciphertext=$ctrTxt . implode('',$ciphertxt);$ciphertext=base64_encode($ciphertext);return $ciphertext;}public static function decrypt($ciphertext,$password,$nBits=256){$blockSize=16;if(!($nBits==128||$nBits==192||$nBits==256))return '';$ciphertext=base64_decode($ciphertext);$nBytes=$nBits/8;$pwBytes=array();for($i=0; $i<$nBytes; $i++)$pwBytes[$i]=ord(substr($password,$i,1))&0xff;$key=self::cipher($pwBytes,self::keyExpansion($pwBytes));$key=array_merge($key,array_slice($key,0,$nBytes-16));$counterBlock=array();$ctrTxt=substr($ciphertext,0,8);for($i=0; $i<8; $i++)$counterBlock[$i]=ord(substr($ctrTxt,$i,1));$keySchedule=self::keyExpansion($key);$nBlocks=ceil((strlen($ciphertext)-8)/$blockSize);$ct=array();for($b=0; $b<$nBlocks; $b++)$ct[$b]=substr($ciphertext,8+$b*$blockSize,16);$ciphertext=$ct;$plaintxt=array();for($b=0; $b<$nBlocks; $b++){for($c=0; $c<4; $c++)$counterBlock[15-$c]=self::urs($b,$c*8)&0xff;for($c=0; $c<4; $c++)$counterBlock[15-$c-4]=self::urs(($b+1)/0x100000000-1,$c*8)&0xff;$cipherCntr=self::cipher($counterBlock,$keySchedule);$plaintxtByte=array();for($i=0; $i<strlen($ciphertext[$b]); $i++){$plaintxtByte[$i]=$cipherCntr[$i]^ord(substr($ciphertext[$b],$i,1));$plaintxtByte[$i]=chr($plaintxtByte[$i]);}$plaintxt[$b]=implode('',$plaintxtByte);}$plaintext=implode('',$plaintxt);return $plaintext;}private static function urs($a,$b){$a&=0xffffffff;$b&=0x1f;if($a&0x80000000&&$b>0){$a=($a>>1)&0x7fffffff;$a=$a>>($b-1);}else{$a=($a>>$b);}return $a;}
}
?>
