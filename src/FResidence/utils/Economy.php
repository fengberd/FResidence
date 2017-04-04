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
			throw new \FResidence\exception\MissingDependException('无法找到支持的经济API');
		}
		if(!in_array($preferAPI,$available))
		{
			$preferAPI=$available[0];
		}
		self::$API=$preferAPI;
		unset($available,$preferAPI);
	}
	
	public static function getApiName()
	{
		return self::$API;
	}
	
	public static function getMoney($player)
	{
		$function='\\FResidence\\utils\\Economy::'.self::$API.'_getMoney';
		return $function(Utils::getPlayerName($player));
	}
	
	public static function setMoney($player,$count)
	{
		$function='\\FResidence\\utils\\Economy::'.self::$API.'_setMoney';
		return $function(Utils::getPlayerName($player),$count);
	}
	
	public static function addMoney($player,$count)
	{
		$function='\\FResidence\\utils\\Economy::'.self::$API.'_addMoney';
		return $function(Utils::getPlayerName($player),$count);
	}
	
	public static function reduceMoney($player,$count)
	{
		$function='\\FResidence\\utils\\Economy::'.self::$API.'_reduceMoney';
		return $function(Utils::getPlayerName($player),$count);
	}
	
	public static function EconomyAPI_getMoney($player)
	{
		return EconomyAPI::getInstance()->myMoney($player);
	}
	
	public static function EconomyAPI_setMoney($player,$count)
	{
		return EconomyAPI::getInstance()->setMoney($player,$count,false,'FResidence')==EconomyAPI::RET_SUCCESS;
	}
	
	public static function EconomyAPI_addMoney($player,$count)
	{
		return EconomyAPI::getInstance()->addMoney($player,$count,false,'FResidence')==EconomyAPI::RET_SUCCESS;
	}
	
	public static function EconomyAPI_reduceMoney($player,$count)
	{
		return EconomyAPI::getInstance()->reduceMoney($player,$count,false,'FResidence')==EconomyAPI::RET_SUCCESS;
	}
}
