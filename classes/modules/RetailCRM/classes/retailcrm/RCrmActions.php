<?php

class RCrmActions
{
    /**
     * @param int $orderId
     */
    public static function orderSend($orderId) {
        /** @var RCrmApiClient $api */
        $objects = umiObjectsCollection::getInstance();
        $orderObj = order::get($orderId);

        if (!$orderObj) {
            return;
        }

        // Проверяем был ли вызов из апи
        $regedit = regedit::getInstance();
        $time = $regedit->getVal('//modules/RetailCRM/IgnoreObjectUpdateEvent/' . $orderObj->getObject()->getId());
        if ($time == $orderObj->getObject()->getUpdateTime() OR $time + 1 == $orderObj->getObject()->getUpdateTime()) {
            return;
        }

        $config = mainConfiguration::getInstance();

        $umiOrderStatusCode = order::getCodeByStatus($orderObj->getOrderStatus());

        $relationMap = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderStatusMap'));
        $crmOrderStatusCode = RCrmHelpers::getRelationByMap($relationMap, $umiOrderStatusCode);

        if (!$crmOrderStatusCode) {
            return;
        }

        $umiOrderPaymentType = $orderObj->getObject()->getValue('payment_id');
        $relationOrderPaymentTypesMap = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderPaymentTypeMap'));
        $crmOrderPaymentType = RCrmHelpers::getRelationByMap($relationOrderPaymentTypesMap, $umiOrderPaymentType);

        $umiOrderPaymentStatus = $orderObj->getObject()->getValue('payment_status_id');
        $relationOrderPaymentStatusesMap = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderPaymentStatusMap'));
        $crmOrderPaymentStatus = RCrmHelpers::getRelationByMap($relationOrderPaymentStatusesMap, $umiOrderPaymentStatus);

        $umiOrderDeliveryId = $orderObj->getObject()->getValue('delivery_id');
        $relationOrderDeliveryTypesMap = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderDeliveryTypeMap'));
        $crmOrderDeliveryType = RCrmHelpers::getRelationByMap($relationOrderDeliveryTypesMap, $umiOrderDeliveryId);

        $customer = new umiObject($orderObj->getCustomerId());
        $orderItemsObj = $orderObj->getItems();

        $orderItems = array();

        foreach ($orderItemsObj as $orderItem) {
            /** @var optionedOrderItem $orderItem */
            $itemProperties = array();

            if (get_class($orderItem) == 'optionedOrderItem') {
                foreach ($orderItem->getOptions() as $option) {
                    $option = new umiObject($option['option-id']);
                    $itemProperties[] = array(
                        'name' => $option->getType()->getName(),
                        'value' => $option->getName()
                    );
                }
            }

            $optionGroups = $orderItem->getItemElement()->getObject()->getType()->getFieldsGroupByName('catalog_option_props')->getFields();
            $optionGuidesToGroups = array();

            foreach ($optionGroups as $optionGroup) {
                /** @var umiField $optionGroup */
                $optionGuidesToGroups[$optionGroup->getGuideId()] = $optionGroup->getId();
            }

            $options = array();

            if (get_class($orderItem) == 'optionedOrderItem') {
                foreach ($orderItem->getOptions() as $option) {
                    $option = $objects->getObject($option['option-id']);
                    $options[] = $optionGuidesToGroups[$option->getTypeId()] . '_' . $option->getId();
                }
            }

            $product = $orderItem->getItemElement();

            if (!empty($options)) {
                $productId = $product->getId() . '#' . implode('-', $options);
            } else {
                $productId = $product->getId();
            }

            if (get_class($orderItem) == 'optionedOrderItem') {
                $productName = $product->getName();
            } else {
                $productName = $orderItem->getName();
            }

            if ($orderItem->getDiscount()) {
                $discount = $orderItem->getDiscount();
            } else if ($orderItem->getValue('item_discount_value')) {
                $discount = $orderItem->getValue('item_discount_value');
            } else {
                $discount = 0;
            }

            $orderItems[] = array(
                'initialPrice' => $orderItem->getItemPrice(),
                'discount' => $discount,
                'quantity' => $orderItem->getAmount(),
                'productName' => $productName,
                'properties' => $itemProperties,
                'offer' => array(
                    'externalId' => $productId
                )
            );
        }

        /* One click order */
        if ($orderObj->getObject()->getValue('purchaser_one_click') !== null) {
            $oneClickObj = new umiObject($orderObj->getObject()->getValue('purchaser_one_click'));

            $order = array(
                'number' => $orderObj->getObject()->getValue('number'),
                'externalId' => $orderObj->getId(),
                'lastName' => $oneClickObj->getValue('lname'),
                'firstName' => $oneClickObj->getValue('fname'),
                'patronymic' => $oneClickObj->getValue('father_name'),
                'phone' => $oneClickObj->getValue('phone'),
                'customer' => array(
                    'externalId' => $customer->getId()
                ),
                'items' => $orderItems,
                'orderMethod' => 'one-click'
            );
        } else {
            if ($orderObj->getObject()->getValue('delivery_address') !== null) {
                $deliveryObjId = $orderObj->getObject()->getValue('delivery_address');
                $deliveryObj = new umiObject($deliveryObjId);

                if ($deliveryObj->getValue('country') !== null) {
                    $deliveryCountryObjId = $deliveryObj->getValue('country');

                    try {
                        $deliveryCountryObj = new umiObject($deliveryCountryObjId);
                        $deliveryCountryIsoCode = $deliveryCountryObj->getValue('country_iso_code');
                    } catch (Exception $e) {
                        $deliveryCountryIsoCode = '';
                    }
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

                $deliveryAddress = array(
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
                $deliveryAddress = array();
            }

            $order = array(
                'number' => $orderObj->getObject()->getValue('number'),
                'externalId' => $orderObj->getId(),
                'lastName' => $customer->getValue('lname'),
                'firstName' => $customer->getValue('fname'),
                'patronymic' => $customer->getValue('father_name'),
                'phone' => $customer->getValue('phone'),
                'customer' => array(
                    'externalId' => $customer->getId()
                ),
                'items' => $orderItems,
                'delivery' => array(
                    'address' => $deliveryAddress,
                )
            );
        }

        if ($crmOrderStatusCode && $crmOrderStatusCode != 'none') {
            $order['status'] = $crmOrderStatusCode;
        }

        if ($crmOrderDeliveryType && $crmOrderDeliveryType != 'none') {
            $order['delivery']['code'] = $crmOrderDeliveryType;
        }

        if ($crmOrderPaymentType && $crmOrderPaymentType != 'none') {
            $order['paymentType'] = $crmOrderPaymentType;
        }

        if ($crmOrderPaymentStatus && $crmOrderPaymentStatus != 'none') {
            $order['paymentStatus'] = $crmOrderPaymentStatus;
        }

        if ($customer->getTypeGUID() == 'emarket-customer') {
            $email = $customer->getValue('email');
        } else if ($customer->getTypeGUID() == 'users-user') {
            $email = $customer->getValue('e-mail');
        } else {
            $email = '';
        }

        if ($email = filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $order['email'] = $email;
        }

        if ($deliveryCost = $orderObj->getValue('delivery_price')) {
            $order['delivery']['cost'] = $deliveryCost;
        }

        // TODO: есть возможность учитывать домен
        $api = new RCrmProxy(
            $config->get('retailcrm', 'crmUrl'),
            $config->get('retailcrm', 'apiKey')
        );

        $response = $api->ordersGet($order['externalId']);
        $order = self::customerPrepare($order);

        if ($response->isSuccessful()) {
            $api->ordersEdit($order);
        } else {
            $api->ordersCreate($order);
        }
    }

    /**
     * @param array $order
     * @return array
     */
    public static function customerPrepare(array $order) {
        $config = mainConfiguration::getInstance();

        /** @var RCrmApiClient $api */
        $api = new RCrmProxy(
            $config->get('retailcrm', 'crmUrl'),
            $config->get('retailcrm', 'apiKey')
        );

        $crmCustomer = $api->customersGet(
            $order['customer']['externalId']
        );

        if (!$crmCustomer->isSuccessful()) {
            $crmCustomers = $api->customersList(array(
                'name' => $order['phone'],
                'email' => $order['email']
            ));

            $foundedCustomerExternalId = false;
            if ($crmCustomers->isSuccessful()) {
                $crmCustomers = $crmCustomers->offsetGet('customers');

                if (count($crmCustomers) > 0) {
                    foreach ($crmCustomers as $crmCustomer) {
                        if (isset($crmCustomer['externalId']) && $crmCustomer['externalId'] > 0) {
                            $foundedCustomerExternalId = true;
                            $order['customer']['externalId'] = $crmCustomer['externalId'];
                            break;
                        }
                    }

                    if (!$foundedCustomerExternalId) {
                        $status = $api->customersFixExternalIds(array(
                            'id' => $crmCustomers[0]['id'],
                            'externalId' => $crmCustomers[0]['externalId']
                        ));

                        if (!$status->isSuccessful()) {
                            unset($order['customer']);
                        }
                    }
                } else {
                    $status = $api->customersCreate(array(
                        'externalId' => $order['customer']['externalId'],
                        'firstName' => $order['firstName'],
                        'lastName' => $order['lastName'],
                        'patronymic' => $order['patronymic'],
                        'email' => $order['email'],
                        'phones' => array(
                            'number' => $order['phone']
                        )
                    ));

                    if (!$status->isSuccessful()) {
                        unset($order['customer']);
                    }
                }
            } else {
                unset($order['customer']);
            }
        }

        return $order;
    }
}
