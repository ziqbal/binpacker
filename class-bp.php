<?php
/**
* Bin Packer Class
* See readme.txt file for description and usage
* 2004-12-19	First Version	ultrasine@gmail.com
*
* IMPORTANT NOTE
* there is no warranty, implied or otherwise with this software.
* 
* LICENCE
* This code has been placed in the Public Domain for all to enjoy.
*
* @author	<ultrasine@gmail.com>
* @package	BinPacker
*/
class BinPacker {

	// Private Variables
	private $debug;
	private $binSize;
	private $pointerArray;
	private $hashIndex;
	private $totalCargo;
	private $bins;

	// Class Constructor
	public function __construct($debugMode=false) {
		$this->debug=$debugMode;
		$this->pointerArray=array();
		$this->hashIndex=array();
		$this->totalCargo=0;
		$this->bins=array();
		mt_srand(doubleval(microtime())*100000000);
		$this->dprint("Class BinPacker Created!");
	}

	// Class Destructor
	public function __destruct() {
		$this->dprint("Class BinPacker Destroyed!");
	}

	// Debug Print
	private function dprint($string="") {
		if($this->debug){
			print("$string\n");
		}
	}

	// Setting Bin Size
	public function setBinSize($size=690,$metric="M") {
		$multiplier=1;
		switch(strtolower($metric)){
			case 'k':
				$multiplier=1024;
				break;
			case 'm':
				$multiplier=1024*1024;
				break;
			case 'g':
				$multiplier=1024*1024*1024;
				break;
			default:
				$multiplier=1;
		}
		$this->binSize=$size*$multiplier;
		$this->dprint("Bin Size Set to $size$metric");
	}

	// Return Bin Size
	public function getBinSize() {
		return $this->binSize;
	}

	// Recursive Function to Add Directories/Files
	public function add($node,$recursive=false,$extension="") {

		$this->dprint("Adding $node");

		$node=str_replace('\\','/',$node);

		if(is_dir($node)){

			$currentDirectory=$node;

			while(substr($currentDirectory,strlen($currentDirectory)-1,1)=="/"){
				$currentDirectory=substr($currentDirectory,0,strlen($currentDirectory)-1);
			}

			$directory=opendir($currentDirectory);
			while(FALSE !== ($newNode=readdir($directory))){
				if($newNode!="." && $newNode!=".."){
					$nodePath="$currentDirectory/$newNode";
					if(is_dir($nodePath) && $recursive){
						$this->add($nodePath,$recursive,$extension);
					}

					$extDot=strrpos($newNode,'.');
					if($extDot){
						$fileExt=strtolower(substr($newNode,$extDot+1,strlen($newNode)-$extDot-1));
					}

					if(strlen($extension)>0) {
					       	if(strtolower($extension)==$fileExt){
							if(is_file($nodePath)) $this->add($nodePath);
						}
					} else {
						if(is_file($nodePath)) $this->add($nodePath);
					}
				}
			}
			closedir($directory);
		}

		if(is_file($node)){
			$fileSize=filesize($node);

			if($fileSize>$this->binSize){
				$this->dprint("Skipping $node - bigger than bin!");
			} else {
				$fuzzyHash=$this->getFuzzyHash($node);
				if(!array_key_exists($fuzzyHash,$this->hashIndex)){
					$this->hashIndex[$fuzzyHash]=array($node,$fileSize,0);
					$this->pointerArray[]=$fuzzyHash;
					$this->totalCargo=$this->totalCargo+$fileSize;
				} else {
					$this->dprint("Skipping $node - already exists!");
				}
			}
		}
	}

	// Return 'Fuzzy' Hash
	private function getFuzzyHash($filePath) {
		$fp=fopen($filePath,"r");
		$currentHash=sha1(fread($fp,1048576));
		while(!feof($fp)) $currentHash=sha1($currentHash.sha1(fread($fp,1048576)));
		fclose($fp);
		return $currentHash;
	}

