UMI.CMS module
==============

Модуль интеграции UMI.CMS c [RetailCRM](http://www.retailcrm.ru)

#### Модуль позволяет:

* Экспортировать в CRM данные о заказах и клиентах и получать обратно изменения по этим данным
* Синхронизировать справочники (способы доставки и оплаты, статусы заказов и т.п.)
* Выгружать каталог товаров в формате [ICML](http://retailcrm.ru/docs/Разработчики/ФорматICML) (IntaroCRM Markup Language)

#### Настройка

* Скопируйте директории classes и images в корень сайта
* В разделе управления модулями (/admin/config/modules) укажите путь до инсталяционного файла: classes/modules/RetailCRM/install.php
* На странице настроек модуля введите API url и API ключ, после этого установите соответствие справочников

#### Выгрузка каталога

Добавьте в крон запись вида

```
* */4 * * * /usr/bin/php /path_to_site/public_html/cron.php RetailCRM icml
```

#### Получение изменение из RetailCRM

Добавьте в крон запись вида

```
*/7 * * * * /usr/bin/php /path_to_site/public_html/cron.php RetailCRM history
```

