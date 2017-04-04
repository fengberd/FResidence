<?php
namespace FResidence\provider;

use pocketmine\utils\Config;

use FResidence\utils\Utils;
use FResidence\utils\Residence;

class YamlDataProvider implements DataProvider
{
	private $main=null;
	private $config=null;
	private $residences=array();
	
	private $failed=array();
	
	public function __construct(\FResidence\Main $main)
	{
		$this->main=$main;
		$this->reload();
		unset($main);
	}
	
	public function getConfig()
	{
		return $this->config;
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
	
	public function getResidenceByPosition($pos,$level='')
	{
		$level=$pos->getLevel()->getFolderName();
		foreach($this->residences as $key=>$res)
		{
			if($res->inResidence($pos))
			{
				unset($pos,$level,$key);
				return $res;
			}
		}
		unset($pos,$level,$key,$res);
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
	
	public function save()
	{
		$data=array();
		foreach($this->residences as $res)
		{
			$data[]=$res->getRawData();
			unset($res);
		}
		$data=array_merge($data,$this->failed);
		$this->config->setAll(array(
			'DataVersion'=>Utils::CONFIG_VERSION,
			'Residences'=>$data));
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
		$this->config=new Config($this->main->getDataFolder().'residence.yml',Config::YAML,array(
			'DataVersion'=>Utils::CONFIG_VERSION,
			'Residences'=>array()));
		$this->config->set('Residences',Utils::updateConfig($this->config->get('DataVersion'),$this->config->get('Residences')));
		$this->failed=array();
		$this->residences=array();
		foreach($this->config->get('Residences') as $data)
		{
			try
			{
				if($this->getResidenceByName($data['name']))
				{
					$this->main->getLogger()->warning('加载领地 '.$data['name'].' 时出现异常:存在重名领地');
				}
				else
				{
					$this->residences[]=new Residence($this,count($this->residences),$data);
				}
			}
			catch(\FResidence\exception\FResidenceException $e)
			{
				$this->failed[]=$data;
				$this->main->getLogger()->warning('加载领地 '.$data['name'].' 时出现错误:'.$e->getMessage());
				unset($e);
			}
			unset($data);
		}
		$this->save();
		unset($save);
	}
	
	public function getName()
	{
		return 'Yaml';
	}
}
