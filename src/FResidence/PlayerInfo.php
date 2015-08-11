<?php
namespace FResidence;

class PlayerInfo
{
	public $p1=false;
	public $p2=false;
	public $player=false;
	public $nowland=false;
	public $checkMoveTick=10;
	public $move=array();
	
	public function __construct($player)
	{
		$this->player=$player;
	}
	
	public function isSelectFinish()
	{
		return ($this->p1!==false && $this->p2!==false);
	}
	
	public function getP1()
	{
		return $this->p1;
	}
	
	public function getP2()
	{
		return $this->p2;
	}
	
	public function setP1($pos)
	{
		$this->p1=$pos;
		unset($pos);
	}
	
	public function setP2($pos)
	{
		$this->p2=$pos;
		unset($pos);
	}
}
?>
