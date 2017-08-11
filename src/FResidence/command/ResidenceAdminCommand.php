<?php
namespace FResidence\command;

class ResidenceAdminCommand extends \pocketmine\command\Command
{
	private $main=null;
	
	public function __construct(\FResidence\Main $main)
	{
		parent::__construct('residenceadmin','领地插件指令 - 使用 /resadmin help 查看帮助','使用 /resadmin help 查看帮助',array('resadmin'));
		$this->main=$main;
		$this->setPermission('FResidence.admin');
	}
	
	public function execute(\pocketmine\command\CommandSender $sender,$label,array $args)
	{
		if($this->testPermission($sender))
		{
			try
			{
				if(!isset($args[0]))
				{
					$args[0]='help';
				}
				$args[0]=strtolower($args[0]);
				$this->main->onResidenceAdminCommand($sender,$args);
			}
			catch(\FResidence\exception\FResidenceException $e)
			{
				$sender->sendMessage(Utils::getRedString('无法完成操作: '.$e->getMessage()));
			}
		}
		unset($sender,$label,$args);
		return true;
	}
}
