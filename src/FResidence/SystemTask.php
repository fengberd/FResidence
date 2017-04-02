<?php
namespace FResidence;

class SystemTask extends \pocketmine\scheduler\PluginTask
{
    public function __construct(Main $plugin)
    {
        parent::__construct($plugin);
    }
    
    public function onRun($currentTick)
    {
        $this->getOwner()->systemTaskCallback($currentTick);
    }
}
