<?php
namespace FResidence\event;

use FResidence\utils\Utils;

class ResidenceOwnerChangeEvent extends CancellableFResidenceEvent
{
	private $owner=null;
	
	public function __construct(\FResidence\Main $plugin,$res,$owner)
	{
		parent::__construct($plugin,$res);
		$this->owner=Utils::getPlayerName($owner);
	}
	
	public function getOwner()
	{
		return $this->owner;
	}
	
	public function setOwner($owner)
	{
		$this->owner=Utils::getPlayerName($owner);
	}
}
