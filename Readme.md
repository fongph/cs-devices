# Devices

Библиотека управления устройствами и их лимитами.

## Использование

Проверка существования устройства, получение идентификатора устройства
```
#!php
<?php

$manager = new CS\Devices\Manager($db);

// проверка на существоание устройства по сгенерированному идентификатору
$this->deviceExist($devUniqueId);

// получаем id устройства по его сгенерированному идентификатору,
// если такого устройства не удалось найти, будет возвращено false
$deviceId = $this->getDeviceId($devUniqueId);
```

Работа с кодами добавления устройства
```
#!php
<?php

$manager = new CS\Devices\Manager($db);
$manager->setRedisConfig($config);

// генерируем и возвращаем код добавления устройства для пользователя, 
// если код уже был сгенерирован и он присутствует в базе - возвращаем его
try {
    $code = $manager->getUserDeviceAddCode($userId);
} catch (CS\Devices\Manager\DeviceCodeGenerationException $e) {
    // ошибка генерации кода, вероятно все возможные коды сейчас заняты
}

// добавление устройства используя код
// возвращаем числовой идентификатор устройства
// также будут созданы "нулевые" лимиты
try {
    $deviceId = $manager->addDeviceWithCode($deviceUniqueId, $code);
} catch (CS\Devices\Manager\DeviceCodeNotFoundException $e) {
    // код не найден или уже устарел
}
```

Работа с лимитами устройства
```
#!php
<?php

$manager = new CS\Devices\Manager($db);

// проверяем лимиты устройства на некоторою опцию (например добавление СМС)
$allowed = $manager->isDeviceLimitationAllowed($devId, $limitationName);

// уменьшаем количественный лимит (смс, звонки)
// если он безлимитный, то ничего не измениться
$manager->decrementLimitation($devId, $limitationName);

// обновлем лимиты устройства
// bool $resetCount - заставляет обновлять количественные лимиты 
// (например когда происходит продление пакета)
// метод объеденяет все пакеты и микропакеты и формирует единственную запись лимитов для устройства
$manager->updateDeviceLimitations($devId, $resetCount);
```

Работа с лицензиями
```
#!php
<?php

$manager = new CS\Devices\Manager($db);

// проверяем на существование лицензии пользователя со статусом available (активная лицензия без устройства)
$manager->isUserLicenseAvailable($id, $userId);

// проверяем на существование лицензии пакета для девайса
$manager->hasDevicePackageLicense($devId);

// привязываем устройство к лицензии, также будут обновлены лимиты устройства
$manager->assignLicenseToDevice($licenseId, $deviceId);
```