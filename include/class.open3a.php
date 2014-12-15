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
	
	function getTemplate(){
		$stmt = $this->conn->prepare('SELECT ownTemplate FROM Stammdaten');
		$res=$stmt->execute();		
		if ($data = $stmt->fetch()) {
			$stmt->closeCursor();
			return $data['ownTemplate'];
		}
		$stmt->closeCursor();
	}
	
	function getAdressForCustomer($customer_id){
		$stmt = $this->conn->prepare('SELECT AdresseID FROM Kappendix WHERE kundennummer = ?');
		$res=$stmt->execute(array($customer_id));		
		if ($data = $stmt->fetch()) {
			$stmt->closeCursor();
			return $data['AdresseID'];
		}
		$stmt->closeCursor();
	}
	
	function createBillFor($timetrack,$customer){
		$template=$this->getTemplate();
		$adr_id=$this->getAdressForCustomer($customer);
		$user = $this->open3aUser;		
		$stmt = $this->conn->prepare('INSERT INTO Auftrag (AdresseID, auftragdatum, kundennummer, UserID, AuftragVorlage, AuftragStammdatenID) VALUES (?,?,?,?,?,?)');
		$stmt->execute(array($adr_id,time(),$customer,$this->open3aUser,$template,1));
		if ($res=$stmt->execute()){
			$id=$this->conn->lastInsertId();
			
			//$stmt = $this->conn->prepare('INSERT INTO GRLBM (AuftragID,datum,isR)');
		}
	}
}

?>
