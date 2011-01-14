<?php

define("SERVERDATA_EXECCOMMAND",2);
define("SERVERDATA_AUTH",3);

class RCon {
	var $Password;
	var $Server;
	var $Port = 27015;
	var $_Sock = null;
	var $_Id = 0;

	//constructor
	function __construct ($server, $port, $password) {
		$this->Password = $password;
		$this->Server = $server;
		$this->Port = $port;
		$tmpSock=@fsockopen($this->Server, $this->Port, $errno, $errstr, 5);
		if($tmpSock){
			$this->_Sock =$tmpSock;
			$this->_Set_Timeout($this->_Sock,1,500);
		}
	}

	function authenticate(){		
		$PackID = $this->_Write(SERVERDATA_AUTH, $this->Password);
		$ret = $this->_PacketRead();
		if ($ret[1]['ID'] == -1) {
			return false;
		}else{
			return true;
		}
	}

	function _Set_Timeout(&$res,$s,$m=0) {
		if (version_compare(phpversion(),'4.3.0','<')) {
			return socket_set_timeout($res,$s,$m);
		}
		return stream_set_timeout($res,$s,$m);
	}


	function getStatus(){
		$status = $this->rconCommand("status");

		//format global server info
		$line = explode("\n", $status);

		$result["hostname"] = trim(substr($line[0], strpos($line[0], ":") + 1));
		$result['map'] = trim(substr($line[3], strpos($line[3], ":") + 1));
		$result['players'] = trim(substr($line[4], strpos($line[4], ":") + 1));
		$ip = explode(" ", trim(substr($line[2], strpos($line[2], ":") + 1)));

		$result['ip'] =$ip[0];
		$playerInfo = explode(" ", $result['players']);
		$result['playercount'] = $playerInfo[0];

		//nasty extra line as this isn't by default included in the l4d status
		$result['difficulty']=$this->parseSetting($this->rconCommand("z_difficulty"));

		//format player info
		for($i = 0; $i < $result['playercount']; $i++){
			//get player line items
			$tmp = explode(" ", trim($line[$i+7]));
			$name= explode('"', trim($line[$i+7]));
			preg_match('/((\d+) (\d+) (".*") (STEAM_\d+:\d+:\d+) (.*) (\d+) (\d+) (\w+) (\d+) (\d+.\d+.\d+.\d+):(\d+))/',$line[$i+7],$matches);

			$result['player'.($i+1)]["id"] = $matches[2];
			$result['player'.($i+1)]["name"] = $matches[4];
			$result['player'.($i+1)]["uniqid"] = $matches[5];
			$result['player'.($i+1)]["connected"] = $matches[6];
			$result['player'.($i+1)]["ping"] = $matches[7];
			$result['player'.($i+1)]["state"] = $matches[9];
			$result['player'.($i+1)]["ip"] = $matches[11];
		} 

		//return formatted result
		return $result;		
	}


	function _Write($cmd, $s1='', $s2='') {
		// Get and increment the packet id
		$id = ++$this->_Id;

		// Put our packet together
		$data = pack("VV",$id,$cmd).$s1.chr(0).$s2.chr(0);

		// Prefix the packet size
		$data = pack("V",strlen($data)).$data;

		// Send packet
		fwrite($this->_Sock,$data,strlen($data));

		// In case we want it later we'll return the packet id
		return $id;
	}

	function _PacketRead() {
		//Declare the return array
		$retarray = array();

		//Fetch the packet size
		while ($size = @fread($this->_Sock,4)) {
			$size = unpack('V1Size',$size);
			//Work around valve breaking the protocol
			if ($size["Size"] > 4096) {
				//pad with 8 nulls
				$packet = "\x00\x00\x00\x00\x00\x00\x00\x00".fread($this->_Sock,4096);
			} else {
				//Read the packet back
				$packet = fread($this->_Sock, $size["Size"]);
			}

			array_push($retarray,unpack("V1ID/V1Response/a*S1/a*S2",$packet));
		}
		return $retarray;
	}

	function getResponse() {		
		$Packets = $this->_PacketRead();

		foreach($Packets as $pack) {
			if (isset($ret[$pack['ID']])) {
				$ret[$pack['ID']]['S1'] .= $pack['S1'];
				$ret[$pack['ID']]['S2'] .= $pack['S1'];
			} else {
				$ret[$pack['ID']] = array('Response' => $pack['Response'],'S1' => $pack['S1'],'S2' =>  $pack['S2'],);
			}
		}

		return $ret;
	}

	function sendCommand($Command) {
		$this->_Write(SERVERDATA_EXECCOMMAND,trim($Command),'');
	}

	function rconCommand($Command) {
		$this->sendCommand($Command);

		$ret = $this->getResponse();

		return $this->parseSetting($ret[$this->_Id]['S1']);
	}

	function parseSetting($settingString){
		$settingString=eregi_replace('^(.*) \=\ "([^\"]*)".*$','\\2', $settingString);
		return $settingString;
	}
}
?>
