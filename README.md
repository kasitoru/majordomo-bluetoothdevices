# Устройства Bluetooth (модуль для MajorDoMo)

Модуль отслеживания заданных Bluetooth устройств в зоне доступа. Доступны методы поиска с помощью сканирования радиоэфира, PING запросов (только для Linux) и запросов на подключение (только для Windows). Уведомления об изменении состояний необходимо обрабатывать с помощью методов Found/Lost объектов класса BluetoothDevices.
Для корректной работы модуля необходимы последние версии пакетов BluetoothView >= 1.66 (для Windows систем) и bluez >= 5.50 (для Linux систем).

Tracking module for specified Bluetooth devices in the access area. Search methods are available using radio scan, PING requests (Linux only), and connection requests (Windows only). State change notifications must be processed using the Found/Lost methods of the BluetoothDevices class objects.
