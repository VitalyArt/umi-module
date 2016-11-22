UMI.CMS module
==============

Module allows integrate UMI.CMS with [RetailCRM](http://www.retailcrm.pro)

#### Features:

* Export orders to retailCRM & fetch changes back
* Export product catalog into [ICML](http://www.retailcrm.pro/docs/Developers/ICML) format

#### Setup

* Copy directories "classes" & "images" into document root
* Go to /admin/config/modules
* Into "Modules" tab fill installation script path (classes/modules/RetailCRM/install.php)
* Press setup button
* Go to module page
* Fill you api url & api key
* Specify directories matching

#### Setting product catalog export

Add to cron:

```
* */4 * * * /usr/bin/php /path_to_site/public_html/cron.php RetailCRM icml
```

#### Getting changes in orders

Add to cron:

```
*/7 * * * * /usr/bin/php /path_to_site/public_html/cron.php RetailCRM history
```
