<?php
/**
* This class provides methods to connect to open3a
*
* @author Stephan Richter <s.richter@srsoftware.de>
* @name open3a
* @package Collabtive
* @version 2.0
* @license http://opensource.org/licenses/gpl-license.php GNU General Public License v3 or later
*/

class open3a {
	
	/**
	 * Konstruktor
	 * Initialisiert den Eventlog
	 */
	function __construct() {
		include CL_ROOT.'/config/open3a/config.php';
		$this->conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
		$this->open3aUser=$user_id;
	}
	
	function warn($message){
		if (!isset($this->warnings)){
			$this->warnings=array();
		}
		$warnings[]=$message;
	}
	
	function getTemplate(){
		$stmt = $this->conn->prepare('SELECT ownTemplate FROM Stammdaten WHERE aktiv=1');
		$res=$stmt->execute();
		$templ=null;		
		if ($data = $stmt->fetch()) {
			$templ=$data['ownTemplate'];
		}
		$stmt->closeCursor();
		return $templ;
	}
	
	function getAdressForCustomer($customer_id){
		$stmt = $this->conn->prepare('SELECT AdresseID FROM Kappendix WHERE kundennummer = ?');
		$res=$stmt->execute(array($customer_id));
		$addr=null;		
		if ($data = $stmt->fetch()) {
			$addr=$data['AdresseID'];
		}
		$stmt->closeCursor();
		return $addr;
	}
	
	function getDefaultInvoiceTexts(){
		$stmt = $this->conn->prepare('SELECT TextbausteinID,KategorieID,text FROM Textbaustein WHERE isRStandard=1');
		$res=$stmt->execute();
		$texts=array();
		while ($data = $stmt->fetch()){
			$kat=$data['KategorieID'];
			$texts[$kat]=array('tx'=>$data['text'],'id'=>$data['TextbausteinID']);			
		}		
		$stmt->closeCursor();
		return $texts;
	}
	
	function getNumberFormat(){
		$stmt = $this->conn->prepare('SELECT belegNummerFormatR FROM Stammdaten WHERE aktiv=1');
		$res=$stmt->execute();
		$format = null;	
		if ($data = $stmt->fetch()) {
			$format=$data['belegNummerFormatR'];
		}		
		$stmt->closeCursor();
		return $format;
	}

	function readNextNumber(){
		$stmt = $this->conn->prepare('SELECT wert FROM Userdata WHERE name="belegNummerNextR"');
		$res=$stmt->execute();
		$num = null;
		if ($data = $stmt->fetch()) {
			$num=$data['wert'];
		}
		$stmt->closeCursor();
		return $num;
	}
	
	function getInvoicePrefix(){
		$stmt = $this->conn->prepare('SELECT prefixR FROM Stammdaten WHERE aktiv=1');
		$res=$stmt->execute();
		$prefix = '';
		if ($data = $stmt->fetch()) {
			$prefix=$data['prefixR'];
		}
		$stmt->closeCursor();
		return $prefix;
	}
	
	function nextNumber($knr){
		$format=$this->getNumberFormat();
		if ($format == null){
			warn('Number format not set!');
			return null;
		}
		$next=$this->readNextNumber();
		if ($next==null){
			$next=1;
		}
		$replace = array(
				"{J}" => date("Y"),
				"{J2}" => date("y"),
				"{T}" => str_pad(date("z"), 3, "0", STR_PAD_LEFT),
				"{M}" => date("m"),
				"{M1}" => date("m") * 1,
				"{K}" => $knr
		);
		foreach($replace AS $k => $v){
			$format = str_replace($k, $v, $format);
		}
		
		$useNext = $next;
		preg_match("/\{N:([0-9]+)\}/", $format, $matches);
		if(isset($matches[1])){
			$useNext = str_pad($next, $matches[1], "0", STR_PAD_LEFT);
			$format = str_replace($matches[0], $useNext, $format);
		}
		
		return $format;
	}
	
	function createBillFor($timetrack,$customer){
		$maxlen=25;
		$preis=15.0;
		$mwst=19.0;
		$template=$this->getTemplate();
		$adr_id=$this->getAdressForCustomer($customer);		
		$texts=$this->getDefaultInvoiceTexts();
		$belegnummer=$this->nextNumber($customer);
		$prefix=$this->getInvoicePrefix();
		$user = $this->open3aUser;		
		$stmt = $this->conn->prepare('INSERT INTO Auftrag (AdresseID, auftragdatum, kundennummer, UserID, AuftragVorlage, AuftragStammdatenID) VALUES (?,?,?,?,?,?)');
		$success=$stmt->execute(array($adr_id,time(),$customer,$this->open3aUser,$template,1));
		if ($success){
			$auftrag_id=$this->conn->lastInsertId();
			$stmt->closeCursor();			
			$stmt = $this->conn->prepare('INSERT INTO GRLBM (AuftragID,datum,isR,nummer,textbausteinOben,textbausteinUnten,zahlungsbedingungen,lieferDatum,prefix,textbausteinObenID,textbausteinUntenID,zahlungsbedingungenID,GRLBMpayedVia) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
			$success = $stmt->execute(array($auftrag_id,time(),1,$belegnummer,$texts[2]['tx'],$texts[3]['tx'],$texts[1]['tx'],time(),$prefix,$texts[2]['id'],$texts[3]['id'],$texts[1]['id'],'transfer'));
			if ($success){
				$grlbm_id=$this->conn->lastInsertId();
				$stmt->closeCursor();
				$stmt=$this->conn->prepare('INSERT INTO Posten (name,gebinde,GRLBMID,preis,menge,mwst,artikelnummer,beschreibung,bruttopreis) VALUES (?,?,?,?,?,?,?,?,?)');
				foreach ($timetrack as $track){
					$comment=trim(strip_tags($track['comment']));
					if (strlen($comment)>$maxlen){
						$pos=strpos($comment, ' ', $maxlen);
						if ($pos === false){
							$short_comment=$comment;
						} else {
							$short_comment=substr($comment,0,$pos ).'...';
						}
					} else {
						$short_comment=$comment;
					}
					$day=$track['daystring'];
					$start=$track['startstring'];
					$end=$track['endstring'];
					$hours=$track['hours'];
					$user=$track['uname'];					
					$task=($track['task']==0)?$short_comment:trim(strip_tags($track['tname']));
					$brutto=round($preis+($mwst*$preis/100),3);
					$success=$stmt->execute(array($task,'h',$grlbm_id,$preis,$hours,$mwst,'Timesheet',$comment,$brutto));
					if (!$success){
						print_r($stmt->errorInfo());
						die();
					}
				}
				print "success";								
				return;
			}
		}
		print_r($stmt->errorInfo());		
	}
}

?>
