<?php
namespace FResidence\event;

class ResidenceRemoveEvent extends CancellableFResidenceEvent
{
	public function __construct(\FResidence\Main $plugin,$res)
	{
		parent::__construct($plugin,$res);
	}
}
