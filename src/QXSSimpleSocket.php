<?php

namespace QXS\WorkerPool;


class SimpleSocket {
	protected $socket=null;
	public $annotation=array();
	
	public function __construct($socket) {
		if(!is_resource($socket) && strtolower(@get_resource_type($socket)!='socket')) {
			throw new \InvalidArgumentException('Socket resource is required!');
		}
		$this->socket=$socket;
	}

	public function __destruct() {
		@socket_close($this->socket);
	}

	public static function select(array $readSockets=array(), array $writeSockets=array(), array $exceptSockets=array(), $sec=0, $usec=0) {
		$read=array();
		$write=array();
		$except=array();
		$readTbl=array();
		$writeTbl=array();
		$exceptTbl=array();
		foreach($readSockets as $val) {
			if(is_a($val, '\QXS\WorkerPool\SimpleSocket')) {
				$read[]=$val->getSocket();
				$readTbl[$val->getResourceId()]=$val;
			}
		}
		foreach($writeSockets as $val) {
			if(is_a($val, '\QXS\WorkerPool\SimpleSocket')) {
				$write[]=$val->getSocket();
				$writeTbl[$val->getResourceId()]=$val;
			}
		}
		foreach($exceptSockets as $val) {
			if(is_a($val, '\QXS\WorkerPool\SimpleSocket')) {
				$except[]=$val->getSocket();
				$exceptTbl[$val->getResourceId()]=$val;
			}
		}

		$out=array();
		$out['read']=array();
		$out['write']=array();
		$out['except']=array();

		$sockets=socket_select($read, $write, $except, $sec, $usec);
		if($sockets===false) {
			return $out;
		}

		foreach($read as $val) {
			$out['read'][]=$readTbl[intval($val)];
		}
		foreach($write as $val) {
			$out['write'][]=$writeTbl[intval($val)];
		}
		foreach($except as $val) {
			$out['except'][]=$exceptTbl[intval($val)];
		}

		return $out;
	}

	public function getResourceId() {
		return intval($this->socket);
	}
	public function getSocket() {
		return $this->socket;
	}

	/**
	 * Write the data to the socket in a predetermined format
	 */
	public function hasData($sec=0, $usec=0) {
		$sec=(int)$sec;
		$usec=(int)$usec;
		if($sec<0) $sec=0;
		if($usec<0) $usec=0;

		$read=array($this->socket);
		$write=array();
		$except=array();
		$sockets=socket_select($read, $write, $except, $sec, $usec);

		if($sockets===false) {
			return false;
		}
		return $sockets>0;
	}

	/**
	 * Write the data to the socket in a predetermined format
	 */
	public function send($data) {
		$serialized=serialize($data);
		$hdr=pack('N', strlen($serialized));    // 4 byte length
		$buffer=$hdr.$serialized;
		unset($serialized);
		unset($hdr);
		$total=strlen($buffer);
		$sent=0;
		while($total > 0) {
			$sent=@socket_write($this->socket, $buffer);
			if($sent===false) {
				throw new \RuntimeException('Sending failed with: '.socket_strerror(socket_last_error()));
				break;
			}
			$total-=$sent;
			$buffer=substr($buffer, $sent);
		}
	}

	/**
	 * Read a data packet from the socket in a predetermined format.
	 *
	 */
	public function receive() {
		// read 4 byte length first
		$hdr='';
		do {
			$read=socket_read($this->socket, 4-strlen($hdr));
			if($read===false || $read==='' || $read===null) {
				return null;
			}
			$hdr.=$read;
		} while(strlen($hdr)<4);

		list($len)=array_values(unpack("N", $hdr));

		// read the full buffer
		$buffer='';
		do {
			$read=socket_read($this->socket, $len-strlen($buffer));
			if($read===false || $read=='') {
				return null;
			}
			$buffer.=$read;
		} while(strlen($buffer)<$len);

		$data=unserialize($buffer);
		return $data;
	}
}
