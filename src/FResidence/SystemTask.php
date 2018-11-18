<?php
namespace FResidence;

class SystemTask extends \pocketmine\scheduler\Task
{
	public function onRun($currentTick)
	{
		\FResidence\Main::getInstance()->systemTaskCallback($currentTick);
	}
}
