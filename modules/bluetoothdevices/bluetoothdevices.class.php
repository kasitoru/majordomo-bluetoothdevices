<?php
/*
	Bluetooth Devices module for MajorDoMo
	Author: Sergey Avdeev <avdeevsv91@gmail.com>
	URL: https://github.com/kasitoru/majordomo-bluetoothdevices
*/

class bluetoothdevices extends module {

	// Constructor
	function bluetoothdevices() {
		$this->name = 'bluetoothdevices';
		$this->title = 'Устройства Bluetooth';
		$this->module_category = '<#LANG_SECTION_DEVICES#>';
		$this->classname = 'BluetoothDevices';
		$this->checkInstalled();
	}

	// Get product version (for exe files)
	private function get_product_version($file) {
		if($data = @file_get_contents($file)) {
			$key = "V\x00S\x00_\x00V\x00E\x00R\x00S\x00I\x00O\x00N\x00_\x00I\x00N\x00F\x00O\x00\x00\x00";
			$key_pos = strpos($data, $key);
			if($key_pos === FALSE) {
				return '';
			}
			$data = substr($data, $key_pos);
			$key = "P\x00r\x00o\x00d\x00u\x00c\x00t\x00V\x00e\x00r\x00s\x00i\x00o\x00n\x00\x00\x00";
			$key_pos = strpos($data, $key);
			if($key_pos === FALSE) {
				return '';
			}
			$version = '';
			$key_pos = $key_pos + strlen($key);
			for($i=$key_pos; $data[$i]!="\x00"; $i+=2) {
				$version .= $data[$i];
			}
			$version = str_replace(',', '.', $version);
			return trim($version);
		} else {
			return NULL;
		}
	}

	// Compare programs versions
	private function compare_programs_versions($first, $second) {
		$fvc = substr_count($first, '.');
		$svc = substr_count($second, '.');
		if($fvc > $svc) {
			$dvc = $fvc;
		} else {
			$dvc = $svc;
		}
		$fvf= explode('.', $first);
		$svf = explode('.', $second);
		for($i=0;$i<=$dvc;$i++) {
			if(intval($svf[$i]) > intval($fvf[$i])) {
				return TRUE;
			} elseif(intval($svf[$i]) < intval($fvf[$i])) {
				return FALSE;
			}
		}
		return FALSE;
	}
	
	// Check programs version
	private function check_programs_version($exe, $version) {
		$results = FALSE;
		if($product_version = $this->get_product_version($exe)) {
			if(!$this->compare_programs_versions($product_version, $version)) {
				$results = TRUE;
			}
		}
		return $results;
	}
	
	// Bluetooth: reset
	private function bluetooth_reset(&$messages=array()) {
		$results = FALSE;
		if(IsWindowsOS()) {
			// Windows
			$messages[] = array('time' => time(), 'text' => 'Reset bluetooth is not supported for Windows OS!');
		} else {
			// Linux
			$messages[] = array('time' => time(), 'text' => 'Reset bluetooth...');
			exec(($this->config['sudo']?'sudo ':'').'hciconfig hci0 down; sudo hciconfig hci0 up');
			setGlobal('bluetoothdevices_resetTime', time());
			$results = TRUE;
		}
		return $results;
	}
	
