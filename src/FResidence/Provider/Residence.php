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
	
	public function getStart()
	{
		return $this->data['start'];
	}
	
	public function getEnd()
	{
		return $this->data['end'];
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
		return isset($this->data['metadata']['message'][$index])?$this->data['metadata']['message'][$index]:$default;
	}
	
	public function setMessage($index,$message)
	{
		$this->data['metadata']['message'][$index]=$message;
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
