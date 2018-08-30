# Устройства Bluetooth (модуль для MajorDoMo)

Модуль отслеживания заданных Bluetooth устройств в зоне доступа. Доступны методы поиска с помощью сканирования радиоэфира, прямого подключения и PING запросов (только для Linux). Уведомления об изменении состояний необходимо обрабатывать с помощью методов Found/Lost объектов класса BluetoothDevices.
Для корректной работы модуля необходимы последние версии пакетов BluetoothView >= 1.66 (для Windows систем) и bluez >= 5.43 (для Linux систем).

Tracking module for specified Bluetooth devices in the access area. The available search methods by using the scanning radio, direct connection and PING (Linux only). State change notifications must be processed using the Found/Lost methods of the BluetoothDevices class objects.
The latest versions of BluetoothView >= 1.66 (for Windows systems) and bluez >= 5.43 (for Linux systems) packages are required for the module to work correctly.
