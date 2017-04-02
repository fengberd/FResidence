<?php
namespace FResidence\event;

class CancellableFResidenceEvent extends FResidenceEvent implements \pocketmine\event\Cancellable
{
	public function __construct(\FResidence\Main $plugin,$res)
	{
		parent::__construct($plugin,$res);
	}
}
