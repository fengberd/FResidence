<?php
namespace FResidence;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block as Blocks;

use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;

use FResidence\utils\Utils;
use FResidence\utils\Economy;
use FResidence\utils\Messages;
use FResidence\utils\PlayerInfo;
use FResidence\utils\Permissions;

use FResidence\provider as Providers;
use FResidence\provider\ConfigProvider;

use FResidence\event\ResidenceAddEvent;
use FResidence\event\ResidenceRemoveEvent;

use FResidence\exception\FResidenceException;

class Main extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener
{
	private static $obj=null;
	public static $protectedBlocks=array(
		Block::TNT,
		Block::SAPLING,
		Block::BED_BLOCK,
		Block::CAKE_BLOCK,
		Block::SUGARCANE_BLOCK,
		
		Block::ANVIL,
		Block::FURNACE,
		Block::DROPPER,
		Block::DISPENSER,
		Block::ENDER_CHEST,
		Block::HOPPER_BLOCK,
		Block::CAULDRON_BLOCK,
		Block::BURNING_FURNACE,
		Block::ENCHANTING_TABLE,
		Block::ITEM_FRAME_BLOCK,
		Block::FLOWER_POT_BLOCK,
		
		Block::LEVER,
		Block::NOTE_BLOCK,
		Block::DAYLIGHT_SENSOR,
		Block::DAYLIGHT_SENSOR_INVERTED);
	
	private static $_RES_COMMAND_HELP=array(
		'select'=>array(1,'/res select <size|chunk|vert|X Y Z>','查看选区大小/选取整个区块/扩展选区Y坐标到0-128/选择以当前坐标为起点 ,半径为指定大小的选区'),
		
		// TODO: area
		'create'=>array(1,'/res create <名称>','创建一个领地'),
		'remove'=>array(1,'/res remove <名称>','移除指定名称的领地'),
		'removeall'=>array(0,'/res removeall','移除你的所有领地'),
		// TODO: subzone
		
		'current'=>array(0,'/res current','查询当前在哪块领地上'),
		'info'=>array(0,'/res info [领地]','查询指定领地/当前所在领地的信息'),
		'list'=>array(0,'/res list [页码]','列出你的所有领地'),
		// TODO: sublist
		'version'=>array(0,'/res version','显示版本信息'),
		'help'=>array(0,'/res help [页码]','查看使用帮助'),
		'confirm'=>array(1,'/res confirm <验证码>','确认执行危险操作'),
		
		'pset'=>array(4,'/res pset <领地> <玩家> <权限> <true/false/remove>','设置/删除某玩家的领地权限'),
		'set'=>array(3,'/res set <领地> <权限> <true/false>','设置领地默认权限'),
		
		'default'=>array(1,'/res default <领地>','重置领地的所有权限'),
		'give'=>array(2,'/res give <领地> <玩家>','把领地赠送给某玩家'),
		'message'=>array(2,'/res message <领地> <索引> [内容]','设置领地的消息内容,不填内容清除消息'),
		'mirror'=>array(2,'/res mirror <源领地> <目标领地>','将源领地的权限数据复制到目标领地'),
		'rename'=>array(2,'/res rename <领地> <名字>','重命名领地'),
		// TODO: renamearea
		'tp'=>array(1,'/res tp <领地>','传送到某领地'),
		'tpset'=>array(0,'/res tpset','设置当前坐标为当前领地传送点')
		// TODO: unstuck
		);
	private static $_RESADMIN_COMMAND_HELP=array(
		'list'=>array(1,'/resadmin list <玩家> [页码]','列出某玩家的所有领地'),
		'listall'=>array(0,'/resadmin listall','列出服务器上的所有领地'),
		
		'removeall'=>array(1,'/resadmin removeall <玩家>','移除某玩家的所有领地'),
		'setowner'=>array(2,'/resadmin setowner <领地> <玩家>','设置领地的主人'),
		'server'=>array(1,'/resadmin server <领地>','把领地设置为服务器领地,然后只有管理员能操作'),
		
		'reload'=>array(0,'/resadmin reload','重载插件数据'),
		'parse'=>array(0,'/resadmin parse','转换EconomyLand的数据到插件里,只有后台能使用'),
		
		'help'=>array(0,'/resadmin help [页码]','查看使用帮助'));
	
	public static function getInstance()
	{
		return self::$obj;
	}
	
	private $provider=null;
	
	private $players=array();
	private $blackListWorld=array();
	private $whiteListWorld=array();
	
	public function getProvider()
	{
		return $this->provider;
	}
	
