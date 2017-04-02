<?php
namespace FResidence\utils;

use onebone\economyapi\EconomyAPI;

class Economy
{
	const API_ECONOMY_API='EconomyAPI';
	const API_ZXDA_COUPONS='ZXDACoupons';
	
	private static $API='';
	
	public static function init($preferAPI='')
	{
		$available=array();
		if(class_exists('\\onebone\\economyapi\\EconomyAPI',false))
		{
			$available[]=self::API_ECONOMY_API;
		}
		if(count($available)==0)
		{
			throw new \Exception('无法找到支持的经济API');
		}
		if(!in_array($preferAPI,$available))
		{
			$preferAPI=$available[0];
		}
		self::$API=$preferAPI;
		unset($available,$preferAPI);
	}
	
	public static function getMoney($player)
	{
		$function='self::'.self::$API.'_getMoney';
		return $function($player);
	}
	
	public static function setMoney($player,$count)
	{
		$function='self::'.self::$API.'_setMoney';
		return $function($player,$count);
	}
	
	public static function addMoney($player,$count)
	{
		$function='self::'.self::$API.'_addMoney';
		return $function($player,$count);
	}
	
	public static function EconomyAPI_getMoney($player)
	{
		return EconomyAPI::getInstance()->myMoney($player);
	}
	
	public static function EconomyAPI_setMoney($player,$count,$force=false)
	{
		return EconomyAPI::getInstance()->setMoney($player,$count,$force);
	}
	
	public static function EconomyAPI_addMoney($player,$count,$force=false)
	{
		return EconomyAPI::getInstance()->addMoney($player,$count,$force);
	}
}
