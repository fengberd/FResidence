<?php
namespace FResidence\event;

abstract class FResidenceEvent extends \pocketmine\event\plugin\PluginEvent
{
	public static $handlerList=null;
	
	private $res;
	
	public function __construct(\FResidence\Main $plugin,$res=null)
	{
		parent::__construct($plugin);
		$this->res=$res;
	}
	
	public function getResidence()
	{
		return $this->res;
	}
}
