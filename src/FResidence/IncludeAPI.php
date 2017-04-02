<?php
namespace FResidence;

use onebone\economyapi\EconomyAPI;

class IncludeAPI
{
	public static function Economy_getMoney($player)
	{
		return EconomyAPI::getInstance()->myMoney($player);
	}
	
	public static function Economy_setMoney($player,$count,$force=false)
	{
		return EconomyAPI::getInstance()->setMoney($player,$count,$force);
	}
	
	public static function Economy_addMoney($player,$count,$force=false)
	{
		return EconomyAPI::getInstance()->addMoney($player,$count,$force);
	}
}