	public function reload()
	{
		ConfigProvider::init($this);
		Permissions::init();
		Economy::init(ConfigProvider::PreferEconomy());
		switch(strtolower(ConfigProvider::Provider()))
		{
		/*case 'mysql':
			break;
		case 'sqlite3':
			break;*/
		default:
			$this->getLogger()->warning('Provider不受支持,已切换至Yaml模式');
		case 'yaml':
			$this->provider=new Providers\YamlDataProvider($this);
			break;
		}
		ConfigProvider::Provider($this->provider->getName());
		ConfigProvider::PreferEconomy(Economy::getApiName());
		$this->getLogger()->notice('当前经济API:'.Economy::getApiName());
		$this->getLogger()->notice('当前Provider:'.$this->provider->getName());
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
		try
		{
			$this->reload();
			$this->getServer()->getScheduler()->scheduleRepeatingTask($this->systemTask=new SystemTask($this),20);
			
			$reflection=new \ReflectionClass(get_class($this));
			foreach($reflection->getMethods() as $method)
			{
				if(!$method->isStatic())
				{
					$priority=0;
					$parameters=$method->getParameters();
					if(count($parameters)===1 and $parameters[0]->getClass() instanceof \ReflectionClass and is_subclass_of($parameters[0]->getClass()->getName(),\pocketmine\event\Event::class))
					{
						$class=$parameters[0]->getClass()->getName();
						$reflection=new \ReflectionClass($class);
						$this->getServer()->getPluginManager()->registerEvent($class,$this,$priority,new \pocketmine\plugin\MethodEventExecutor($method->getName()),$this,false);
					}
				}
				unset($method);
			}
		}
		catch(FResidenceException $e)
		{
			$this->getLogger()->warning('初始化插件时出现错误: '.$e->getMessage().',即将关闭服务器');
			$this->getServer()->forceShutdown();
		}
	}
	
