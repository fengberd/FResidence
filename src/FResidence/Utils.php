<?php
namespace FResidence;

use pocketmine\math\Vector3;

class Utils
{
	public static function calcBoxSize($p1,$p2)
	{
		return max(abs($p1->getX()-$p2->getX()),1)*max(abs($p1->getY()-$p2->getY()),1)*max(abs($p1->getZ()-$p2->getZ()),1);
	}
}
