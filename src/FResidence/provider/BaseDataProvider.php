<?php
namespace FResidence\provider;

use FResidence\utils\Utils;
use FResidence\utils\Residence;

abstract class BaseDataProvider implements IDataProvider
{
	protected $main=null;
	protected $residences=array();
	
	protected $failed=array();
	
	public function __construct(\FResidence\Main $main)
	{
		$this->main=$main;
		$this->reload();
		unset($main);
	}
	
	public function addResidence($pos1,$pos2,$owner,$name)
	{
		$this->residences[]=new Residence($this,$id=count($this->residences),$name,$owner,$pos1,$pos2);
		$this->save();
		unset($pos1,$pos2,$owner,$name);
		return $id;
	}
	
	public function getResidence($id)
	{
		return isset($this->residences[$id])?$this->residences[$id]:null;
	}
	
	public function getResidenceByName($name)
	{
		foreach($this->residences as $key=>$res)
		{
			if($res->getName()==$name)
			{
				unset($key,$name);
				return $res;
			}
			unset($key,$res);
		}
		unset($name);
		return null;
	}
	
	public function getResidenceByPosition($pos)
	{
		foreach($this->residences as $key=>$res)
		{
			if($res->inResidence($pos))
			{
				unset($pos,$key);
				return $res;
			}
		}
		unset($pos,$key,$res);
		return null;
	}
	
	public function getAllResidences()
	{
		return $this->residences;
	}
	
	public function getResidencesByOwner($owner)
	{
		$owner=Utils::getPlayerName($owner);
		$result=array();
		foreach($this->residences as $key=>$res)
		{
			if($res->getOwner()==$owner)
			{
				$result[$key]=$res;
			}
			unset($key,$res);
		}
		unset($owner);
		return $result;
	}
	
	public function removeResidence($id)
	{
		if($id instanceof Residence)
		{
			$id=$id->getId();
		}
		if(!isset($this->residences[$id]))
		{
			return false;
		}
		unset($this->residences[$id],$id);
		$this->save();
		return true;
	}
	
	public function removeResidencesByOwner($owner)
	{
		$owner=Utils::getPlayerName($owner);
		$count=0;
		foreach($this->residences as $key=>$res)
		{
			if($res->getOwner()==$owner)
			{
				unset($this->residences[$key]);
				$count++;
			}
			unset($key,$res);
		}
		$this->save();
		unset($res,$key);
		return $count;
	}
	
	public abstract function save();
	public abstract function close($save=true);
	public abstract function reload($save=false);
	
	public abstract function getName();
}