	// Bluetooth: scan
	private function bluetooth_scan(&$messages=array()) {
		$results = array();
		if(IsWindowsOS()) {
			// Windows
			// FIXME: does not find BLE devices
			// FIXME: finds an offline device if it is paired
			$devices_file = SERVER_ROOT.'/apps/bluetoothview/devices_'.uniqid().'.txt';
			exec(SERVER_ROOT.'/apps/bluetoothview/bluetoothview.exe /stab "'.$devices_file.'"');
			if(file_exists($devices_file)) {
				if($data = LoadFile($devices_file)) {
					$data = str_replace(chr(0), '', $data);
					$data = str_replace("\r", '', $data);
					$lines = explode("\n", $data);
					$total = count($lines);
					for($i=0; $i<$total; $i++) {
						$fields = explode("\t", $lines[$i]);
						$address = trim(strtolower($fields[2]));
						$name = trim($fields[1]);
						if(!empty($address)) {
							$results[] = array(
								'address'	=> $address,
								'name'		=> $name,
							);
						}
					}
				} else {
					$messages[] = array('time' => time(), 'text' => 'Error opening file "'.$devices_file.'"!');
					$results = FALSE;
				}
				@unlink($devices_file);
			} else {
				$messages[] = array('time' => time(), 'text' => 'Missing file "'.$devices_file.'"!');
				$results = FALSE;
			}
		} else {
			// Linux
			$data = array();
			exec(($this->config['sudo']?'sudo ':'').'hcitool scan | grep ":"', $data);
			exec(($this->config['sudo']?'sudo ':'').'timeout -s INT 10s hcitool lescan | grep ":"', $data);
			$total = count($data);
			for($i=0; $i<$total; $i++) {
				$data[$i] = trim($data[$i]);
				$address = trim(strtolower(substr($data[$i], 0, 17)));
				$name = trim(substr($data[$i], 17));
				if(!empty($address)) {
					$results[] = array(
						'address'	=> $address,
						'name'		=> $name,
					);
				}
			}
		}
		return $results;
	}
	
	// Bluetooth: hybrid
	private function bluetooth_hybrid($address, &$messages=array()) {
		$results = FALSE;
		// Ping
		if(!$results && $this->bluetooth_ping($address, $messages)) {
			$results = TRUE;
		}
		// Discovery
		if(!$results && $this->bluetooth_discovery($address, $messages)) {
			$results = TRUE;
		}
		// Connect
		if(!$results && $this->bluetooth_connect($address, $messages)) {
			$results = TRUE;
		}
		return $results;
	}
	
	// Bluetooth: ping
	private function bluetooth_ping($address, &$messages=array()) {
		$results = FALSE;
		if(IsWindowsOS()) {
			// Windows
			$messages[] = array('time' => time(), 'text' => 'Method "ping" is not supported for Windows OS!');
		} else {
			// Linux
			$data = exec(($this->config['sudo']?'sudo ':'').'l2ping '.$address.' -c1 -f | awk \'/loss/ {print $3}\'');
			if(intval($data) > 0) {
				$results = TRUE;
			}
		}
		return $results;
	}

	// Bluetooth: discovery
	private function bluetooth_discovery($address, &$messages=array()) {
		$devices = $this->bluetooth_scan($messages);
		if(is_array($devices)) {
			foreach($devices as $device) {
				if($device['address'] == $address) {
					return TRUE;
				}
			}
		}
		return FALSE;
	}
	
	// Bluetooth: connect
	private function bluetooth_connect($address, &$messages=array()) {
		$results = FALSE;
		if(IsWindowsOS()) {
			// Windows
			if($this->check_programs_version(SERVER_ROOT.'/apps/bluetoothview/BluetoothView.exe', '1.41')) {
				// BluetoothView version >= 1.41
				exec(SERVER_ROOT.'/apps/bluetoothview/bluetoothview.exe /try_to_connect '.$address, $data, $code);
				if($code == 0) {
					$results = TRUE;
				}
			} else {
				$messages[] = array('time' => time(), 'text' => 'The current version of BluetoothView is lower than required (1.41)!');
			}
		} else {
			// Linux
			$data = exec(($this->config['sudo']?'sudo ':'').'hcitool cc '.$address.' 2>&1');
			if(empty($data)) {
				$results = TRUE;
			}
		}
		return $results;
	}

	// saveParams
	function saveParams($data=0) {
		$p = array();
		if(isset($this->id)) {
			$p['id'] = $this->id;
		}
		if(isset($this->view_mode)) {
			$p['view_mode'] = $this->view_mode;
		}
		if(isset($this->edit_mode)) {
			$p['edit_mode'] = $this->edit_mode;
		}
		if(isset($this->tab)) {
			$p['tab'] = $this->tab;
		}
		return parent::saveParams($p);
	}

