<?php
namespace FResidence\Provider;

use FResidence\Main;

interface DataProvider
{
	public function __construct(Main $main);
	public function addResidence($startpos,$endpos,$owner,$name);
	public function getAllResidences();
	public function removeResidenceByPosition($pos);
	public function removeResidenceByID($resid);
	public function removeResidenceByOwner($owner);
	public function queryResidenceByPosition($pos);
	public function getResidenceByID($ID);
	public function queryResidencesByOwner($owner);
	public function getConfig();
	public function reload();
	public function close();
	public function in_residence($res,$pos);
	public function getResidenceVector3Array($pos1,$pos2);
	public function getResidenceMessage($res,$msgid,$default);
	public function setResidenceMessage($resid,$msgid,$message);
}
?>
