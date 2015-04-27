<?php
namespace FResidence\Provider;

use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\level\Position;

use FResidence\Main;

class YAMLProvider implements DataProvider
{
	private $config;
	private $Residences=array();
	private $main;
	
	public function __construct(Main $main)
	{
		$this->main=$main;
		$this->reload();
		unset($main);
	}
	
	public function addResidence($startpos,$endpos,$owner,$name)
	{
		if($owner instanceof Player)
		{
			$owner=$owner->getName();
		}
		$owner=strtolower($owner);
		$this->Residences[]=new Residence($this,count($this->Residences),array(
			'name'=>$name,
			'start'=>array(
				'x'=>(int)$startpos->getX(),
				'y'=>(int)$startpos->getY(),
				'z'=>(int)$startpos->getZ()),
			'end'=>array(
				'x'=>(int)$endpos->getX(),
				'y'=>(int)$endpos->getY(),
				'z'=>(int)$endpos->getZ()),
			'level'=>$startpos->getLevel()->getFolderName(),
			'owner'=>$owner,
			'metadata'=>array(
				'permission'=>array(
					'move'=>'true',
					'build'=>'false',
					'use'=>'false'),
				'message'=>array(
					'enter'=>'欢迎来到 %name ,这里是 %owner 的领地',
					'leave'=>'你离开了 %name',
					'permission'=>'你没有权限使用这块领地'))));
		$this->save();
		unset($startpos,$endpos,$owner,$name);
		return count($this->Residences)-1;
	}
	
	public function getAllResidences()
	{
		return $this->Residences;
	}
	
	public function getResidence($resid)
	{
		return isset($this->Residences[$resid])?$this->Residences[$resid]:false;
	}
	
	public function removeResidence($resid)
	{
		if(!isset($this->Residences[$resid]))
		{
			return false;
		}
		unset($this->Residences[$resid],$resid);
		$this->save();
		return true;
	}
	
	public function removeResidencesByOwner($owner)
	{
		if($owner instanceof Player)
		{
			$owner=$owner->getName();
		}
		$owner=strtolower($owner);
		$cou=0;
		foreach($this->Residences as $key=>$res)
		{
			if($res->getOwner()===$owner)
			{
				unset($this->Residences[$key]);
				$cou++;
			}
			unset($key,$res);
		}
		$this->save();
		unset($res,$pos,$key);
		return $cou;
	}
	
	public function queryResidenceByName($name)
	{
		foreach($this->Residences as $key=>$res)
		{
			if($res->getName()===$name)
			{
				unset($res,$pos,$name);
				return $key;
			}
			unset($key,$res);
		}
		unset($name);
		return false;
	}
	
	public function queryResidencesByOwner($owner)
	{
		if($owner instanceof Player)
		{
			$owner=$owner->getName();
		}
		$owner=strtolower($owner);
		$ret=array();
		foreach($this->Residences as $key=>$res)
		{
			if($res->getOwner()===$owner)
			{
				$ret[$key]=$res;
			}
			unset($key,$res);
		}
		unset($owner);
		return $ret;
	}
	
	public function queryResidenceByPosition($pos,$level='')
	{
		if($pos instanceof Position)
		{
			$level=$pos->getLevel()->getFolderName();
		}
		foreach($this->Residences as $key=>$res)
		{
			if($res->inResidence($pos,$level))
			{
				unset($pos,$level,$res);
				return $key;
			}
		}
		unset($pos,$level,$key,$res);
		return false;
	}
	
	public function getConfig()
	{
		return $this->config;
	}
	
	public function save()
	{
		$data=array();
		foreach($this->Residences as $res)
		{
			$data[]=$res->getData();
			unset($res);
		}
		$this->config->set('Residences',$data);
		$this->config->save();
	}
	
	public function close($save=true)
	{
		if($save)
		{
			$this->save();
		}
		unset($save,$this);
	}
	
	public function reload($save=false)
	{
		if($save)
		{
			$this->save();
		}
		unset($save);
		@mkdir($this->main->getDataFolder());
		$this->config=new Config($this->main->getDataFolder().'residence.yml',Config::YAML,array(
			'dataVersion'=>1,
			'Residences'=>array()));
		foreach($this->config->get('Residences') as $arr)
		{
			$this->Residences[]=new Residence($this,count($this->Residences),$arr);
			unset($arr);
		}
	}
}
?>
