<?php
namespace FResidence\utils;

use pocketmine\level\Position;

class Residence
{
	private $_provider=null;
	
	private $id=-1;
	
	private $pos1=null;
	private $pos2=null;
	private $teleport=null;
	
	private $minX=0;
	private $minY=0;
	private $minZ=0;
	
	private $maxX=0;
	private $maxY=0;
	private $maxZ=0;
	
	private $name='';
	private $owner='';
	private $level='';
	
	private $message=null;
	private $permission=null;
	
	public function __construct(\FResidence\provider\IDataProvider $provider,int $id,...$data)
	{
		if(is_array($data[0]))
		{
			$data=$data[0];
			if(!($level=\pocketmine\Server::getInstance()->getLevelByName($data['level'])) instanceof \pocketmine\level\Level)
			{
				throw new \FResidence\exception\ResidenceInstantiationException('未找到领地所在世界,如果世界确实不存在领地会被自动删除,否则可以忽略此提示');
			}
			$this->name=$data['name'];
			$this->owner=Utils::getPlayerName($data['owner']);
			$this->level=strtolower($level->getFolderName());
			
			$this->pos1=Utils::parsePosition($data['positions']['pos1'],$level);
			$this->pos2=Utils::parsePosition($data['positions']['pos2'],$level);
			$this->teleport=Utils::parsePosition($data['positions']['teleport'],$level);
			
			$this->message=new Messages($data['messages'],$this);
			$this->permission=new Permissions($data['permissions'],$this);
		}
		else
		{
			$this->name=$data[0];
			$this->owner=Utils::getPlayerName($data[1]);
			$this->level=strtolower($data[2]->getLevel()->getFolderName());
			
			$this->pos1=$data[2];
			$this->pos2=$data[3];
			$this->teleport=$data[2];
			
			$this->message=new Messages($this);
			$this->permission=new Permissions($this);
		}
		$this->id=$id;
		$this->_provider=$provider;
		$this->calculateBoundary();
	}
	
	public function getRawData()
	{
		return array(
			'name'=>$this->name,
			'owner'=>$this->owner,
			'level'=>$this->level,
			'messages'=>$this->message->getRawData(),
			'positions'=>array(
				'pos1'=>Utils::encodeVector3($this->pos1),
				'pos2'=>Utils::encodeVector3($this->pos2),
				'teleport'=>Utils::encodeVector3($this->teleport)),
			'permissions'=>$this->permission->getRawData());
	}
	
	public function save()
	{
		$this->_provider->save();
		return $this;
	}
	
	public function getId()
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
		return Utils::calculateSize($this->pos1,$this->pos2);
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
		return $this;
	}
	
	public function isOwner($owner)
	{
		return $this->owner==Utils::getPlayerName($owner);
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
		return $this;
	}
	
	public function getMessage(string $index)
	{
		return $this->message->getMessage($index);
	}
	
	public function getMessages()
	{
		return $this->message;
	}
	
	public function getLevelName()
	{
		return $this->level;
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
	
	public function setPermissions(Permissions $permission)
	{
		$this->permission=$permission;
		return $this;
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
	
	public function calculateBoundary()
	{
		$this->maxX=max($this->pos1->x,$this->pos2->x);
		$this->maxY=max($this->pos1->y,$this->pos2->y);
		$this->maxZ=max($this->pos1->z,$this->pos2->z);
		$this->minX=min($this->pos1->x,$this->pos2->x);
		$this->minY=min($this->pos1->y,$this->pos2->y);
		$this->minZ=min($this->pos1->z,$this->pos2->z);
		return $this;
	}
	
	public function inResidence($pos)
	{
		if(strtolower($pos->getLevel()->getFolderName())!=$this->level)
		{
			unset($pos);
			return false;
		}
		$x=$pos->getX();
		$y=$pos->getY();
		$z=$pos->getZ();
		$result=$this->minX<=$x && $x<=$this->maxX && 
			$this->minY<=$y && $y<=$this->maxY && 
			$this->minZ<=$z && $z<=$this->maxZ;
		unset($x,$y,$z,$pos);
		return $result;
	}
}
