<?php
namespace FResidence\Provider;

use FResidence\Main;

use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\level\Level;

class Residence
{
	private $provider;
	private $__rid=-1;
	private $data;
	
	public function __construct(DataProvider $provider,$ID,array $data)//int $ID,array $start,array $end,$name,$owner,$metadata)
	{
		$this->__rid=$ID;
		$this->provider=$provider;
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
		return $this->data['owner'];
	}
	
	public function setOwner($owner)
	{
		if($owner instanceof Player)
		{
			$owner=$owner->getName();
		}
		$this->data['owner']=$owner;
		$this->save();
	}
	
	public function getName()
	{
		return $this->data['name'];
	}
	
	public function setName($name)
	{
		$this->data['name']=$name;
		$this->save();
	}
	
	public function getMessage($index,$default='数据读取失败')
	{
		return isset($this->data['metadata']['message'][$index])?"§e".$this->data['metadata']['message'][$index]:$default;
	}
	
	public function setMessage($index,$message)
	{
		$this->data['metadata']['message'][$index]=$message;
		$this->save();
	}
	
	public function getPermission($index,$default=false)
	{
		return isset($this->data['metadata']['permission'][$index])?($this->data['metadata']['permission'][$index]=='true'):$default;
	}
	
	public function setPermission($index,$permission)
	{
		$this->data['metadata']['permission'][$index]=$permission;
		$this->save();
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
			return $this->removePlayerPermission($player,$index);
		}
		$this->data['metadata']['playerpermission'][$player][$index]=$permission;
		$this->save();
		return true;
	}
	
	public function getPlayerPermission($player,$index,$default=false)
	{
		if($player instanceof Player)
		{
			$player=$player->getName();
		}
		$player=strtolower($player);
		return isset($this->data['metadata']['playerpermission'][$player][$index])?$this->data['metadata']['playerpermission'][$player][$index]:$this->getPermission($index,$default);
	}
	
	public function removePlayerPermission($player,$index)
	{
		if($player instanceof Player)
		{
			$player=$player->getName();
		}
		$player=strtolower($player);
		unset($this->data['metadata']['playerpermission'][$player][$index]);
		$this->save();
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
