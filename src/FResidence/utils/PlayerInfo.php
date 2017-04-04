<?php
namespace FResidence\utils;

use pocketmine\utils\TextFormat;

use FResidence\provider\ConfigProvider;

class PlayerInfo implements \pocketmine\IPlayer
{
	public $checkMoveTick=10;
	public $movementLog=array();
	
	private $pos1=null;
	private $pos2=null;
	private $player=null;
	private $confirmQueue=array();
	private $currentResidence=null;
	
	public function __construct($player)
	{
		$this->player=$player;
		unset($player);
	}
	
	public function inResidence()
	{
		return $this->currentResidence!==null;
	}
	
	public function getResidence()
	{
		return $this->currentResidence;
	}
	
	public function setResidence($res)
	{
		$this->currentResidence=$res;
		unset($res);
		return $this;
	}
	
	public function getConfirm($type,$code)
	{
		$code=$type.$code;
		if(!isset($this->confirmQueue[$code]))
		{
			return null;
		}
		$result=$this->confirmQueue[$code];
		unset($this->confirmQueue[$code],$type,$code);
		if(array_shift($result)<time())
		{
			$result=null;
		}
		return $result;
	}
	
	public function addConfirm($type,$code,$action,array $args=array(),$expires=60)
	{
		$this->confirmQueue[$type.$code]=array(time()+$expires,$action,$args);
		unset($type,$code,$action,$args,$expires);
		return $this;
	}
	
	public function sendRedMessage($msg)
	{
		return $this->sendColorMessage($msg,TextFormat::RED);
	}
	
	public function sendAquaMessage($msg)
	{
		return $this->sendColorMessage($msg,TextFormat::AQUA);
	}
	
	public function sendGreenMessage($msg)
	{
		return $this->sendColorMessage($msg,TextFormat::GREEN);
	}
	
	public function sendYellowMessage($msg)
	{
		return $this->sendColorMessage($msg,TextFormat::YELLOW);
	}
	
	public function sendColorMessage($msg,$color=TextFormat::WHITE)
	{
		if($msg!='')
		{
			$this->sendMessage(Utils::getColoredString($msg,$color));
		}
		unset($msg,$color);
		return $this;
	}
	
	public function validateSelect($notify=false)
	{
		$valid=-1;
		if($this->isSelectFinish())
		{
			$valid=$this->pos1->getLevel()->getFolderName()==$this->pos2->getLevel()->getFolderName()?Utils::calculateSize($this->pos1,$this->pos2):-1;
			if($notify)
			{
				if($valid>=2*2*2)
				{
					$this->sendYellowMessage('选区已设定,需要 '.(ConfigProvider::MoneyPerBlock()*$valid).' '.ConfigProvider::MoneyName().'来创建领地');
				}
				else
				{
					$this->sendRedMessage('选区无效,请确保你选择的两个点在同一个世界内并且选区大于2x2x2');
				}
			}
		}
		unset($notify);
		return $valid;
	}
	
	public function isSelectFinish()
	{
		return ($this->pos1!==null && $this->pos2!==null);
	}
	
	public function getPos1()
	{
		return $this->pos1;
	}
	
	public function setPos1($pos)
	{
		if($pos!==null)
		{
			$pos->x=intval($pos->getX());
			$pos->z=intval($pos->getZ());
			$pos->y=min(max($pos->getY(),0),256);
		}
		$this->pos1=$pos;
		unset($pos);
		return $this;
	}
	
	public function getPos2()
	{
		return $this->pos2;
	}
	
	public function setPos2($pos)
	{
		if($pos!==null)
		{
			$pos->x=intval($pos->getX());
			$pos->z=intval($pos->getZ());
			$pos->y=min(max($pos->getY(),0),256);
		}
		$this->pos2=$pos;
		unset($pos);
		return $this;
	}
	
	public function __call($name,$args)
	{
		return $this->player->$name(...$args);
	}
	
	public function isOnline()
	{
		return $this->player->isOnline();
	}
	
	public function getName()
	{
		return $this->player->getName();
	}
	
	public function isBanned()
	{
		return $this->player->isBanned();
	}
	
	public function setBanned($banned)
	{
		return $this->player->setBanned($banned);
	}
	
	public function isWhitelisted()
	{
		return $this->player->isWhitelisted();
	}
	
	public function setWhitelisted($value)
	{
		return $this->player->setWhitelisted($value);
	}
	
	public function getPlayer()
	{
		return $this->player->getPlayer();
	}
	
	public function getFirstPlayed()
	{
		return $this->player->getFirstPlayed();
	}
	
	public function getLastPlayed()
	{
		return $this->player->getLastPlayed();
	}
	
	public function hasPlayedBefore()
	{
		return $this->player->hasPlayedBefore();
	}
	
	public function isOp()
	{
		return $this->player->isOp();
	}
	
	public function setOp($value)
	{
		return $this->player->setOp($value);
	}
}
