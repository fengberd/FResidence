<?php
namespace FResidence\event;

use pocketmine\event\plugin\PluginEvent;

use FResidence\Main;

abstract class FResidenceEvent extends PluginEvent
{
	public static $handlerList=null;
	
	public function __construct(Main $plugin)
	{
		parent::__construct($plugin);
	}
}
?>
