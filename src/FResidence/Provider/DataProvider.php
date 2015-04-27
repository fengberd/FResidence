<?php
namespace FResidence\Provider;

use FResidence\Main;

interface DataProvider
{
	public function __construct(Main $main);
	public function addResidence($startpos,$endpos,$owner,$name);
	public function getAllResidences();
	public function removeResidence($resid);
	public function queryResidenceByPosition($pos);
	public function getResidenceByID($ID);
	public function queryResidencesByOwner($owner);
	public function getConfig();
	public function reload();
	public function close();
	public function save();
	public function in_residence($res,$pos);
	public function getResidenceVector3Array($pos1,$pos2);
}
?>