	// getParams
	function getParams() {
		global $id, $mode, $view_mode, $edit_mode, $tab;
		if(isset($id)) {
			$this->id = $id;
		}
		if(isset($mode)) {
			$this->mode = $mode;
		}
		if(isset($view_mode)) {
			$this->view_mode = $view_mode;
		}
		if(isset($edit_mode)) {
			$this->edit_mode = $edit_mode;
		}
		if(isset($tab)) {
			$this->tab = $tab;
		}
	}

	// run
	function run() {
		global $session;
		$out = array();
		if($this->action == 'admin') {
			$this->admin($out);
		} else {
			$this->usual($out);
		}
		if(isset($this->owner->action)) {
			$out['PARENT_ACTION'] = $this->owner->action;
		}
		if(isset($this->owner->name)) {
			$out['PARENT_NAME']=$this->owner->name;
		}
		$out['ID'] = $this->id;
		$out['VIEW_MODE'] = $this->view_mode;
		$out['EDIT_MODE'] = $this->edit_mode;
		$out['MODE'] = $this->mode;
		$out['ACTION'] = $this->action;
		$this->data = $out;
		$p = new parser(DIR_TEMPLATES.$this->name.'/'.$this->name.'.html', $this->data, $this);
		$this->result = $p->result;
	}

	// BackEnd
	function admin(&$out) {
		$this->getConfig();
		// Cycle status
		if(time()-intval(getGlobal('cycle_bluetoothdevicesRun')) < 120) {
			$out['CYCLERUN'] = 1;
		} else {
			$out['CYCLERUN'] = 0;
		}
		// OS
		$out['SERVER_ROOT'] = SERVER_ROOT;
		$out['IS_WINDOWS_OS'] = (int)IsWindowsOS();
		// Features
		$out['IS_HYBRID_AVAILABLE']		= (int)!IsWindowsOS();
		$out['IS_PING_AVAILABLE']		= (int)!IsWindowsOS();
		$out['IS_SCAN_AVAILABLE']		= (int)TRUE;
		$out['IS_CONNECT_AVAILABLE']	= (int)TRUE;
		// Views
		if($this->data_source == 'bluetoothdevices' || $this->data_source == '') {
			switch($this->view_mode) {
				case 'settings_bluetoothdevices': // Settings
					$this->settings_bluetoothdevices($out);
					break;
				case 'add_bluetoothdevices': // Add
					$this->add_bluetoothdevices($out);
					break;
				case 'edit_bluetoothdevices': // Edit
					$this->edit_bluetoothdevices($out, $this->id);
					break;
				case 'delete_bluetoothdevices': // Delete
					$this->delete_bluetoothdevices($this->id);
					break;
				default: // List
					$this->list_bluetoothdevices($out);
			}
		}
		// BluetoothView
		$out['BV_UNSUPPORTED_VERSION'] = (int)FALSE;
		if(IsWindowsOS()) {
			$out['BV_UNSUPPORTED_VERSION']	= (int)TRUE;
			if($this->check_programs_version(SERVER_ROOT.'/apps/bluetoothview/BluetoothView.exe', '1.41')) {
				$out['BV_UNSUPPORTED_VERSION'] = (int)FALSE;
			}
		}
	}
	
	// Settings
	function settings_bluetoothdevices(&$out) {
		// Save action
		if($this->edit_mode == 'save') {
			global $sudo, $scanMethod, $scanInterval, $scanTimeout, $resetInterval;
			$this->config['sudo'] = intval($sudo);
			$this->config['scanMethod'] = strtolower($scanMethod);
			$this->config['scanInterval'] = intval($scanInterval);
			$this->config['scanTimeout'] = intval($scanTimeout);
			$this->config['resetInterval'] = intval($resetInterval);
			$this->saveConfig();
			// Redirect
			$this->redirect('?');
		}
		// Current config
		if($out['SUDO'] = $this->config['sudo']) {
			if(exec('sudo echo test') == 'test') {
				$out['SUDO_TEST'] = 1;
			} else {
				$out['SUDO_TEST'] = 0;
			}
		} else {
			// FIXME:
			// hciconfig
			// l2ping
			// hcitool
		}
		$out['SCAN_METHOD'] = $this->config['scanMethod'];
		$out['SCAN_INTERVAL'] = $this->config['scanInterval'];
		$out['SCAN_TIMEOUT'] = $this->config['scanTimeout'];
		$out['RESET_INTERVAL'] = $this->config['resetInterval'];
		// BluetoothView
		if(IsWindowsOS()) {
			$out['IS_CONNECT_AVAILABLE']	= (int)FALSE;
			if($this->check_programs_version(SERVER_ROOT.'/apps/bluetoothview/BluetoothView.exe', '1.41')) {
				$out['IS_CONNECT_AVAILABLE'] = (int)TRUE;
			}
		}
	}