	// Pack Function	
	public function pack() {
		$this->dprint("Packing...");
		$totalBins=ceil($this->totalCargo/$this->binSize);
		$this->dprint("Ideally $totalBins Bin(s)...");
		$totalItems=count($this->pointerArray);
		if($totalItems>1){
			$this->shuffle();
			$binID=0;
			while($this->packRemaining()>0){
				$currentBin=array();
				$currentBinTotal=0;
				for($i=0;$i<$totalItems;$i++){
					$currentItem=$this->hashIndex[$this->pointerArray[$i]];
					if(($currentItem[2]==0) && (($currentItem[1]+$currentBinTotal)<=$this->binSize)){
						$this->hashIndex[$this->pointerArray[$i]][2]=1;
						$currentBinTotal+=$currentItem[1];
						$currentBin[]=$i;
					}
				}
				$this->bins[]=$currentBin;
				$binID++;
			}
		} else {
			$this->dprint("Packing: task too trivial... more than 1 file is required!");
		}
	}

	// Output Function
	public function output($targetDir="",$move=false) {
		$moveFiles=false;
		if(strlen($targetDir)>0){
			if(is_dir($targetDir)){
				$moveFiles=true;
			}
		}

		$binNumber=1;
		foreach ($this->bins as $itemKey=>$itemValue){
			$fileArray=array();
			$binSize=0;
			foreach($itemValue as $key=>$value){
				$myItem=$this->hashIndex[$this->pointerArray[$value]];
				$fileArray[]=$myItem[0];
				$binSize+=$myItem[1];
				$filePath=$myItem[0];
				if($moveFiles) $this->moveFile($filePath,$targetDir,$binNumber,$move);	
			}
			sort($fileArray);
			$binWaste=$this->binSize-$binSize;
			$this->dprint("Bin $binNumber : Size = $binSize ($binWaste Bytes Wasted)");
			$binNumber++;
		}
	}

	// Copy/Move File, Creating subfolders and info files
	private function moveFile($filePath,$targetDir,$binNumber,$move=false) {
		
		while(substr($targetDir,strlen($targetDir)-1,1)=="/"){
			$targetDir=substr($targetDir,0,strlen($targetDir)-1);
		}

		if(!is_dir("$targetDir/bin-$binNumber")){
			mkdir("$targetDir/bin-$binNumber");
		}

		$logFile="$targetDir/bin-$binNumber.txt";
		$targetDir="$targetDir/bin-$binNumber";

		str_replace("\\","/",$filePath);
		$pathArray=split('/',$filePath);

		if(count($pathArray)>1) {
			array_pop($pathArray);
		} else {
			$pathArray=array();
		}

		$logPath='';
		foreach($pathArray as $key=>$value){
			if((strlen($value)>0) && (strpos($value,':')==false)){
				$targetDir="$targetDir/$value";
				if(!is_dir($targetDir)){
					mkdir($targetDir);
				}
				$logPath="$logPath/$value";
			}
		}

		if(!is_file("$targetDir/".basename($filePath))){
			if($move){
				rename($filePath,"$targetDir/".basename($filePath));
			} else {
				copy($filePath,"$targetDir/".basename($filePath));
			}
			if (!$handle = fopen($logFile, 'a')) { echo "Cannot open file ($logFile)"; }
			if (fwrite($handle, "$logPath/".basename($filePath)."\r\n") === FALSE) { echo "Cannot write to file ($logFile)"; }
			fclose($handle);
		} else {
			$this->dprint("Target Directory already contains file!");
		}
	}

	// Return number of items that still need processing	
	private function packRemaining(){
		$remaining=0;
		$totalItems=count($this->pointerArray);
		for($i=0;$i<$totalItems;$i++){
			$currentItem=$this->hashIndex[$this->pointerArray[$i]];
			if($currentItem[2]==0) $remaining++;
		}
		return $remaining;
	}

	// Function to shuffle items	
	private function shuffle() {
		$lastIndex=count($this->pointerArray)-1;
		$iterations=($lastIndex+1)*1000;
		while($iterations>0){
			$i=0;$j=0;
			while($i==$j){$i=mt_rand(0,$lastIndex);$j=mt_rand(0,$lastIndex);}
			$z=$this->pointerArray[$i];
			$this->pointerArray[$i]=$this->pointerArray[$j];
			$this->pointerArray[$j]=$z;
			$iterations--;
		}
	}

}

?>
