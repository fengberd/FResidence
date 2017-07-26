<?php
namespace FResidence\provider;

use pocketmine\utils\Config;

use FResidence\utils\Utils;
use FResidence\exception\IllegalArgumentException;

class ConfigProvider
{
	private static $config=null;
	private static $defaults=array(
		'Provider'=>'Yaml',
		'MoneyName'=>'元',
		'SelectVert'=>false,
		'SelectItem'=>\pocketmine\item\Item::STRING,
		'PreferEconomy'=>'EconomyAPI',
		'CheckMoveTick'=>10,
		'MoneyPerBlock'=>0.05,
		'MaxResidenceCount'=>3,
		'BlackListWorld'=>array(),
		'WhiteListWorld'=>array());
	
	public static function getDefaults()
	{
		return $defaults;
	}
	
	public static function getConfig()
	{
		return self::$config;
	}
	
	public static function validateIndex($index)
	{
		return isset(self::$defaults[$index]);
	}
	
	public static function init($main)
	{
		@mkdir($main->getDataFolder());
		self::$config=new Config($main->getDataFolder().'config.yml',Config::YAML,self::$defaults);
		$data=self::$defaults;
		if(!array_walk($data,function(&$val,$key)
		{
			$val=self::$config->get($key,$val);
		}))
		{
			throw new \FResidence\exception\ConfigException('无法读取配置数据');
		}
		self::BlackListWorld(array_map('strtolower',self::BlackListWorld()));
		self::WhiteListWorld(array_map('strtolower',self::WhiteListWorld()));
		if(self::$config->exists('landItem'))
		{
			$main->getLogger()->warning('检测到旧版本的config.yml,正在更新...');
			$data['MoneyName']=self::$config->get('moneyName',self::MoneyName());
			$data['SelectItem']=self::$config->get('landItem',self::SelectItem());
			$data['CheckMoveTick']=self::$config->get('checkMoveTick',self::CheckMoveTick());
			$data['MoneyPerBlock']=self::$config->get('blockMoney',self::MoneyPerBlock());
			$data['MaxResidenceCount']=self::$config->get('playerMaxCount',self::MaxResidenceCount());
		}
		self::$config->setAll($data);
		self::save();
		unset($main);
	}
	
	public static function save()
	{
		if(self::$config instanceof Config)
		{
			self::$config->save();
		}
	}
	
	public static function __callStatic($index,$args)
	{
		if(!self::validateIndex($index))
		{
			unset($index,$args);
			throw new IllegalArgumentException('Invalid Config Index.');
		}
		if(!isset($args[0]))
		{
			return self::$config->get($index,self::$defaults[$index]);
		}
		self::$config->set($index,$args[0]);
		self::save();
	}
}
