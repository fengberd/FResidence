<?php
namespace FResidence\event;

class ResidenceAddEvent extends CancellableFResidenceEvent
{
	private $money;
	private $select1;
	private $select2;
	private $resname;
	private $player;
	
	public function __construct(\FResidence\Main $plugin,$money,$select1,$select2,$resname,$player)
	{
		parent::__construct($plugin,null);
		$this->select1=$select1;
		$this->select2=$select2;
		$this->resname=$resname;
		$this->money=$money;
		$this->player=$player;
	}
	
	public function getPlayer()
	{
		return $this->player;
	}
	
	public function getPos1()
	{
		return $this->select1;
	}
	
	public function setPos1($val)
	{
		$this->select1=$val;
		unset($val);
	}
	
	public function getPos2()
	{
		return $this->select2;
	}
	
	public function setPos2($val)
	{
		$this->select2=$val;
		unset($val);
	}
	
	public function getResName()
	{
		return $this->resname;
	}
	
	public function setResName($val)
	{
		$this->resname=$val;
		unset($val);
	}
	
	public function getMoney()
	{
		return $this->money;
	}
	
	public function setMoney($val)
	{
		$this->money=$val;
		unset($val);
	}
}
