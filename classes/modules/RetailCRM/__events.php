<?php

abstract class __RetailCRM_events
{
    public function onCronGenerateICML()
    {
        $icml = new RCrmIcml();
        $icml->generateICML();
    }

    public function onCronSyncHistory()
    {
        $history = new RCrmHistory();
        $history->runCustomers();
        $history->runOrders();
    }

    public function onOrderStatusChanged(umiEventPoint $eventPoint)
    {
        if ($eventPoint->getMode() != 'after') {
            return;
        }

        $mode = $eventPoint->getParam('old-status-id') == null ? 'create' : 'edit';

        /** @var order $order */
        $order = $eventPoint->getRef('order');

        RCrmActions::orderSend($order->getId(), $mode);
    }

    public function onModifyProperty(umiEventPoint $eventPoint)
    {
        /** @var umiEventPoint $eventPoint */
        if ($eventPoint->getMode() != 'after') {
            return;
        }

        /** @var umiObject $entity */
        $entity = $eventPoint->getRef('entity');

        RCrmActions::orderSend($entity->getId(), 'edit');
    }

    public function onModifyObject(umiEventPoint $eventPoint)
    {
        /** @var umiEventPoint $eventPoint */
        if ($eventPoint->getMode() != 'after') {
            return;
        }

        /** @var umiObject $object */
        $object = $eventPoint->getRef('object');

        RCrmActions::orderSend($object->getId(), 'edit');
    }
}