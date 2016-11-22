<?php
    global $argv;

    if ((!isset($argv[2]) || $argv[2] == 'icml') && !isset($_GET['module']) || (isset($_GET['action']) && $_GET['action'] == 'icml')) {
        new umiEventListener('cron', 'RetailCRM', 'onCronGenerateICML');
    }

    if ((!isset($argv[2]) || $argv[2] == 'history') && !isset($_GET['module']) || (isset($_GET['action']) && $_GET['action'] == 'history')) {
        new umiEventListener('cron', 'RetailCRM', 'onCronSyncHistory');
    }

    new umiEventListener('systemModifyPropertyValue', 'RetailCRM', 'onModifyProperty');
    new umiEventListener('systemModifyObject', 'RetailCRM', 'onModifyObject');
    new umiEventListener('order-status-changed', 'RetailCRM', 'onOrderStatusChanged');
?>