<?php
namespace FResidence\exception;

class VersionException extends FResidenceException
{
	public function __construct($message)
	{
		parent::__construct($message);
	}
}
