# Устройства Bluetooth (модуль для MajorDoMo)

Модуль отслеживания заданных Bluetooth устройств в зоне доступа. Доступны методы поиска с помощью сканирования радиоэфира, прямого подключения, PING запросов (только для Linux) и гибридный метод, который объединяет все вышеперечисленное. Уведомления об изменении состояний необходимо обрабатывать с помощью методов Found/Lost объектов класса BluetoothDevices.
Для корректной работы модуля необходимы последние версии пакетов BluetoothView >= 1.66 (для Windows систем) и bluez >= 5.43 (для Linux систем).
Более подробную информацию вы можете получить на форуме https://majordomo.smartliving.ru/forum/viewtopic.php?f=5&t=5686

# Bluetooth devices (module for MajorDoMo)

Tracking module for specified Bluetooth devices in the access area. Search methods are available via radio scan, direct connect, PING requests (Linux only), and a hybrid method that combines all of the above. State change notifications must be processed using the Found/Lost methods of the BluetoothDevices class objects.
The latest versions of BluetoothView >= 1.66 (for Windows systems) and bluez >= 5.43 (for Linux systems) packages are required for the module to work correctly.
More information can be found on the forum https://majordomo.smartliving.ru/forum/viewtopic.php?f=5&t=5686