	// Add bluetooth device
	function add_bluetoothdevices(&$out) {
		// Add action
		if($this->edit_mode == 'add') {
			global $address, $description, $user;
			$address = strtolower($address);
			// Validate address
			if(!preg_match('/^([a-f0-9]{2}:){5}[a-f0-9]{2}$/ims', $address)) {
				$out['ERROR_TEXT'] = 'Необходимо указать корректный адрес Bluetooth устройства!';
			}
			// Generate object name
			$object_name = 'btdev_'.substr(md5($address), 0, 8);
			// Check the existence of the object
			if(getObject($this->classname.'.'.$object_name)) {
				$out['ERROR_TEXT'] = 'Данное устройство уже присутствует в списке!';
			}
			// Add new object
			if(empty($out['ERROR_TEXT'])) {
				if($object_id = addClassObject($this->classname, $object_name)) {
					// Set description for object
					$object = SQLSelectOne('SELECT * FROM `objects` WHERE `ID` = '.$object_id);
					$object['DESCRIPTION'] = $description;
					SQLUpdate('objects', $object);
					// Set properties
					if($object = getObject($this->classname.'.'.$object_name)) {
						$object->setProperty('address', $address);
						$object->setProperty('online', 0);
						$object->setProperty('lastTimestamp', 0);
						$object->setProperty('user', $user);
						// Redirect
						$this->redirect('?');
					}
				}
			} else {
				$out['ADDRESS'] = $address;
				$out['DESCRIPTION'] = $description;
				$out['USER'] = $user;
			}
		}
		$out['USERS'] = SQLSelect('SELECT `ID`, `NAME` FROM `users` ORDER BY `NAME`');
	}
	
	// Edit bluetooth device
	function edit_bluetoothdevices(&$out, $id) {
		// Get object data
		$object_data = SQLSelectOne('SELECT * FROM `objects` WHERE `ID` = '.$id);
		if($object = getObject($this->classname.'.'.$object_data['TITLE'])) {
			$out['ADDRESS'] = strtolower($object->getProperty('address'));
			$out['DESCRIPTION'] = $object->description;
			$out['USER'] = $object->getProperty('user');
		}
		// Edit action
		if($this->edit_mode == 'edit') {
			global $address, $description, $user;
			$address = strtolower($address);
			// Check object
			if(!$object->id) {
				$out['ERROR_TEXT'] = 'Невозможно получить информацию о выбранном устройстве!';
			}
			// Validate address
			if(!preg_match('/^([a-f0-9]{2}:){5}[a-f0-9]{2}$/ims', $address)) {
				$out['ERROR_TEXT'] = 'Необходимо указать корректный адрес Bluetooth устройства!';
			}
			// Save
			if(empty($out['ERROR_TEXT'])) {
				// Set description for object
				$object_data['DESCRIPTION'] = $description;
				SQLUpdate('objects', $object_data);
				// Set properties
				$object->setProperty('address', $address);
				$object->setProperty('user', $user);
				// Redirect
				$this->redirect('?');
			} else {
				$out['ADDRESS'] = $address;
				$out['DESCRIPTION'] = $description;
				$out['USER'] = $user;
			}
		}
		$out['USERS'] = SQLSelect('SELECT `ID`, `NAME` FROM `users` ORDER BY `NAME`');
	}
	
