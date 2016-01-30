<?php
namespace FResidence\Provider;

use FResidence\Main;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\level\Position;

class Residence
{
	public static $DefaultPermission=array(
		'move'=>'true',
		'build'=>'false',
		'use'=>'false',
		'pvp'=>'true',
		'damage'=>'true',
		'tp'=>'false',
		'flow'=>'true');
	private $provider;
	private $__rid=-1;
	private $data;
	
	public function __construct(DataProvider $provider,$ID,array $data)
	{
		$this->__rid=$ID;
		$this->provider=$provider;
		$data['metadata']['permission']=array_merge(self::$DefaultPermission,$data['metadata']['permission']);
		$this->data=$data;
	}
	
	public function save()
	{
		$this->provider->save();
	}
	
	public function getData()
	{
		return $this->data;
	}
	
	public function getStart()
	{
		return $this->data['start'];
	}
	
	public function getEnd()
	{
		return $this->data['end'];
	}
	
	public function getSize()
	{
		$select1=$this->data['start'];
		$select2=$this->data['end'];
		return abs($select1['x']-$select2['x'])*abs($select1['y']-$select2['y'])*abs($select1['z']-$select2['z']);
	}
	
	public function getLevel()
	{
		return $this->data['level'];
	}
	
	public function getOwner()
	{
		return strtolower($this->data['owner']);
	}
	
	public function setOwner($owner)
	{
		if($owner instanceof Player)
		{
			$owner=$owner->getName();
		}
		$this->data['owner']=$owner;
		$this->save();
		unset($owner);
	}
	
	public function getName()
	{
		return $this->data['name'];
	}
	
	public function setName($name)
	{
		$this->data['name']=$name;
		$this->save();
		unset($name);
	}
	
	public function getMessage($index,$default='数据读取失败')
	{
		return isset($this->data['metadata']['message'][$index])?'§e'.$this->data['metadata']['message'][$index]:$default;
	}
	
	public function setMessage($index,$message)
	{
		$this->data['metadata']['message'][$index]=$message;
		$this->save();
		unset($index,$message);
	}
	
	public function resetPermission()
	{
		$this->data['metadata']['permission']=Residence::$DefaultPermission;
		$this->data['metadata']['playerpermission']=array();
		$this->save();
	}
	
	public function getAllPermission($default=false)
	{
		return isset($this->data['metadata']['permission'])?($this->data['metadata']['permission']):$default;
	}
	
	public function setAllPermission($data)
	{
		if(!is_array($data))
		{
			return false;
		}
		$this->data['metadata']['permission']=$data;
		$this->save();
		unset($data);
		return true;
	}
	
	public function getPermission($index,$default=false)
	{
		return isset($this->data['metadata']['permission'][$index])?($this->data['metadata']['permission'][$index]=='true'):$default;
	}
	
	public function setPermission($index,$permission)
	{
		$this->data['metadata']['permission'][$index]=$permission;
		$this->save();
		unset($index,$permission);
	}
	
	public function setPlayerPermission($player,$index,$permission)
	{
		if($player instanceof Player)
		{
			$player=$player->getName();
		}
		$player=strtolower($player);
		if($permission=='remove')
		{
			$this->removePlayerPermission($player,$index);
		}
		else
		{
			$this->data['metadata']['playerpermission'][$player][$index]=$permission;
			$this->save();
		}
		unset($player,$index,$permission);
	}
	
	public function getPlayerPermission($player,$index,$default=false)
	{
		if($player instanceof Player)
		{
			$player=$player->getName();
		}
		$player=strtolower($player);
		return isset($this->data['metadata']['playerpermission'][$player][$index])?($this->data['metadata']['playerpermission'][$player][$index]=='true'):$this->getPermission($index,$default);
	}
	
	public function removePlayerPermission($player,$index)
	{
		if($player instanceof Player)
		{
			$player=$player->getName();
		}
		$player=strtolower($player);
		unset($this->data['metadata']['playerpermission'][$player][$index],$player,$index);
		$this->save();
	}
	
	public function getTeleportPos()
	{
		if(!($level=Server::getInstance()->getLevelByName($this->data['level'])) instanceof Level)
		{
			unset($level);
			return false;
		}
		return new Position($this->data['metadata']['teleport']['x'],$this->data['metadata']['teleport']['y'],$this->data['metadata']['teleport']['z'],$level);
	}
	
	public function setTeleportPos(Position $pos)
	{
		$this->data['metadata']['teleport']['x']=$pos->x;
		$this->data['metadata']['teleport']['y']=$pos->y;
		$this->data['metadata']['teleport']['z']=$pos->z;
		$this->save();
		unset($pos);
	}
	
	public function inResidence(Vector3 $pos,$level='')
	{
		if($level instanceof Level)
		{
			$level=$level->getFolderName();
		}
		if($level!=='' && $level!==$this->getLevel())
		{
			unset($pos,$level);
			return false;
		}
		$x=$pos->x;
		$y=$pos->y;
		$z=$pos->z;
		$start=$this->data['start'];
		$end=$this->data['end'];
		if((($x<=$start['x'] && $x>=$end['x']) || ($x>=$start['x'] && $x<=$end['x'])) && (($y<=$start['y'] && $y>=$end['y']) || ($y>=$start['y'] && $y<=$end['y'])) && (($z<=$start['z'] && $z>=$end['z']) || ($z>=$start['z'] && $z<=$end['z'])))
		{
			unset($x,$y,$z,$start,$end,$pos,$level);
			return true;
		}
		unset($x,$y,$z,$start,$end,$pos,$level);
		return false;
	}
}
