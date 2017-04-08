<?php
namespace FResidence\utils;

use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;

class Utils
{
	const CONFIG_VERSION=2;
	
	private static $main=null;
	private static $pluginManager=null;
	
	public static function init(\FResidence\Main $main)
	{
		self::$main=$main;
		self::$pluginManager=$main->getServer()->getPluginManager();
	}
	
	public static function callEvent(\FResidence\event\FResidenceEvent $ev)
	{
		self::$pluginManager->callEvent($ev);
		if($ev instanceof \FResidence\event\CancellableFResidenceEvent)
		{
			return !$ev->isCancelled();
		}
		return true;
	}
	
	public static function calculateSize($p1,$p2)
	{
		return max(abs($p1->getX()-$p2->getX()),1)*max(abs($p1->getY()-$p2->getY()),1)*max(abs($p1->getZ()-$p2->getZ()),1);
	}
	
	public static function getPlayerName($var)
	{
		if(is_string($var))
		{
			return strtolower($var);
		}
		else if($var instanceof \pocketmine\IPlayer || $var instanceof \FResidence\utils\PlayerInfo)
		{
			return strtolower($var->getName());
		}
		throw new \FResidence\exception\IllegalArgumentException('此函数只接受字符串或IPlayer/PlayerInfo对象');
	}
	
	public static function validatePlayerName($val)
	{
		return preg_match('#^[a-zA-Z0-9_]{3,16}$#',$val)!=0;
	}
	
	public static function makeList($title,$data,&$page,$itemPerPage,$callback=null)
	{
		$total=ceil(count($data)/$itemPerPage);
		if(!isset($page) || ($page=intval($page))>$total || $page<1)
		{
			$page=1;
		}
		return implode("\n",array_map($callback===null?function($val)
		{
			return is_array($val)?(TextFormat::DARK_GREEN.$val[1].': '.TextFormat::WHITE.$val[2]):$val;
		}:$callback,array_merge(array('--- '.$title.' ['.$page.'/'.$total.'] ---'),array_slice($data,($page-1)*$itemPerPage,$itemPerPage))));
	}
	
	public static function parseBool($val)
	{
		if(is_bool($val))
		{
			return $val;
		}
		if(is_int($val))
		{
			return $val<=0?false:true;
		}
		if(is_string($val))
		{
			$val=strtolower($val);
			return $val=='true' || $val=='1' || $val=='真' || $val=='开' || $val=='开启';
		}
		return false;
	}
	
	public static function parsePosition(array $data,\pocketmine\level\Level $level)
	{
		return new \pocketmine\level\Position($data['x'],$data['y'],$data['z'],$level);
	}
	
	public static function encodeVector3(Vector3 $data)
	{
		return array(
			'x'=>$data->x,
			'y'=>$data->y,
			'z'=>$data->z);
	}
	
	public static function updateConfig($version,$data)
	{
		if($version>self::CONFIG_VERSION)
		{
			throw new \FResidence\exception\VersionException('您当前使用的FResidence版本过旧,无法读取领地数据,请更新插件至最新版!');
		}
		while($version<self::CONFIG_VERSION)
		{
			foreach($data as $key=>$val)
			{
				switch($version)
				{
				case 1:
					$data[$key]=array(
						'name'=>$val['name'],
						'owner'=>$val['owner'],
						'level'=>$val['level'],
						'messages'=>array(
							'enter'=>$val['metadata']['message']['enter'],
							'leave'=>$val['metadata']['message']['leave'],
							'permission'=>$val['metadata']['message']['permission']),
						'positions'=>array(
							'pos1'=>$val['start'],
							'pos2'=>$val['end'],
							'teleport'=>$val['metadata']['teleport']),
						'permissions'=>array(
							'default'=>$val['metadata']['permission'],
							'players'=>$val['metadata']['playerpermission']));
					break;
				}
				unset($key,$val);
			}
			$version++;
		}
		return $data;
	}
	
	public static function getRedString($msg)
	{
		return self::getColoredString($msg,TextFormat::RED);
	}
	
	public static function getAquaString($msg)
	{
		return self::getColoredString($msg,TextFormat::AQUA);
	}
	
	public static function getGreenString($msg)
	{
		return self::getColoredString($msg,TextFormat::GREEN);
	}
	
	public static function getYellowString($msg)
	{
		return self::getColoredString($msg,TextFormat::YELLOW);
	}
	
	public static function getColoredString($msg,$color=TextFormat::WHITE)
	{
		return '[FResidence] '.$color.$msg;
	}
}
