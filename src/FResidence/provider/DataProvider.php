<?php
namespace FResidence\provider;

interface DataProvider
{
	public function __construct(\FResidence\Main $main);
	
	public function addResidence($pos1,$pos2,$owner,$name);
	
	public function getResidence($id);
	public function getResidenceByName($name);
	public function getResidenceByPosition($pos);
	
	public function getAllResidences();
	public function getResidencesByOwner($owner);
	
	public function removeResidence($id);
	public function removeResidencesByOwner($owner);
	
	public function save();
	public function close($save);
	public function reload($save);
}
