<?php

class RCrmActions
{
    public static function orderSend($orderId, $mode = 'create') {
        /** @var RCrmApiClient $api */
        $objects = umiObjectsCollection::getInstance();
        $order = order::get($orderId);

        if (!$order) {
            return;
        }

        // Проверяем был ли вызов из апи
        $regedit = regedit::getInstance();
        $time = $regedit->getVal('//modules/RetailCRM/IgnoreObjectUpdateEvent/' . $order->getObject()->getId());
        if ($time == $order->getObject()->getUpdateTime() OR $time + 1 == $order->getObject()->getUpdateTime()) {
            return;
        }

        $config = mainConfiguration::getInstance();

        $umiOrderStatusCode = order::getCodeByStatus($order->getOrderStatus());

        $retailcrm = new RetailCRM;

        $relationMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderStatusMap'));
        $crmOrderStatusCode = $retailcrm->getRelationByMap($relationMap, $umiOrderStatusCode);

        if (!$crmOrderStatusCode) {
            return;
        }

        $umiOrderPaymentType = $order->getObject()->getValue('payment_id');
        $relationOrderPaymentTypesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderPaymentTypeMap'));
        $crmOrderPaymentType = $retailcrm->getRelationByMap($relationOrderPaymentTypesMap, $umiOrderPaymentType);

        $umiOrderPaymentStatus = $order->getObject()->getValue('payment_status_id');
        $relationOrderPaymentStatusesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderPaymentStatusMap'));
        $crmOrderPaymentStatus = $retailcrm->getRelationByMap($relationOrderPaymentStatusesMap, $umiOrderPaymentStatus);

        $umiOrderDeliveryId = $order->getObject()->getValue('delivery_id');
        $relationOrderDeliveryTypesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderDeliveryTypeMap'));
        $crmOrderDeliveryType = $retailcrm->getRelationByMap($relationOrderDeliveryTypesMap, $umiOrderDeliveryId);

        $customer = customer::get($order->getCustomerId());
        $orderItems = $order->getItems();

        $orderItemsToCrm = array();

        foreach ($orderItems as $orderItem) {
            /** @var optionedOrderItem $orderItem */

            $itemProperties = array();
            foreach ($orderItem->getOptions() as $option) {
                $option = new umiObject($option['option-id']);
                $itemProperties[] = array(
                    'name' => $option->getType()->getName(),
                    'value' => $option->getName()
                );
            }

            $optionGroups = $orderItem->getItemElement()->getObject()->getType()->getFieldsGroupByName('catalog_option_props')->getFields();
            $optionGuidesToGroups = array();
            foreach ($optionGroups as $optionGroup) {
                /** @var umiField $optionGroup */
                $optionGuidesToGroups[$optionGroup->getGuideId()] = $optionGroup->getId();
            }

            $options = array();
            foreach ($orderItem->getOptions() as $option) {
                $option = $objects->getObject($option['option-id']);
                $options[] = $optionGuidesToGroups[$option->getTypeId()] . '_' . $option->getId();
            }

            $product = $orderItem->getItemElement();

            if (!empty($options)) {
                $productId = $product->getId() . '#' . implode('-', $options);
            } else {
                $productId = $product->getId();
            }

            $orderItemsToCrm[] = array(
                'initialPrice' => $orderItem->getItemPrice(),
                'discount' => $orderItem->getDiscount(),
                'quantity' => $orderItem->getAmount(),
                'productName' => $product->getName(),
                'properties' => $itemProperties,
                'offer' => array(
                    'externalId' => $productId
                )
            );
        }

        /* One click order */
        if ($order->getObject()->getValue('purchaser_one_click') !== null) {
            $oneClickObj = new umiObject($order->getObject()->getValue('purchaser_one_click'));

            $orderToCrm = array(
                'number' => $order->getObject()->getName(),
                'externalId' => $order->getId(),
                'phone' => $oneClickObj->getValue('phone'),
                'customer' => array(
                    'externalId' => $customer->getId()
                ),
                'paymentType' => $crmOrderPaymentType,
                'paymentStatus' => $crmOrderPaymentStatus,
                'status' => $crmOrderStatusCode,
                'items' => $orderItemsToCrm,
                'orderMethod' => 'one-click'
            );
        } else {
            if ($order->getObject()->getValue('delivery_address') !== null) {
                $deliveryObjId = $order->getObject()->getValue('delivery_address');
                $deliveryObj = new umiObject($deliveryObjId);

                if ($deliveryObj->getValue('country') !== false) {
                    $deliveryCountryObjId = $deliveryObj->getValue('country');
                    $deliveryCountryObj = new umiObject($deliveryCountryObjId);
                    $deliveryCountryIsoCode = $deliveryCountryObj->getValue('country_iso_code');
                } else {
                    $deliveryCountryIsoCode = '';
                }

                $deliveryIndex = $deliveryObj->getValue('index');
                $deliveryStreet = $deliveryObj->getValue('street');
                $deliveryBuilding = $deliveryObj->getValue('house');
                $deliveryHouse = $deliveryObj->getValue('house');
                $deliveryFlat = $deliveryObj->getValue('flat');
                $deliveryNotes = $deliveryObj->getValue('order_comments');

                if ($deliveryObj->getValue('region') !== null) {
                    try {
                        $deliveryRegionObj = new umiObject($deliveryObj->getValue('region'));
                        $deliveryRegion = $deliveryRegionObj->getName();
                    } catch (Exception $e) {
                        $deliveryRegion = $deliveryObj->getValue('region');
                    }
                } else {
                    $deliveryRegion = '';
                }

                if ($deliveryObj->getValue('city') !== null) {
                    try {
                        $deliveryCityObj = new umiObject($deliveryObj->getValue('city'));
                        $deliveryCity = $deliveryCityObj->getName();
                    } catch (Exception $e) {
                        $deliveryCity = $deliveryObj->getValue('city');
                    }
                } else {
                    $deliveryCity = '';
                }

                $addressToCrm = array(
                    'countryIso' => $deliveryCountryIsoCode,
                    'index' => $deliveryIndex,
                    'region' => $deliveryRegion,
                    'city' => $deliveryCity,
                    'street' => $deliveryStreet,
                    'building' => $deliveryBuilding,
                    'flat' => $deliveryFlat,
                    'house' => $deliveryHouse,
                    'notes' => $deliveryNotes
                );

            } else {
                $addressToCrm = array();
            }

            $orderToCrm = array(
                'number' => $order->getObject()->getName(),
                'externalId' => $order->getId(),
                'lastName' => $customer->getValue('lname'),
                'firstName' => $customer->getValue('fname'),
                'patronymic' => $customer->getValue('father_name'),
                'phone' => $customer->getValue('phone'),
                'email' => $customer->getValue('email'),
                'customer' => array(
                    'externalId' => $customer->getId()
                ),
                'paymentType' => $crmOrderPaymentType,
                'paymentStatus' => $crmOrderPaymentStatus,
                'status' => $crmOrderStatusCode,
                'items' => $orderItemsToCrm,
                'delivery' => array(
                    'address' => $addressToCrm,
                    'code' => $crmOrderDeliveryType
                )
            );
        }

        // TODO: есть возможность учитывать домен
        $api = new RCrmProxy(
            $config->get('retailcrm', 'crmUrl'),
            $config->get('retailcrm', 'apiKey'),
            __DIR__ . '/../../../retailcrm.error.log'
        );

        if ($mode == 'create') {
            $orderToCrm = self::customerPrepare($orderToCrm);
            $api->ordersCreate($orderToCrm);
        } else if ($mode == 'edit') {
            $api->ordersEdit($orderToCrm);
        }
    }

