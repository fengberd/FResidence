<?php
namespace FResidence\event;

use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;

use FResidence\Main;

class ResidenceRemoveEvent extends FResidenceEvent implements Cancellable
{
	private $res;
	
	public function __construct(Main $plugin,$res)
	{
		parent::__construct($plugin);
		$this->res=$res;
	}
	
	public function getResidence()
	{
		return $this->res;
	}
}
?>
