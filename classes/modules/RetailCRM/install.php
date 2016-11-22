<?php

$INFO = array();

$INFO['name'] = "RetailCRM"; // Имя модуля (латинское), должно совпадать с именем папки модуля
$INFO['title'] = "RetailCRM";
$INFO['description'] = "RetailCRM integration module.";
$INFO['filename'] = "modules/RetailCRM/class.php"; // Путь до файла class.php
$INFO['config'] = "0"; // Если «1», то модуль будет настраиваемый, если «0», то нет
$INFO['ico'] = "ico_PrivateOffice"; // Базовое имя файла иконки модуля
//$INFO['default_method'] = "view"; // Метод (функция) вызываемая, по умолчанию для клиентской части
$INFO['default_method_admin'] = "manage"; // Метод (функция), вызываемая, по умолчанию для админки

$INFO['func_perms'] = ""; // Массив, определяющий группы прав нашего модуля (появятся в настройках пользователя и будут влиять на доступ к методам (функциям) модуля), ключи массива вносятся в реестр иерархически.
// В данном случае мы предусмотрим, что «пользователь» сможет «админить» модуль (даем доступ к методу manage), а также просматривать его страницы (даем доступ к методу view)
$INFO['func_perms/view'] = "Просмотр страниц модуля"; //Собственно объявляем права для «клиентского метода»
$INFO['func_perms/manage'] = "Администрирование модуля"; // И для «административной части»

$COMPONENTS[0] = "./classes/modules/RetailCRM/__admin.php";
$COMPONENTS[1] = "./classes/modules/RetailCRM/__events.php";
$COMPONENTS[2] = "./classes/modules/RetailCRM/events.php";
$COMPONENTS[3] = "./classes/modules/RetailCRM/classes/retailcrm/RCrmIcml.php";
$COMPONENTS[4] = "./classes/modules/RetailCRM/classes/retailcrm/RCrmApiClient.php";
$COMPONENTS[5] = "./classes/modules/RetailCRM/classes/retailcrm/RCrmHttpClient.php";
$COMPONENTS[6] = "./classes/modules/RetailCRM/classes/retailcrm/RCrmApiResponse.php";
$COMPONENTS[7] = "./classes/modules/RetailCRM/i18n.php";