    public static function customerPrepare($orderToCrm) {
        $config = mainConfiguration::getInstance();

        $api = new RCrmProxy(
            $config->get('retailcrm', 'crmUrl'),
            $config->get('retailcrm', 'apiKey'),
            __DIR__ . '/../../../retailcrm.error.log'
        );

        $crmCustomer = $api->customersGet($orderToCrm['customer']['externalId']);

        if (!$crmCustomer) {
            $crmCustomers = $api->customersList(array(
                'name' => $orderToCrm['phone'],
                'email' => $orderToCrm['email']
            ));

            $foundedCustomerExternalId = false;
            if ($crmCustomers) {
                /** @var RCrmApiResponse $crmCustomers */
                $crmCustomers = $crmCustomers->getCustomers();

                if (count($crmCustomers) > 0) {
                    foreach ($crmCustomers as $crmCustomer) {
                        if (isset($crmCustomer['externalId']) && $crmCustomer['externalId'] > 0) {
                            $foundedCustomerExternalId = true;
                            $orderToCrm['customer']['externalId'] = $crmCustomer['externalId'];
                            break;
                        }
                    }

                    if (!$foundedCustomerExternalId) {
                        $crmCustomer = $crmCustomers[0];
                        $status = $api->customersFixExternalIds(array(
                            'id' => $crmCustomer['id'],
                            'externalId' => $crmCustomer['externalId']
                        ));

                        if (!$status) {
                            unset($orderToCrm['customer']);
                        }
                    }
                } else {
                    $status = $api->customersCreate(array(
                        'externalId' => $orderToCrm['customer']['externalId'],
                        'firstName' => $orderToCrm['firstName'],
                        'lastName' => $orderToCrm['lastName'],
                        'patronymic' => $orderToCrm['patronymic'],
                        'email' => $orderToCrm['email'],
                        'phones' => array(
                            'number' => $orderToCrm['phone']
                        )
                    ));

                    if (!$status) {
                        unset($orderToCrm['customer']);
                    }
                }
            } else {
                unset($orderToCrm['customer']);
            }
        }

        return $orderToCrm;
    }
}
