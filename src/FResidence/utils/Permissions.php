<?php
namespace FResidence\utils;

use FResidence\exception\IllegalArgumentException;

class Permissions
{
	const PERMISSION_USE='use';
	const PERMISSION_MOVE='move';
	const PERMISSION_BUILD='build';
	const PERMISSION_TELEPORT='tp';
	
	const PERMISSION_PVP='pvp';
	const PERMISSION_FLOW='flow';
	const PERMISSION_DAMAGE='damage';
	const PERMISSION_HEALING='healing';
	
	private static $defaults=array();
	private static $playerDefaults=array();
	
	public static function init()
	{
		self::$playerDefaults=array(
			self::PERMISSION_USE=>false,
			self::PERMISSION_MOVE=>true,
			self::PERMISSION_BUILD=>false,
			self::PERMISSION_TELEPORT=>false);
		self::$defaults=array_merge(array(
			self::PERMISSION_PVP=>true,
			self::PERMISSION_FLOW=>true,
			self::PERMISSION_DAMAGE=>true,
			self::PERMISSION_HEALING=>false),self::$playerDefaults);
	}
	
	public static function getDefaults()
	{
		return self::$defaults;
	}
	
	public static function getPlayerDefaults()
	{
		return self::$playerDefaults;
	}
	
	public static function validate(array $data)
	{
		foreach($data as $key=>$val)
		{
			if(!isset(self::$defaults[$key]))
			{
				unset($data[$key]);
			}
			else
			{
				$data[$key]=Utils::parseBool($data[$key]);
			}
			unset($key,$val);
		}
		foreach(self::$defaults as $key=>$val)
		{
			if(!isset($data[$key]))
			{
				$data[$key]=$val;
			}
			unset($key,$val);
		}
		return $data;
	}
	
	public static function validateIndex(string $index)
	{
		return isset(self::$defaults[$index]);
	}
	
	public static function validateIndexThrow(string $index)
	{
		if(!self::validateIndex($index=strtolower($index)))
		{
			throw new IllegalArgumentException('无效权限索引,请使用 Permissions::PERMISSION_XXX 常量');
		}
		return $index;
	}
	
	public static function validatePlayer(array $data)
	{
		foreach($data as $key=>$val)
		{
			if(!isset(self::$playerDefaults[$key]))
			{
				unset($data[$key]);
			}
			else
			{
				$data[$key]=parseBool($data[$key]);
			}
			unset($key,$val);
		}
		foreach(self::$playerDefaults as $key=>$val)
		{
			if(!isset($data[$key]))
			{
				$data[$key]=$val;
			}
			unset($key,$val);
		}
		return $data;
	}
	
	public static function validatePlayerIndex(string $index)
	{
		return isset(self::$playerDefaults[$index]);
	}
	
	public static function validatePlayerIndexThrow(string $index)
	{
		if(!self::validatePlayerIndex($index=strtolower($index)))
		{
			throw new IllegalArgumentException('无效权限索引,请使用 Permissions::PERMISSION_XXX 常量');
		}
		return $index;
	}
	
	private $residence=null;
	
	private $permissions=array();
	private $playerPermissions=array();
	
	public function __construct(...$data)
	{
		if(isset($data[0]) && is_array($data[0]))
		{
			$this->permissions=self::validate($data[0]['default']);
			foreach($data[0]['players'] as $key=>$val)
			{
				$this->playerPermissions[Utils::getPlayerName($key)]=self::validatePlayer($val);
				unset($key,$val);
			}
			if(isset($data[1]) && $data[1] instanceof Residence)
			{
				$this->residence=$data[1];
			}
		}
		else
		{
			$this->permissions=self::getDefaults();
			$this->playerPermissions=array();
			if(isset($data[0]) && $data[0] instanceof Residence)
			{
				$this->residence=$data[0];
			}
		}
		unset($data);
	}
	
	public function getRawData()
	{
		return array(
			'default'=>$this->permissions,
			'players'=>$this->playerPermissions);
	}
	
	public function trySave()
	{
		if($this->residence instanceof Residence)
		{
			$this->residence->save();
			return true;
		}
		return false;
	}
	
	public function hasPermission($player,string $index)
	{
		$player=Utils::getPlayerName($player);
		$index=self::validateIndexThrow($index);
		if(isset($this->playerPermissions[$player][$index]))
		{
			return $this->playerPermissions[$player][$index];
		}
		return $this->permissions[$index];
	}
	
	public function getPermission(string $index)
	{
		return $this->permissions[self::validateIndexThrow($index)];
	}
	
	public function setPermission(string $index,$val=true)
	{
		$index=self::validateIndexThrow($index);
		$this->permissions[$index]=Utils::parseBool($val);
		$this->trySave();
		unset($index,$val);
		return $this;
	}
	
	public function resetPermissions()
	{
		$this->permissions=self::getDefaults();
		$this->trySave();
		return $this;
	}
	
	public function setPlayerPermission($player,string $index,$val=true)
	{
		$player=Utils::getPlayerName($player);
		$index=self::validatePlayerIndexThrow($index);
		$this->playerPermissions[$player][$index]=Utils::parseBool($val);
		$this->trySave();
		unset($player,$index,$val);
		return $this;
	}
	
	public function clearPlayerPermissions($player)
	{
		unset($this->playerPermissions[Utils::getPlayerName($player)]);
		$this->trySave();
		unset($player);
		return $this;
	}
	
	public function clearAllPlayerPermissions()
	{
		$this->playerPermissions=array();
		$this->trySave();
		return $this;
	}
}
