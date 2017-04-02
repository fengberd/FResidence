<?php
namespace FResidence;

class PlayerInfo implements \pocketmine\IPlayer
{
	public $p1=false;
	public $p2=false;
	public $checkMoveTick=10;
	public $movementLog=array();
	
	private $player=null;
	private $currentResidence=null;
	
	public function __construct($player)
	{
		$this->player=$player;
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
	}
	
	public function validateSelect($moneyPerBlock,$moneyName)
	{
		if($this->isSelectFinish())
		{
			if($this->p1->getLevel()->getFolderName()!=$this->p2->getLevel()->getFolderName())
			{
				$this->sendMessage('[FResidence] '.TextFormat::RED.'请在同一个世界选点圈地');
			}
			else
			{
				$this->sendMessage('[FResidence] '.TextFormat::YELLOW.'选区已设定,需要 '.($moneyPerBlock*Utils::calucateSize($this->p1,$this->p2)).' '.$moneyName.'来创建领地');
			}
		}
	}
	
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
	
	public function setPos1($pos)
	{
		$this->p1=$pos;
		unset($pos);
	}
	
	public function setPos2($pos)
	{
		$this->p2=$pos;
		unset($pos);
	}
	
	public function __call($name,$args)
	{
		return $this->player->$name(...$args);
	}
	/*
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
		return $this->player->isBanned($banned);
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
		return $this->player;
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
	*/
}
