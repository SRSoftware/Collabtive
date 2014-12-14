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
	}
	
	function getTemplate(){
		$stmt = $this->conn->prepare('SELECT ownTemplate FROM Stammdaten');
		$res=$stmt->execute();
		if ($data = $stmt->fetch()) {
			return $data['ownTemplate'];
		}
		
	}
	
	function createBillFor($timetrack,$customer){
		$template=$this->getTemplate();
		$
		$stmt = $this->conn->prepare('INSERT INTO Auftrag (AdresseID, auftragdatum, kundennummer, ')
		
		$stmt = $this->conn->prepare('SELECT * FROM Auftrag');
		$res=$stmt->execute();
		while ($projekt = $stmt->fetch()) {
			print_r($projekt);
    }
		
	}
}

?>
