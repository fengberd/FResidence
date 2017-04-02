<?php
namespace FResidence\utils;

use pocketmine\math\Vector3;
use pocketmine\level\Position;

class Residence
{
	private $_provider=null;
	
	private $id=-1;
	
	private $pos1=null;
	private $pos2=null;
	private $teleport=null;
	
	private $name='';
	private $owner='';
	private $level='';
	
	private $message=null;
	private $permission=null;
	
	public function __construct(DataProvider $provider,int $id,string $name,$owner,Position $pos1,Position $pos2)
	{
		$this->id=$id;
		$this->name=$name;
		$this->owner=Utils::getPlayerName($owner);
		$this->level=$pos1->getLevel()->getFolderName();
		
		$this->pos1=$pos1;
		$this->pos2=$pos2;
		$this->teleport=$pos1;
		
		$this->message=new Messages();
		$this->permission=new Permissions();
		
		$this->_provider=$provider;
	}
	
	public function __construct(DataProvider $provider,int $id,array $data)
	{
		if(!($level=\pocketmine\Server::getInstance()->getLevelByName($data['level'])) instanceof \pocketmine\level\Level)
		{
			throw new \Exception('领地所在世界不存在,已被删除');
		}
		$this->id=$id;
		$this->name=$data['name'];
		$this->owner=Utils::getPlayerName($data['owner']);
		$this->level=$level->getFolderName();
		
		$this->pos1=Utils::parsePosition($data['positions']['pos1'],$level);
		$this->pos2=Utils::parsePosition($data['positions']['pos2'],$level);
		$this->teleport=Utils::parsePosition($data['positions']['teleport'],$level);
		
		$this->message=new Messages($data['messages']);
		$this->permission=new Permissions($data['permissions']);
		
		$this->_provider=$provider;
	}
	
	public function getRawData()
	{
		return array(
			'name'=>$this->name,
			'owner'=>$this->owner,
			'level'=>$this->level,
			'messages'=>$this->messages->getRawData(),
			'positions'=>array(
				'pos1'=>Utils::encodeVector3($this->pos1),
				'pos2'=>Utils::encodeVector3($this->pos2),
				'teleport'=>Utils::encodeVector3($this->teleport)),
			'permissions'=>$this->permission->getRawData());
	}
	
	public function save()
	{
		$this->_provider->save();
	}
	
	public function getID()
	{
		return $this->id;
	}
	
	public function getPos1()
	{
		return $this->pos1;
	}
	
	public function getPos2()
	{
		return $this->pos2;
	}
	
	public function getSize()
	{
		return Utils::calucateSize($this->pos1,$this->pos2);
	}
	
	public function getOwner()
	{
		return $this->owner;
	}
	
	public function setOwner($owner)
	{
		$this->owner=Utils::getPlayerName($owner);
		$this->save();
		unset($owner);
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	public function setName(string $name)
	{
		$this->name=$name;
		$this->save();
		unset($name);
	}
	
	public function getMessage(string $index)
	{
		return $this->message->getMessage($index);
	}
	
	public function getMessages()
	{
		return $this->message;
	}
	
	public function hasPermission($player,string $index)
	{
		return $this->permission->hasPermission($player,$index);
	}
	
	public function getPermission(string $index)
	{
		return $this->permission->getPermission($index);
	}
	
	public function getPermissions()
	{
		return $this->permission;
	}
	
	public function getTeleportPos()
	{
		return $this->teleport;
	}
	
	public function setTeleportPos(Position $pos)
	{
		if($pos!=null && $pos->getLevel()->getName()!=$this->pos1->getLevel()->getName())
		{
			return false;
		}
		$this->teleport=$pos;
		$this->save();
		unset($pos);
		return true;
	}
	
	public function inResidence(Vector3 $pos,$level=null)
	{
		if($level instanceof \pocketmine\level\Level)
		{
			$level=$level->getFolderName();
		}
		if($level!==null && $level!=$this->level)
		{
			unset($pos,$level);
			return false;
		}
		$x=$pos->x;
		$y=$pos->y;
		$z=$pos->z;
		if((($x<=$this->pos1->x && $x>=$this->pos2->x) || ($x>=$this->pos1->x && $x<=$this->pos2->x)) && 
			(($y<=$this->pos1->y && $y>=$this->pos2->y) || ($y>=$this->pos1->y && $y<=$this->pos2->y)) && 
			(($z<=$this->pos1->z && $z>=$this->pos2->z) || ($z>=$this->pos1->z && $z<=$this->pos2->z)))
		{
			unset($x,$y,$z,$pos,$level);
			return true;
		}
		unset($x,$y,$z,$pos,$level);
		return false;
	}
}