	// Delete bluetooth device
	function delete_bluetoothdevices($id) {
		SQLExec("DELETE FROM `history` WHERE `OBJECT_ID` = $id");
		SQLExec("DELETE FROM `methods` WHERE `OBJECT_ID` = $id");
		SQLExec("DELETE FROM `pvalues` WHERE `OBJECT_ID` = $id");
		SQLExec("DELETE FROM `properties` WHERE `OBJECT_ID` = $id");
		SQLExec("DELETE FROM `objects` WHERE `ID` = $id");
		$this->redirect('?');
	}

	// List of bluetooth devices
	function list_bluetoothdevices(&$out) {
		if($objects = getObjectsByClass($this->classname)) {
			foreach($objects as $obj) {
				$obj = getObject($this->classname.'.'.$obj['TITLE']);
				// Get username
				$user = SQLSelectOne('SELECT `USERNAME`, `NAME` FROM `users` WHERE `ID` = '.intval($obj->getProperty('user')));
				// Get lastTimestamp
				$lastTimestamp = intval($obj->getProperty('lastTimestamp'));
				$out['DEVICES'][] = array(
					'ID'			=> $obj->id,
					'OBJECT'		=> $obj->object_title,
					'CLASS_ID'		=> $obj->class_id,
					'DESCRIPTION'	=> $obj->description,
					'ONLINE'		=> $obj->getProperty('online'),
					'ADDRESS'		=> strtolower($obj->getProperty('address')),
					'TIMESTAMP'		=> ($lastTimestamp?date('d.m.Y в H:i', $lastTimestamp):''),
					'USER'			=> ($user?"$user[USERNAME] ($user[NAME])":''),
				);
			}
		}
	}

	// FrontEnd
	function usual(&$out) {
		global $session, $ajax, $command;
		if(isset($ajax)) {
			// JSON default
			$json = array(
				'command'			=> $command,
				'success'			=> FALSE,
				'message'			=> NULL,
				'data'				=> NULL,
			);
			// Command
			switch($command) {
				case 'scan':
					$messages = array();
					$data = $this->bluetooth_scan($messages);
					if(is_array($data)) {
						$json['success'] = TRUE;
						$json['message'] = 'OK';
						$json['data'] = $data;
					} else {
						$json['success'] = FALSE;
						if(count($messages) > 0) {
							foreach($messages as $message) {
								$json['message'] .= $message['text'].' ';
							}
							$json['message'] = trim($json['message']);
						} else {
							$json['message'] = 'bluetooth_scan() error!';
						}
					}
					break;
				default:
					$json['success'] = FALSE;
					$json['message'] = 'Unknown command!';
			}
			// Return json
			if(!$this->intCall) {
				$session->save();
				die(json_encode($json));
			}
		}
	}
	
	// Cycle
	function processCycle() {
		if($objects = getObjectsByClass($this->classname)) {
			// All objects from $this->classname class
			foreach($objects as $obj) {
				$messages = array();
				// Current object
				$obj = getObject($this->classname.'.'.$obj['TITLE']);
				$address = strtolower($obj->getProperty('address'));

				// Search device
				$is_found = FALSE;
				switch(strtolower($this->config['scanMethod'])) {
					case 'hybrid': // Hybrid method
						$is_found = $this->bluetooth_hybrid($address, $messages);
						break;
					case 'ping': // Ping
						$is_found = $this->bluetooth_ping($address, $messages);
						break;
					case 'discovery': // Discovery
						$is_found = $this->bluetooth_discovery($address, $messages);
						break;
					case 'connect': // Connect
						$is_found = $this->bluetooth_connect($address, $messages);
						break;
					default:
						$messages[] = array('time' => time(), 'text' => 'Unknown method "'.$this->config['scanMethod'].'"!');
				}
				// Print messages
				foreach($messages as $message) {
					echo date('[d/m/Y H:i:s]:', $message['time']).' '.$message['text'].PHP_EOL;
				}
				// Update object
				if($is_found) {
					$obj->setProperty('lastTimestamp', time());
					if($obj->getProperty('online') == 0) {
						echo date('Y/m/d H:i:s').' Device found: '.$address.PHP_EOL;
						$obj->setProperty('online', 1);
						$obj->callMethod('Found', array('ADDRESS'=>$address));
					}
				} else {
					$lastTimestamp = intval($obj->getProperty('lastTimestamp'));
					if($obj->getProperty('online') == 1) {
						if(time()-$lastTimestamp > intval($this->config['scanTimeout'])) {
							echo date('Y/m/d H:i:s').' Device lost: '.$address.PHP_EOL;
							$obj->setProperty('online', 0);
							$obj->callMethod('Lost', array('ADDRESS'=>$address));
						}
					}
				}
			}

			// Reset bluetooth
			if((intval($this->config['resetInterval']) >= 0) && (time()-intval(getGlobal('bluetoothdevices_resetTime')) > intval($this->config['resetInterval']))) {
				$this->bluetooth_reset($messages);
			}

		}
	}

