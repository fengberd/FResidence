<?php
namespace FResidence\utils;

class Messages
{
	const INDEX_ENTER='enter';
	const INDEX_LEAVE='leave';
	const INDEX_PERMISSION='permission';
	
	private static $defaults=array(
		self::INDEX_ENTER=>'欢迎来到 %name ,这里是 %owner 的领地',
		self::INDEX_LEAVE=>'你离开了 %name',
		self::INDEX_PERMISSION=>'你没有权限使用这块领地');
	
	public static function getDefaults()
	{
		return self::$defaults;
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
				// TODO: Remove illegal chars
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
			throw new \FResidence\exception\InvalidArgumentException('无效消息索引,请使用 Messages::INDEX_XXX 常量');
		}
		return $index;
	}
	
	private $residence=null;
	
	private $messages=array();
	
	public function __construct(...$data)
	{
		if(isset($data[0]) && is_array($data[0]))
		{
			$this->messages=self::validate($data[0]);
			if(isset($data[1]) && $data[1] instanceof Residence)
			{
				$this->residence=$data[1];
			}
		}
		else
		{
			$this->messages=self::getDefaults();
			if(isset($data[0]) && $data[0] instanceof Residence)
			{
				$this->residence=$data[0];
			}
		}
		unset($data);
	}
	
	public function getRawData()
	{
		return $this->messages;
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
	
	public function getMessage(string $index)
	{
		$index=self::validateIndexThrow($index);
		return $this->messages[$index];
	}
	
	public function setMessage(string $index,$val='')
	{
		$index=self::validateIndexThrow($index);
		$this->messages[$index]=$val;
		$this->trySave();
		unset($index,$val);
		return $this;
	}
	
	public function resetMessages()
	{
		$this->messages=self::getDefaults();
		$this->trySave();
		return $this;
	}
}
