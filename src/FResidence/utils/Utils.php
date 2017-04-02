<?php
namespace FResidence\utils;

use pocketmine\math\Vector3;

class Utils
{
	const CONFIG_VERSION=2;
	
	public static function calucateSize($p1,$p2)
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
		throw new \InvalidArgumentException('This function only accept string or IPlayer/PlayerInfo object.');
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
		return new Position($data['x'],$data['y'],$data['z'],$level);
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
		if($version>CONFIG_VERSION)
		{
			throw new \Exception('您当前使用的FResidence版本过旧,无法读取领地数据,请更新插件至最新版!');
		}
		while($version<CONFIG_VERSION)
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
}
