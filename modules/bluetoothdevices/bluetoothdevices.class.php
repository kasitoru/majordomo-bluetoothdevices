<?php
/*
	Bluetooth Devices module for MajorDoMo
	Author: Sergey Avdeev <thesoultaker48@gmail.com>
	URL: https://github.com/thesoultaker48/majordomo-bluetoothdevices
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
		//$out['ID'] = $this->id; FIXME
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
		// Cycle status
		if(time()-intval(getGlobal('cycle_bluetoothdevicesRun')) < 120) {
			$out['CYCLERUN'] = 1;
		} else {
			$out['CYCLERUN'] = 0;
		}
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
	}
	
	// Settings
	function settings_bluetoothdevices(&$out) {
		$this->getConfig();
		$scanMethod = $this->config['scanMethod'];
		$scanInterval = $this->config['scanInterval'];
		$scanTimeout = $this->config['scanTimeout'];
		$resetInterval = $this->config['resetInterval'];
		
		// Save action
		if($this->edit_mode == 'save') {
			global $scanMethod, $scanInterval, $scanTimeout, $resetInterval;

			$this->config['scanMethod'] = strtolower($scanMethod);
			$this->config['scanInterval'] = intval($scanInterval);
			$this->config['scanTimeout'] = intval($scanTimeout);
			$this->config['resetInterval'] = intval($resetInterval);
			$this->saveConfig();
			
			$this->redirect('?');
		}

		$out['SCAN_METHOD'] = $scanMethod;
		$out['SCAN_INTERVAL'] = $scanInterval;
		$out['SCAN_TIMEOUT'] = $scanTimeout;
		$out['RESET_INTERVAL'] = $resetInterval;
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
		global $session;
	}
	
	// Cycle
	function processCycle() {
		$this->getConfig();
		if($objects = getObjectsByClass($this->classname)) {
			// All objects from $this->classname class
			foreach($objects as $obj) {
				// Current object
				$obj = getObject($this->classname.'.'.$obj['TITLE']);
				$address = strtolower($obj->getProperty('address'));
				// Search device
				$is_found = false;
				if(!IsWindowsOS()) {
					// Linux
					if(time()-intval(getGlobal('bluetoothdevices_resetTime') > intval($this->config['resetInterval']))) {
						// Reset bluetooth
						echo date('Y/m/d H:i:s').' Reset bluetooth'.PHP_EOL;
						exec('sudo hciconfig hci0 down; sudo hciconfig hci0 up');
						setGlobal('bluetoothdevices_resetTime', time());
					}
					if(strtolower($this->config['scanMethod']) == 'ping') {
						// Ping
						$result = exec(str_replace('%ADDRESS%', $address, 'sudo l2ping %ADDRESS% -c10 -f | awk \'/loss/ {print $3}\''));
						if(intval($result) > 0) {
							$is_found = true;
						}
					} elseif(strtolower($this->config['scanMethod']) == 'discovery') {
						// Discovery
						$data = array();
						exec('sudo hcitool scan | grep ":"', $data);
						exec('timeout -s INT 30s hcitool lescan | grep ":"', $data);
						$total = count($data);
						for($i=0; $i<$total; $i++) {
							$data[$i] = trim($data[$i]);
							if(!$data[$i]) {
								continue;
							}
							if(strtolower(substr($data[$i], 0, 17)) == $address) {
								$is_found = true;
								break;
							}
						}
					} elseif(strtolower($this->config['scanMethod']) == 'connect') { // FIXME
						// Connect
						echo date('Y/m/d H:i:s').' Method is not supported for Linux OS: '.$this->config['scanMethod'].PHP_EOL;
					} else {
						// Unknown
						echo date('Y/m/d H:i:s').' Unknown method: '.$this->config['scanMethod'].PHP_EOL;
					}
				} else {
					// Windows
					if(strtolower($this->config['scanMethod']) == 'ping') { // FIXME
						// Ping
						echo date('Y/m/d H:i:s').' Method is not supported for Windows OS: '.$this->config['scanMethod'].PHP_EOL;
					} elseif(strtolower($this->config['scanMethod']) == 'discovery') {
						// Discovery
						$devices_file = SERVER_ROOT.'/apps/bluetoothview/devices.txt';
						unlink($devices_file);
						exec(SERVER_ROOT.'/apps/bluetoothview/bluetoothview.exe /stab '.$devices_file);
						if(file_exists($devices_file)) {
							if($data = LoadFile($devices_file)) {
								$data = str_replace(chr(0), '', $data);
								$data = str_replace("\r", '', $data);
								$lines = explode("\n", $data);
								$total = count($lines);
								for($i=0; $i<$total; $i++) {
									$fields = explode("\t", $lines[$i]);
									if(strtolower(trim($fields[2])) == $address) {
										$is_found = true;
										break;
									}
								}
							}
						}
					} elseif(strtolower($this->config['scanMethod']) == 'connect') {
						// Connect
						exec(SERVER_ROOT.'/apps/bluetoothview/bluetoothview.exe /try_to_connect '.$address, $data, $code);
						if($code == 0) {
							$is_found = true;
						}
					} else {
						// Unknown
						echo date('Y/m/d H:i:s').' Unknown method: '.$this->config['scanMethod'].PHP_EOL;
					}
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
					if(time()-$lastTimestamp > intval($this->config['scanTimeout'])) {
						echo date('Y/m/d H:i:s').' Device lost: '.$address.PHP_EOL;
						$obj->setProperty('online', 0);
						$obj->callMethod('Lost', array('ADDRESS'=>$address));
					}
				}
			}
		}
	}

	// Install
	function install($parent_name='') {
		parent::install($parent_name);
	}

	// Uninstall
	function uninstall() {
		SQLExec("DELETE FROM `pvalues` WHERE `PROPERTY_ID` IN (SELECT `ID` FROM `properties` WHERE `OBJECT_ID` IN (SELECT `ID` FROM `objects` WHERE `CLASS_ID` = (SELECT `ID` FROM `classes` WHERE `TITLE` = '".$this->classname."')))");
		SQLExec("DELETE FROM `history` WHERE `OBJECT_ID` IN (SELECT `ID` FROM `objects` WHERE `CLASS_ID` = (SELECT `ID` FROM `classes` WHERE `TITLE` = '".$this->classname."'))");
		SQLExec("DELETE FROM `properties` WHERE `OBJECT_ID` IN (SELECT `ID` FROM `objects` WHERE `CLASS_ID` = (SELECT `ID` FROM `classes` WHERE `TITLE` = '".$this->classname."'))");
		SQLExec("DELETE FROM `objects` WHERE `CLASS_ID` = (SELECT `ID` FROM `classes` WHERE `TITLE` = '".$this->classname."')");
		SQLExec("DELETE FROM `methods` WHERE `CLASS_ID` = (SELECT `ID` FROM `classes` WHERE `TITLE` = '".$this->classname."')");	 
		SQLExec("DELETE FROM `classes` WHERE `TITLE` = '".$this->classname."'");
		parent::uninstall();
	}

	// dbInstall
	function dbInstall($data) {
		// Class
		addClass($this->classname);

		// Method: Found
		$meth_id = addClassMethod($this->classname, 'Found', '');
		if($meth_id) {
			$property = SQLSelectOne('SELECT * FROM `methods` WHERE `ID` = '.$meth_id);
			$property['DESCRIPTION'] = 'Устройство появилось в зоне доступа';
			SQLUpdate('methods', $property);
		}
		
		// Method: Lost
		$meth_id = addClassMethod($this->classname, 'Lost', '');
		if($meth_id) {
			$property = SQLSelectOne('SELECT * FROM `methods` WHERE `ID` = '.$meth_id);
			$property['DESCRIPTION'] = 'Устройство пропало из зоны доступа';
			SQLUpdate('methods', $property);
		}

		// Property: Online
		$prop_id = addClassProperty($this->classname, 'online', 0);
		if($prop_id) {
			$property = SQLSelectOne('SELECT * FROM `properties` WHERE `ID` = '.$prop_id);
			$property['DESCRIPTION'] = 'Состояние доступности';
			SQLUpdate('properties', $property);
		}
		
		// Property: Address
		$prop_id = addClassProperty($this->classname, 'address', 0);
		if($prop_id) {
			$property = SQLSelectOne('SELECT * FROM `properties` WHERE `ID` = '.$prop_id);
			$property['DESCRIPTION'] = 'Адрес устройства';
			SQLUpdate('properties', $property);
		}
	
		// Property: lastTimestamp
		$prop_id = addClassProperty($this->classname, 'lastTimestamp', 0);
		if($prop_id) {
			$property = SQLSelectOne('SELECT * FROM `properties` WHERE `ID` = '.$prop_id);
			$property['DESCRIPTION'] = 'Время последнего онлайна';
			SQLUpdate('properties', $property);
		}
		
		// Property: User
		$prop_id = addClassProperty($this->classname, 'user', 0);
		if($prop_id) {
			$property = SQLSelectOne('SELECT * FROM `properties` WHERE `ID` = '.$prop_id);
			$property['DESCRIPTION'] = 'Пользователь устройства';
			SQLUpdate('properties', $property);
		}
		
		// Config
		$this->getConfig();
		$this->config['scanMethod'] = 'discovery';
		$this->config['scanInterval'] = 60; // 1 min
		$this->config['scanTimeout'] = 15*60; // 15 min
		$this->config['resetInterval'] = 2*60*60; // 2 hrs
		$this->saveConfig();
		
		// Global property
		setGlobal('cycle_bluetoothdevicesDisabled', 0);
		setGlobal('cycle_bluetoothdevicesAutoRestart', 1);
		setGlobal('cycle_bluetoothdevicesRun', 0);
		setGlobal('cycle_bluetoothdevicesControl', 'start');
		setGlobal('bluetoothdevices_resetTime', 0);

		parent::dbInstall($data);
	}

}

?>
