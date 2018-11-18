<?php
namespace FResidence\provider;

use pocketmine\utils\Config;

use FResidence\utils\Utils;
use FResidence\utils\Residence;

class YamlDataProvider extends BaseDataProvider
{
	private $config=null;
	
	public function __construct(\FResidence\Main $main)
	{
		parent::__construct($main);
	}
	
	public function getConfig()
	{
		return $this->config;
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
		unset($save);
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