	// Install
	function install($parent_name='') {
		// Class
		addClass($this->classname);
		// Method: Found
		if($meth_id = addClassMethod($this->classname, 'Found', '')) {
			$property = SQLSelectOne('SELECT * FROM `methods` WHERE `ID` = '.$meth_id);
			$property['DESCRIPTION'] = 'Устройство появилось в зоне доступа';
			SQLUpdate('methods', $property);
		}
		// Method: Lost
		if($meth_id = addClassMethod($this->classname, 'Lost', '')) {
			$property = SQLSelectOne('SELECT * FROM `methods` WHERE `ID` = '.$meth_id);
			$property['DESCRIPTION'] = 'Устройство пропало из зоны доступа';
			SQLUpdate('methods', $property);
		}
		// Property: Online
		if($prop_id = addClassProperty($this->classname, 'online', 0)) {
			$property = SQLSelectOne('SELECT * FROM `properties` WHERE `ID` = '.$prop_id);
			$property['DESCRIPTION'] = 'Состояние доступности';
			SQLUpdate('properties', $property);
		}
		// Property: Address
		if($prop_id = addClassProperty($this->classname, 'address', 0)) {
			$property = SQLSelectOne('SELECT * FROM `properties` WHERE `ID` = '.$prop_id);
			$property['DESCRIPTION'] = 'Адрес устройства';
			SQLUpdate('properties', $property);
		}
		// Property: lastTimestamp
		if($prop_id = addClassProperty($this->classname, 'lastTimestamp', 0)) {
			$property = SQLSelectOne('SELECT * FROM `properties` WHERE `ID` = '.$prop_id);
			$property['DESCRIPTION'] = 'Время последнего онлайна';
			SQLUpdate('properties', $property);
		}
		// Property: User
		if($prop_id = addClassProperty($this->classname, 'user', 0)) {
			$property = SQLSelectOne('SELECT * FROM `properties` WHERE `ID` = '.$prop_id);
			$property['DESCRIPTION'] = 'Пользователь устройства';
			SQLUpdate('properties', $property);
		}
		// Save default config
		$this->getConfig();
		if(!$this->config) {
			$this->config['sudo'] = (int)TRUE;
			if(IsWindowsOS()) {
				// Windows
				$this->config['scanMethod'] = 'discovery';
				if($this->check_programs_version(SERVER_ROOT.'/apps/bluetoothview/BluetoothView.exe', '1.41')) {
					$this->config['scanMethod'] = 'connect';
				}
			} else {
				// Linux
				$this->config['scanMethod'] = 'hybrid';
			}
			$this->config['scanInterval'] = (int)60;
			$this->config['scanTimeout'] = (int)5*60;
			$this->config['resetInterval'] = (int)2*60*60;
			$this->saveConfig();
		}
		// Global property
		setGlobal('cycle_bluetoothdevicesDisabled', 0);
		setGlobal('cycle_bluetoothdevicesAutoRestart', 1);
		setGlobal('cycle_bluetoothdevicesRun', 0);
		setGlobal('cycle_bluetoothdevicesControl', 'start');
		setGlobal('bluetoothdevices_resetTime', 0);
		// Parent install
		parent::install($parent_name);
	}

