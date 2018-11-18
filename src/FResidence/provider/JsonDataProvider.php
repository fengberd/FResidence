<?php
namespace FResidence\provider;

use FResidence\utils\Utils;
use FResidence\utils\Residence;

class JsonDataProvider extends BaseDataProvider
{
	private $config_path=null;
	
	public function __construct(\FResidence\Main $main)
	{
		parent::__construct($main);
	}
	
	public function getConfig()
	{
		return $this->config_path;
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
		@file_put_contents($this->config_path,json_encode(array(
			'DataVersion'=>Utils::CONFIG_VERSION,
			'Residences'=>$data)));
	}
	
	public function close($save=true)
	{
		if($save)
		{
			$this->save();
		}
		unset($save);
	}
	
	public function reload($save=false)
	{
		if($save)
		{
			$this->save();
		}
		$this->config_path=$this->main->getDataFolder().'residence.json';
		$config=array(
			'DataVersion'=>Utils::CONFIG_VERSION,
			'Residences'=>array());
		if(file_exists($this->config_path))
		{
			$config=json_decode(file_get_contents($this->config_path),true);
			$config=array(
				'DataVersion'=>Utils::CONFIG_VERSION,
				'Residences'=>Utils::updateConfig($config['DataVersion'],$config['Residences']));
		}
		@file_put_contents($this->config_path,json_encode($config));
		$this->failed=array();
		$this->residences=array();
		foreach($config['Residences'] as $data)
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
		return 'Json';
	}
}