	public function systemTaskCallback($currentTick)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		foreach($this->players as $player)
		{
			if($player->inResidence() && $player->getResidence()->getPermission(Permissions::PERMISSION_HEALING))
			{
				$ev=new EntityRegainHealthEvent($player->getPlayer(),1,EntityRegainHealthEvent::CAUSE_CUSTOM);
				$player->heal($ev->getAmount(),$ev);
				unset($ev);
			}
			unset($player);
		}
		unset($currentTick);
	}
	
	public function getPlayer($mixed)
	{
		if(is_string($mixed))
		{
			$mixed=$this->getServer()->getPlayer($mixed);
		}
		if($mixed instanceof \pocketmine\event\player\PlayerEvent)
		{
			$mixed=$mixed->getPlayer();
		}
		if($mixed instanceof Player || $mixed instanceof PlayerInfo)
		{
			$mixed=$mixed->getId();
		}
		if(is_int($mixed))
		{
			return isset($this->players[$mixed])?$this->players[$mixed]:null;
		}
		return null;
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
			$args[0]='help';
		}
		$args[0]=strtolower($args[0]);
		try
		{
			if(strtolower($command->getName())=='residenceadmin')
			{
				if($sender->hasPermission('Residence.admin'))
				{
					$this->onResidenceAdminCommand($sender,$args);
				}
				else
				{
					$sender->sendMessage(Utils::getRedString('权限不足'));
				}
			}
			else
			{
				$this->onResidenceCommand($sender,$args);
			}
		}
		catch(FResidenceException $e)
		{
			$sender->sendMessage(Utils::getRedString('无法完成操作: '.$e->getMessage()));
		}
		/*
		switch(isset($args[0])?$args[0]:'help')
		{
		case 'wl':
		case 'whitelist':
			if(!$sender->isOp())
			{
				$sender->sendRedMessage('你没有权限进行此操作');
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
					$sender->sendRedMessage('该世界已在白名单列表中');
					break;
				}
				$this->whiteListWorld[]=$args[2];
				$sender->sendGreenMessage('白名单世界添加成功');
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
					$sender->sendRedMessage('该世界不在白名单列表中');
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
				$sender->sendGreenMessage('白名单世界移除成功');
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
				$sender->sendGreenMessage('白名单世界清空成功');
				break;
			default:
				$sender->sendMessage('[FResidence] '.TextFormat::AQUA.'使用方法: /res whitelist [add <世界名>|remove <世界名>|list|clear]');
				break;
			}
			$this->config->set('whiteListWorld',$this->whiteListWorld);
			$this->config->save();
			break;
		}*/
		unset($sender,$command,$label,$args);
		return true;
	}
	
	public function onResidenceCommand($sender,array $args,$granted=false)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(!isset(self::$_RES_COMMAND_HELP[$args[0]]))
		{
			$sender->sendMessage(Utils::getRedString('未知指令,请使用 /res help 查看帮助'));
		}
		else if(!isset($args[self::$_RES_COMMAND_HELP[$args[0]][0]]))
		{
			$sender->sendMessage(Utils::getAquaString('使用方法: '.self::$_RES_COMMAND_HELP[$args[0]][1]));
		}
		else if($args[0]=='help' || $args[0]=='version' || ($sender instanceof Player && isset($this->players[$sender->getId()])))
		{
			if($sender instanceof Player)
			{
				$sender=$this->getPlayer($sender);
			}
			switch($args[0])
			{
			case 'select':
				switch(strtolower($args[1]))
				{
				case 'size':
					if(!$sender->isSelectFinish())
					{
						$sender->sendRedMessage('请先选择领地范围再执行此命令');
						break;
					}
					if(($size=$sender->validateSelect())<2*2*2)
					{
						$sender->sendRedMessage('选区无效,请确保你选择的两个点在同一个世界内并且选区大于2x2x2');
						unset($size);
						break;
					}
					$pos1=$sender->getPos1();
					$pos2=$sender->getPos2();
					$sender->sendGreenMessage(implode("\n    ".TextFormat::GREEN,array(
						'当前选区信息:',
						'大小: '.TextFormat::YELLOW.$size.' 方块',
						'价格: '.TextFormat::YELLOW.(ConfigProvider::MoneyPerBlock()*$size).' '.ConfigProvider::MoneyName(),
						'坐标: '.TextFormat::YELLOW.'('.$pos1->getX().','.$pos1->getY().','.$pos1->getZ().')->('.$pos2->getX().','.$pos2->getY().','.$pos2->getZ().')')));
					unset($size,$pos1,$pos2);
					break;
				case 'chunk':
					$sender->setPos1(new Position(($sender->getX()>>4)*16,0,($sender->getZ()>>4)*16,$sender->getLevel()))
						->setPos2(new Position(($sender->getX()>>4)*16+16,256,($sender->getZ()>>4)*16+16,$sender->getLevel()))
						->sendGreenMessage('已选中当前所在区块')->validateSelect(true);
					break;
				case 'vert':
					if(!$sender->isSelectFinish())
					{
						$sender->sendRedMessage('请先选择两个点再执行此命令');
						break;
					}
					$sender->getPos1()->y=0;
					$sender->getPos2()->y=256;
					$sender->sendGreenMessage('已将选区Y坐标扩展到0-256格')->validateSelect(true);
					break;
				default:
					if(isset($args[3]))
					{
						$offset=new Vector3(...array_map(function($val)
						{
							return abs(intval($val));
						},array_slice($args,1)));
						$sender->setPos1(Position::fromObject($sender->add($offset),$sender->getLevel()))->setPos2(Position::fromObject($sender->add($offset->multiply(-1)),$sender->getLevel()))->validateSelect(true);
						unset($offset);
					}
					else
					{
						$sender->sendAquaMessage('使用方法: '.self::$_RES_COMMAND_HELP[$args[0]][1]);
					}
					break;
				}
				break;
			case 'create':
				if(!$sender->isSelectFinish())
				{
					$sender->sendRedMessage('请先选择领地范围再创建领地');
					break;
				}
				if(!$granted && in_array(strtolower($sender->getPos1()->getLevel()->getFolderName()),ConfigProvider::BlackListWorld()))
				{
					$sender->sendRedMessage('当前世界不允许创建领地');
					break;
				}
				if(strlen($args[1])<=0 || strlen($args[1])>=60)
				{
					$sender->sendRedMessage('无效领地名称');
					break;
				}
				if(!$granted && count($this->provider->getResidencesByOwner($sender->getName()))>=ConfigProvider::MaxResidenceCount())
				{
					$sender->sendYellowMessage('你的领地数量已经达到了上限 '.ConfigProvider::MaxResidenceCount().' 块');
					break;
				}
				if($this->provider->getResidenceByName($args[1])!==null)
				{
					$sender->sendRedMessage('已存在重名领地');
					break;
				}
				if(($money=$sender->validateSelect())<2*2*2)
				{
					$sender->sendRedMessage('选区无效,请确保你选择的两个点在同一个世界内并且选区大于2x2x2');
					break;
				}
				$money*=$granted?0:Economy::$MoneyPerBlock;
				if($money>Economy::getMoney($sender))
				{
					$sender->sendRedMessage('你没有足够的钱来圈地 ,需要 '.$money.' '.ConfigProvider::MoneyName());
					break;
				}
				$pos1=$sender->getPos1();
				$pos2=$sender->getPos2();
				$conflict=0;
				foreach($this->provider->getAllResidences() as $res)
				{
					if($res->getLevelName()==strtolower($pos1->getLevel()->getFolderName()) && $this->check($res->getPos1(),$res->getPos2(),$pos1,$pos2))
					{
						$sender->sendYellowMessage('选区与领地 '.$res->getName().' 重叠');
						$conflict++;
					}
					unset($res);
				}
				if($conflict>0)
				{
					$sender->sendRedMessage('选区与 '.$conflict.' 块领地重叠 ,操作失败');
					unset($pos1,$pos2,$conflict);
					break;
				}
				$this->getServer()->getPluginManager()->callEvent($ev=new ResidenceAddEvent($this,$money,$sender->getPos1(),$sender->getPos2(),$args[1],$sender));
				if(!$ev->isCancelled())
				{
					$this->provider->addResidence($ev->getPos1(),$ev->getPos2(),$sender,$ev->getResName());
					Economy::reduceMoney($sender,$ev->getMoney());
					$sender->setPos1(null)->setPos2(null);
					$sender->sendGreenMessage('领地创建成功 ,花费 '.$money.' '.ConfigProvider::MoneyName());
				}
				unset($pos1,$pos2,$conflict,$ev);
				break;
			case 'remove':
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('领地不存在');
					break;
				}
				if(!$granted && !$res->isOwner($sender))
				{
					$sender->sendRedMessage('你没有权限移除这块领地');
					break;
				}
				$this->getServer()->getPluginManager()->callEvent($ev=new ResidenceRemoveEvent($this,$res));
				if(!$ev->isCancelled())
				{
					$this->provider->removeResidence($res);
					$sender->sendGreenMessage('领地 '.$res->getName().' 移除成功');
				}
				break;
			case 'removeall':
				$sender->addConfirm('default',$code=mt_rand(1000,9999),'removeall')->sendYellowMessage('您正在进行一个危险操作(删除自己的所有领地),请使用 /res confirm '.$code.' 继续此操作,一分钟内有效');
				unset($code);
				break;
			
			case 'current':
				if(($res=$this->provider->getResidenceByPosition($sender))===null)
				{
					$sender->sendColorMessage('当前位置没有领地');
					break;
				}
				$sender->sendAquaMessage('您当前在 '.($res->getOwner()==''?'(服务器)':$res->getOwner()).' 的领地 '.$res->getName().' 上');
				break;
			case 'info':
				if(($res=isset($args[1])?$this->provider->getResidenceByName($args[1]):$this->provider->getResidenceByPosition($sender))===null)
				{
					$sender->sendRedMessage(isset($args[1])?'不存在名称为 '.$args[1].' 的领地':'当前位置没有领地');
					break;
				}
				$sender->sendGreenMessage(implode("\n    ".TextFormat::GREEN,array(
					'领地信息查询结果:',
					'名称: '.TextFormat::YELLOW.$res->getName(),
					'主人: '.TextFormat::YELLOW.($res->getOwner()==''?'(服务器)':$res->getOwner()),
					'大小: '.TextFormat::YELLOW.$res->getSize().' 方块')));
				break;
			case 'list':
				$res=$this->provider->getResidencesByOwner($sender);
				if(count($res)<=0)
				{
					$sender->sendYellowMessage('没有查询到任何领地');
					break;
				}
				$sender->sendMessage(Utils::makeList('领地列表',$res,$args[1],($sender instanceof Player || $sender instanceof PlayerInfo)?5:50,function($val)
				{
					return is_string($val)?$val:(TextFormat::DARK_GREEN.$val->getName().TextFormat::WHITE.' - 世界:'.$val->getLevelName().',大小:'.$val->getSize().' 方块');
				}));
				break;
			case 'version':
				$sender->sendMessage(implode("\n".TextFormat::WHITE,array(
					'--- FResidence ---',
					'版本: '.TextFormat::DARK_GREEN.$this->getDescription()->getVersion(),
					'作者: '.TextFormat::DARK_GREEN.'FENGberd',
					'E-Mail: '.TextFormat::DARK_GREEN.'fengberd@gmail.com')));
				break;
			case 'help':
				$sender->sendMessage(Utils::makeList('FResidence Help',self::$_RES_COMMAND_HELP,$args[1],($sender instanceof Player || $sender instanceof PlayerInfo)?5:50));
				break;
			case 'confirm':
				if(($confirm=$player->getConfirm('default',$args[1]))!==null)
				{
					switch($confirm[0])
					{
					case 'removeall':
						$sender->sendGreenMessage('成功移除你的所有领地 (操作 '.$this->provider->removeResidencesByOwner($sender).' 块领地)');
						break;
					}
				}
				break;
			
			case 'pset':
				if(!Utils::validatePlayerName($args[2]=Utils::getPlayerName($args[2])))
				{
					$sender->sendRedMessage('无效的用户名');
					break;
				}
				if(!Permissions::validatePlayerIndex($args[3]=strtolower($args[3])))
				{
					$sender->sendRedMessage(implode("\n    ".TextFormat::WHITE,array(
						'错误的权限索引 ,只能为以下值的任意一个 :',
						Permissions::PERMISSION_USE.' - 使用工作台/箱子等',
						Permissions::PERMISSION_MOVE.' - 玩家移动(关闭后其他玩家不能进入领地)',
						Permissions::PERMISSION_BUILD.' - 破坏/放置方块',
						Permissions::PERMISSION_TELEPORT.' - 传送到领地')));
					break;
				}
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('领地不存在');
					break;
				}
				if(!$granted && !$res->isOwner($sender))
				{
					$sender->sendRedMessage('你没有权限修改这块领地的权限');
					break;
				}
				if(strtolower($args[4])=='remove')
				{
					$res->getPermissions()->removePlayerPermission($args[2],$args[3]);
					$sender->sendGreenMessage('成功移除玩家 '.$args[2].' 在领地 '.$res->getName().' 的权限 '.$args[3]);
				}
				else
				{
					$res->getPermissions()->setPlayerPermission($args[2],$args[3],$args[4]=Utils::parseBool($args[4]));
					$sender->sendGreenMessage('成功设置玩家 '.$args[2].' 在领地 '.$res->getName().' 的权限 '.$args[3].' 为 '.($args[4]?'开启':'关闭'));
				}
				break;
			case 'set':
				if(!Permissions::validateIndex($args[2]=strtolower($args[2])))
				{
					$sender->sendRedMessage(implode("\n    ".TextFormat::WHITE,array(
						'错误的权限索引 ,只能为以下值的任意一个 :',
						Permissions::PERMISSION_USE.' - 使用工作台/箱子等',
						Permissions::PERMISSION_MOVE.' - 玩家移动(关闭后其他玩家不能进入领地)',
						Permissions::PERMISSION_BUILD.' - 破坏/放置方块',
						Permissions::PERMISSION_TELEPORT.' - 传送到领地',
						Permissions::PERMISSION_PVP.' - 玩家互相PVP',
						Permissions::PERMISSION_FLOW.' - 液体流动',
						Permissions::PERMISSION_DAMAGE.' - 造成伤害',
						Permissions::PERMISSION_HEALING.' - 自动回血')));
					break;
				}
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('领地不存在');
					break;
				}
				if(!$granted && !$res->isOwner($sender))
				{
					$sender->sendRedMessage('你没有权限修改这块领地的权限');
					break;
				}
				$res->getPermissions()->setPermission($args[2],$args[3]=Utils::parseBool($args[3]));
				$sender->sendGreenMessage('成功设置领地 '.$res->getName().' 的权限 '.$args[2].' 为 '.($args[3]?'开启':'关闭'));
				break;
			
			case 'default':
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('领地不存在');
					break;
				}
				if(!$granted && !$res->isOwner($sender))
				{
					$sender->sendRedMessage('你没有权限修改这块领地的权限');
					break;
				}
				$res->getPermissions()->resetPermissions();
				$sender->sendGreenMessage('成功重置领地 '.$res->getName().' 的权限数据');
				break;
			case 'give':
				if(!Utils::validatePlayerName($args[2]=Utils::getPlayerName($args[2])))
				{
					$sender->sendRedMessage('无效的用户名');
					break;
				}
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('领地不存在');
					break;
				}
				if(!$granted && !$res->isOwner($sender))
				{
					$sender->sendRedMessage('你没有权限赠送这块领地');
					break;
				}
				$res->setOwner($args[2]);
				$sender->sendGreenMessage('成功把领地 '.$res->getName().' 赠送给玩家 '.$args[2]);
				break;
			case 'message':
				if(!Messages::validateIndex($args[2]=strtolower($args[2])))
				{
					$sender->sendRedMessage(implode("\n    ".TextFormat::WHITE,array(
						'错误的消息索引 ,只能为以下值的任意一个 :',
						Messages::INDEX_ENTER.' - 进入领地的提示',
						Messages::INDEX_LEAVE.' - 离开领地的提示',
						Messages::INDEX_PERMISSION.' - 没有权限使用领地的提示')));
					break;
				}
				;
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('领地不存在');
					break;
				}
				if(!$granted && !$res->isOwner($sender))
				{
					$sender->sendRedMessage('你没有权限修改这块领地的消息');
					break;
				}
				$res->getMessages()->setMessage($args[2],isset($args[3]) && $args[3]!=''?implode(' ',array_slice($args,3)):'');
				$sender->sendGreenMessage('成功'.(isset($args[3]) && $args[3]!=''?'设置':'清除').'领地 '.$res->getName().' 的 '.$args[2].' 消息数据');
				break;
			case 'mirror':
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('源领地不存在');
					break;
				}
				if(!$res->isOwner($sender))
				{
					$sender->sendRedMessage('你不是源领地的主人,无法读取权限数据');
					break;
				}
				if(($dstRes=$this->provider->getResidenceByName($args[2]))===null)
				{
					$sender->sendRedMessage('目标领地不存在');
					unset($dstRes);
					break;
				}
				if(!$dstRes->isOwner($dstRes))
				{
					$sender->sendRedMessage('你没有权限修改目标领地的权限');
					unset($dstRes);
					break;
				}
				$dstRes->setPermissions(new Permissions($res->getPermissions()->getRawData(),$dstRes));
				$sender->sendGreenMessage('成功将领地 '.$res->getName().' 的权限数据复制到领地 '.$dstRes->getName());
				unset($dstRes);
				break;
			case 'rename':
				if(strlen($args[2])<=0 || strlen($args[2])>=60)
				{
					$sender->sendRedMessage('领地名称无效');
					break;
				}
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('领地不存在');
					break;
				}
				if(!$granted && !$res->isOwner($sender))
				{
					$sender->sendRedMessage('你没有权限修改这块领地的名称');
					break;
				}
				if($this->provider->getResidenceByName($args[2])!==null)
				{
					$sender->sendRedMessage('已存在重名领地');
					break;
				}
				$sender->sendGreenMessage('成功将领地 '.$res->getName().' 重命名为 '.$args[2]);
				$res->setName($args[2]);
				break;
			case 'tp':
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendRedMessage('领地不存在');
					break;
				}
				if(!$granted && !$res->isOwner($sender) && !$res->hasPermission($sender,Permissions::PERMISSION_TELEPORT))
				{
					$sender->sendRedMessage('你没有权限传送到这块领地');
					break;
				}
				$sender->teleport($res->getTeleportPos());
				$sender->sendGreenMessage('正在传送到领地 '.$res->getName().' ...');
				break;
			case 'tpset':
				if(($res=$this->provider->getResidenceByPosition($sender))===null)
				{
					$sender->sendRedMessage('当前位置没有领地');
					break;
				}
				if(!$granted && !$res->isOwner($sender))
				{
					$sender->sendRedMessage('你没有权限修改这块领地的传送点');
					break;
				}
				$res->setTeleportPos($sender->getPlayer());
				$sender->sendGreenMessage('领地 '.$res->getName().' 的传送点修改成功');
				break;
			}
		}
		else
		{
			$sender->sendMessage(Utils::getRedString('这个指令只能在游戏中使用'));
		}
		unset($sender,$args);
	}
	
	public function onResidenceAdminCommand($sender,array $args)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(!isset(self::$_RESADMIN_COMMAND_HELP[$args[0]]) && !isset(self::$_RES_COMMAND_HELP[$args[0]]))
		{
			$sender->sendMessage(Utils::getRedString('未知指令,请使用 /resadmin help 查看帮助'));
		}
		else if(isset(self::$_RESADMIN_COMMAND_HELP[$args[0]]) && !isset($args[self::$_RESADMIN_COMMAND_HELP[$args[0]][0]]))
		{
			$sender->sendMessage(Utils::getAquaString('使用方法: '.self::$_RESADMIN_COMMAND_HELP[$args[0]][1]));
		}
		else if(isset(self::$_RES_COMMAND_HELP[$args[0]]) && !isset($args[self::$_RES_COMMAND_HELP[$args[0]][0]]))
		{
			$sender->sendMessage(Utils::getAquaString('使用方法: '.self::$_RES_COMMAND_HELP[$args[0]][1]));
		}
		else
		{
			switch(isset($args[0])?$args[0]:'help')
			{
			case 'list':
				$res=$this->provider->getResidencesByOwner($args[1]);
				if(count($res)<=0)
				{
					$sender->sendMessage(Utils::getYellowString('没有查询到任何领地'));
					break;
				}
				$sender->sendMessage(Utils::makeList('玩家 '.$args[1].' 的领地列表',$res,$args[2],($sender instanceof Player || $sender instanceof PlayerInfo)?5:50,function($val)
				{
					return is_string($val)?$val:(TextFormat::DARK_GREEN.$val->getName().TextFormat::WHITE.' - 世界:'.$val->getLevelName().',大小:'.$val->getSize().' 方块');
				}));
				break;
			case 'listall':
				$res=$this->provider->getAllResidences();
				if(count($res)<=0)
				{
					$sender->sendMessage(Utils::getYellowString('没有查询到任何领地'));
					break;
				}
				$sender->sendMessage(Utils::makeList('服务器领地列表',$res,$args[2],($sender instanceof Player || $sender instanceof PlayerInfo)?5:50,function($val)
				{
					return is_string($val)?$val:(TextFormat::DARK_GREEN.$val->getName().TextFormat::WHITE.' - 世界:'.$val->getLevelName().',大小:'.$val->getSize().' 方块');
				}));
				break;
			case 'removeall':
				$sender->sendMessage(Utils::getGreenString('成功移除玩家 '.TextFormat::AQUA.$args[1].TextFormat::GREEN.' 的所有领地 (操作 '.$this->provider->removeResidencesByOwner($args[1]).' 块领地)'));
				break;
			case 'setowner':
				if(!Utils::validatePlayerName($args[2]=Utils::getPlayerName($args[2])))
				{
					$sender->sendMessage(Utils::getGreenString('无效的用户名'));
					break;
				}
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendMessage(Utils::getGreenString('领地不存在'));
					break;
				}
				$res->setOwner($args[2]);
				$sender->sendMessage(Utils::getGreenString('成功把领地 '.$res->getName().' 的主人设置为 '.$args[2]));
				break;
			case 'server':
				if(($res=$this->provider->getResidenceByName($args[1]))===null)
				{
					$sender->sendMessage(Utils::getRedString('领地不存在'));
					break;
				}
				$res->setOwner('');
				$sender->sendMessage(Utils::getGreenString('成功把领地 '.$res->getName().' 设为服务器领地'));
				break;
			
			case 'reload':
				$this->reload();
				$sender->sendMessage(Utils::getGreenString('重载完成'));
				break;
			case 'parse':
				if(!($sender instanceof \pocketmine\command\ConsoleCommandSender))
				{
					$sender->sendMessage(Utils::getRedString('这个指令只能在服务器后台使用'));
					break;
				}
				$sender->sendMessage(Utils::getAquaString('开始转换领地数据'));
				$land=new Config($this->getDataFolder().'../EconomyLand/Land.yml',Config::YAML,array());
				$cou=0;
				foreach($land->getAll() as $l)
				{
					$this->provider->addResidence(new Vector3($l['startX'],0,$l['startZ']),new Vector3($l['endX'],256,$l['endZ']),$l['owner'],'parse_'.$l['ID'],$l['level']);
					$cou++;
					unset($l);
				}
				$sender->sendMessage(Utils::getGreenString('数据转换完成,共处理 '.$cou.' 块领地'));
				break;
			
			case 'help':
				$sender->sendMessage(Utils::makeList('FResidence Admin Help',self::$_RESADMIN_COMMAND_HELP,$args[1],($sender instanceof Player || $sender instanceof PlayerInfo)?5:50));
				break;
			
			default:
				if(isset(self::$_RES_COMMAND_HELP[$args[0]]))
				{
					$this->onResidenceCommand($sender,$args,true);
				}
				break;
			}
		}
		unset($sender,$args);
	}
	
	public function onPlayerInteract(PlayerInteractEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if($event->getAction()==PlayerInteractEvent::RIGHT_CLICK_BLOCK)
		{
			$player=$this->getPlayer($event);
			if(($res=$this->provider->getResidenceByPosition($event->getBlock()))!==null)
			{
				if(!$player->isOp() && !$res->isOwner($player) && ($this->isProtectBlock($event->getBlock()) || $this->isBlockedItem($event->getItem())) && !$res->hasPermission($player,Permissions::PERMISSION_USE))
				{
					$player->sendColorMessage($res->getMessage(Messages::INDEX_PERMISSION));
					$event->setCancelled();
				}
			}
			else
			{
				if($event->getItem()->getId()==ConfigProvider::SelectItem())
				{
					$player->setPos1($event->getBlock())->sendYellowMessage('已设置第一个点')->validateSelect(true);
					$event->setCancelled();
				}
				else if(!$player->isOp() && in_array(strtolower($event->getBlock()->getLevel()->getFolderName()),ConfigProvider::WhiteListWorld()))
				{
					$player->sendYellowMessage('抱歉,当前世界需要先圈地才能进行建筑');
					$event->setCancelled();
				}
			}
			unset($player,$res);
		}
		unset($event);
	}
	
	public function onPlayerMove(\pocketmine\event\player\PlayerMoveEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		$player=$this->getPlayer($event);
		$player->checkMoveTick--;
		$player->movementLog[]=$event->getFrom();
		if(count($player->movementLog)>$player->checkMoveTick)
		{
			array_shift($player->movementLog);
		}
		if($player->checkMoveTick>0)
		{
			unset($event);
			return;
		}
		$player->checkMoveTick=ConfigProvider::CheckMoveTick();
		if(($res=$this->provider->getResidenceByPosition($event->getTo()))!==null)
		{
			if(!$res->isOwner($player) && !$player->isOp() && !$res->hasPermission($player,Permissions::PERMISSION_MOVE))
			{
				$player->teleport($player->movementLog[0]);
				if(($msg=$res->getMessage(Messages::INDEX_PERMISSION))!='')
				{
					$player->sendTip($msg);
				}
				unset($msg);
			}
			else if(!$player->inResidence() || $player->getResidence()->getId()!=$res->getId())
			{
				$player->sendColorMessage(str_replace(array('%name','%owner'),array(
					$res->getName(),
					$res->getOwner()),$res->getMessage(Messages::INDEX_ENTER)));
				$player->setResidence($res);
			}
		}
		else
		{
			if($player->inResidence())
			{
				$res=$player->getResidence();
				$player->sendColorMessage(str_replace(array('%name','%owner'),array(
					$res->getName(),
					$res->getOwner()),$res->getMessage(Messages::INDEX_LEAVE)));
				$player->setResidence(null);
			}
		}
		unset($event,$res,$player);
	}
	
	public function onBlockPlace(\pocketmine\event\block\BlockPlaceEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		$player=$this->getPlayer($event->getPlayer());
		if(($res=$this->provider->getResidenceByPosition($event->getBlock()))!==null)
		{
			if(!$res->isOwner($player) && !$res->hasPermission($player,Permissions::PERMISSION_BUILD) && !$player->isOp())
			{
				$player->sendColorMessage($res->getMessage(Messages::INDEX_PERMISSION));
				$event->setCancelled();
			}
			unset($player);
		}
		else
		{
			if(!$player->isOp() && in_array(strtolower($event->getBlock()->getLevel()->getFolderName()),ConfigProvider::WhiteListWorld()))
			{
				$player->sendYellowMessage('抱歉,当前世界需要先圈地才能进行建筑');
				$event->setCancelled();
			}
		}
		unset($event,$res,$player);
	}
	
	public function onBlockBreak(\pocketmine\event\block\BlockBreakEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		$player=$this->getPlayer($event->getPlayer());
		if(($res=$this->provider->getResidenceByPosition($event->getBlock()))!==null)
		{
			if(!$res->isOwner($player) && !$res->hasPermission($player,Permissions::PERMISSION_BUILD) && !$player->isOp())
			{
				$player->sendColorMessage($res->getMessage(Messages::INDEX_PERMISSION));
				$event->setCancelled();
			}
		}
		else
		{
			if($event->getItem()->getId()==ConfigProvider::SelectItem())
			{
				$player->setPos2($event->getBlock())->sendGreenMessage('已设置第二个点')->validateSelect(true);
				$event->setCancelled();
			}
			else if(!$player->isOp() && in_array(strtolower($event->getBlock()->getLevel()->getFolderName()),ConfigProvider::WhiteListWorld()))
			{
				$player->sendYellowMessage('抱歉,当前世界需要先圈地才能进行建筑');
				$event->setCancelled();
			}
		}
		unset($event,$res,$player);
	}
	
	public function onBlockUpdate(\pocketmine\event\block\BlockUpdateEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		$block=$event->getBlock();
		if($block->getId()>=8 && $block->getId()<=11 && ($res=$this->provider->getResidenceByPosition($block))!==null && !$res->getPermission(Permissions::PERMISSION_FLOW))
		{
			$event->setCancelled();
		}
		unset($event,$block,$res);
	}
	
	public function onLevelLoad(\pocketmine\event\level\LevelLoadEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		$this->provider->reload();
		unset($event);
	}
	
	public function onEntityDamage(\pocketmine\event\entity\EntityDamageEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if(($res=$this->provider->getResidenceByPosition($event->getEntity()))!==null && !$res->getPermission(Permissions::PERMISSION_DAMAGE))
		{
			$event->setCancelled();
		}
		else if($event instanceof \pocketmine\event\entity\EntityDamageByEntityEvent && $event->getDamager() instanceof Player && $event->getEntity() instanceof Player)
		{
			if(($res!==null && !$res->getPermission(Permissions::PERMISSION_PVP)) || 
				(($res=$this->provider->getResidenceByPosition($event->getDamager()))!==null && !$res->getPermission(Permissions::PERMISSION_PVP)))
			{
				if(($msg=$res->getMessage(Messages::INDEX_PERMISSION))!='')
				{
					$event->getDamager()->sendMessage($msg);
				}
				unset($msg);
				$event->setCancelled();
			}
		}
		unset($res,$event);
	}
	
	public function onPlayerJoin(\pocketmine\event\player\PlayerJoinEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		$this->players[$event->getPlayer()->getId()]=new PlayerInfo($event->getPlayer());
		unset($event);
	}
	
	public function onPlayerQuit(\pocketmine\event\player\PlayerQuitEvent $event)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		unset($this->players[$event->getPlayer()->getId()],$event);
	}
	
	public function isProtectBlock(Block $block)
	{
		ZXDA::isTrialVersion();
		if(!ZXDA::isVerified())
		{
			return null;
		}
		if($block instanceof Blocks\Door || 
			$block instanceof Blocks\Chest || 
			$block instanceof Blocks\Trapdoor || 
			$block instanceof Blocks\FenceGate || 
			$block instanceof Blocks\WoodenButton || 
			in_array($block->getId(),self::$protectedBlocks))
		{
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
