<?php
    $eventHandlers = array(
        'icml' => 'onCronGenerateICML',
        'history' => 'onCronSyncHistory',
    );

    if (isset($_SERVER['argv']) && (isset($_SERVER['argv'][2]) || isset($eventHandlers[$_SERVER['argv'][2]]))) {
        new umiEventListener('cron', 'RetailCRM', $eventHandlers[$_SERVER['argv'][2]]);
    }
    
    if (isset($_GET['action']) && isset($eventHandlers[$_GET['action']])) {
        new umiEventListener('cron', 'RetailCRM', $eventHandlers[$_GET['action']]);
    }
    
    new umiEventListener('systemModifyPropertyValue', 'RetailCRM', 'onModifyProperty');
    new umiEventListener('systemModifyObject', 'RetailCRM', 'onModifyObject');
    new umiEventListener('order-status-changed', 'RetailCRM', 'onOrderStatusChanged');
?>
