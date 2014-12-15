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
		$stmt = $this->conn->prepare('SELECT KategorieID,text FROM Textbaustein WHERE isRStandard=1');
		$res=$stmt->execute();
		$texts=array();
		while ($data = $stmt->fetch()){
			$kat=$data['KategorieID'];
			$texts[$kat]=$data['text'];
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
		$prefix=$this->getInvoicePrefix();
		$next=$this->readNextNumber();
		
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
		
		return $prefix.$format;
	}
	
	function createBillFor($timetrack,$customer){
		$template=$this->getTemplate();
		$adr_id=$this->getAdressForCustomer($customer);
		
		$texts=$this->getDefaultInvoiceTexts();
		$belegnummer=$this->nextNumber($customer);

		$stmt=$this->conn->prepare('SELECT * FROM GRLBM');
		$stmt->execute();
		while ($data = $stmt->fetch()){
			print_r($data);
		}
		die();
		$user = $this->open3aUser;		
		$stmt = $this->conn->prepare('INSERT INTO Auftrag (AdresseID, auftragdatum, kundennummer, UserID, AuftragVorlage, AuftragStammdatenID) VALUES (?,?,?,?,?,?)');
		$stmt->execute(array($adr_id,time(),$customer,$this->open3aUser,$template,1));
		if ($res=$stmt->execute()){
			$id=$this->conn->lastInsertId();
			
			$stmt = $this->conn->prepare('INSERT INTO GRLBM (AuftragID,datum,isR,nummer,textbausteinOben,textbausteinUnten,zahlungsbedingungen,lieferDatum,prefix,textbausteinObenID,textbausteinUntenID,zahlungsbedingungenID,GRLBMpayedVia)');
//			$stmt->execute($id,time(),1,/*TODO*/,'transfer');
		}
	}
}

?>