	// Uninstall
	function uninstall() {
		// Stop cycle
		setGlobal('cycle_bluetoothdevicesControl', 'stop');
		// Table: classes
		$classes = array();
		if($query = SQLSelect("SELECT `ID` FROM `classes` WHERE `TITLE` = '".$this->classname."'")) {
			foreach($query as $item) {
				$classes[] = (int)$item['ID'];
			}
			$classes = array_filter($classes);
			// Delete classes
			if(!empty($classes)) {
				SQLExec('DELETE FROM `classes` WHERE `ID` IN ('.implode(', ', $classes).')');
			}
		}
		// Table: objects
		$objects = array();
		if($query = SQLSelect("SELECT `ID` FROM `objects` WHERE ".(!empty($classes)?'`CLASS_ID` IN ('.implode(', ', $classes).') OR ':'')."`TITLE` LIKE 'btdev_%'")) {
			foreach($query as $item) {
				$objects[] = (int)$item['ID'];
			}
			$objects = array_filter($objects);
			// Delete objects
			if(!empty($objects)) {
				SQLExec('DELETE FROM `objects` WHERE `ID` IN ('.implode(', ', $objects).')');
			}
		}
		// Table: methods
		$methods = array();
		if($query = SQLSelect("SELECT `ID` FROM `methods` WHERE ".(!empty($objects)?'`OBJECT_ID` IN ('.implode(', ', $objects).') OR ':'')." ".(!empty($classes)?'`CLASS_ID` IN ('.implode(', ', $classes).') OR ':'')."0")) {
			foreach($query as $item) {
				$methods[] = (int)$item['ID'];
			}
			$methods = array_filter($methods);
			// Delete methods
			if(!empty($methods)) {
				SQLExec('DELETE FROM `methods` WHERE `ID` IN ('.implode(', ', $methods).')');
			}
		}
		// Table: properties
		$properties = array();
		if($query = SQLSelect("SELECT `ID` FROM `properties` WHERE ".(!empty($classes)?'`CLASS_ID` IN ('.implode(', ', $classes).') OR ':'')." ".(!empty($objects)?'`OBJECT_ID` IN ('.implode(', ', $objects).') OR ':'')."0")) {
			foreach($query as $item) {
				$properties[] = (int)$item['ID'];
			}
			$properties = array_filter($properties);
			// Delete properties
			if(!empty($properties)) {
				SQLExec('DELETE FROM `properties` WHERE `ID` IN ('.implode(', ', $properties).')');
			}
		}
		// Table: pvalues
		$pvalues = array();
		if($query = SQLSelect("SELECT `ID` FROM `pvalues` WHERE ".(!empty($properties)?'`PROPERTY_ID` IN ('.implode(', ', $properties).') OR ':'')." ".(!empty($objects)?'`OBJECT_ID` IN ('.implode(', ', $objects).') OR ':'')."`PROPERTY_NAME` LIKE 'btdev_%'")) {
			foreach($query as $item) {
				$pvalues[] = (int)$item['ID'];
			}
			$pvalues = array_filter($pvalues);
			// Delete pvalues
			if(!empty($pvalues)) {
				SQLExec('DELETE FROM `pvalues` WHERE `ID` IN ('.implode(', ', $pvalues).')');
			}
		}
		// Table: history
		$history = array();
		if($query = SQLSelect("SELECT `ID` FROM `history` WHERE ".(!empty($objects)?'`OBJECT_ID` IN ('.implode(', ', $objects).') OR ':'')." ".(!empty($methods)?'`METHOD_ID` IN ('.implode(', ', $methods).') OR ':'')." ".(!empty($pvalues)?'`VALUE_ID` IN ('.implode(', ', $pvalues).') OR ':'')."0")) {
			foreach($query as $item) {
				$history[] = (int)$item['ID'];
			}
			$history = array_filter($history);
			// Delete history
			if(!empty($history)) {
				SQLExec('DELETE FROM `history` WHERE `ID` IN ('.implode(', ', $history).')');
			}
		}
		// Parent uninstall
		parent::uninstall();
	}

	// dbInstall
	function dbInstall($data) {
		parent::dbInstall($data);
	}

}

?>
