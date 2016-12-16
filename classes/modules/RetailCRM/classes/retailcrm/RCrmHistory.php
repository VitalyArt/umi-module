<?php

class RCrmHistory
{
    /** @var RCrmApiClient $api */
    private $api = null;

    public function __construct()
    {
        $config = mainConfiguration::getInstance();

        $this->api = new RCrmProxy(
            $config->get('retailcrm', 'crmUrl'),
            $config->get('retailcrm', 'apiKey'),
            __DIR__ . '/../../../retailcrm.error.log'
        );
    }

    public function runOrders()
    {
        $retailcrm = new RetailCRM;
        $regedit = regedit::getInstance();
        $config = mainConfiguration::getInstance();

        $historyLastId = $config->get('retailcrm', 'lastHistorySinceId');

        if (!$historyLastId) {
            $historyLastId = 0;
        }

        $historyPage = 1;
        $historyArray = array();

        do {
            $response = $this->api->ordersHistory(array('sinceId' => $historyLastId), $historyPage);
            $historyPage++;

            if (!is_null($response) && count($response['history'])) {
                $historyArray = array_merge($historyArray, $response['history']);
            } else {
                break;
            }
        } while ($response['pagination']['currentPage'] != $response['pagination']['totalPageCount']);

        if (count($historyArray)) {
            $lastChange = end($historyArray);

            $crmOrders = $retailcrm->getAssemblyOrder($historyArray);

            $objectTypes = umiObjectTypesCollection::getInstance();
            $objects = umiObjectsCollection::getInstance();

            foreach ($crmOrders as $crmOrder) {
                $order = null;

                if (isset($crmOrder['externalId'])) {
                    $order = order::get($crmOrder['externalId']);
                }

                if ((!isset($crmOrder['externalId']) || !$order) && isset($crmOrder['created'])) {
                    if (isset($crmOrder['deleted']) && $crmOrder['deleted']) {
                        continue;
                    }

                    $order = order::create();

                    /* Order create date */
                    $order->getObject()->getPropByName('order_date')->setValue(umiDate::getTimeStamp($crmOrder['createdAt']));

                    $crmCustomer = $crmOrder['customer'];
                    if (isset($crmCustomer['externalId']) && $crmCustomer['externalId'] > 0) {
                        // TODO: проверить существует ли такой пользователь в системе, если нет, то создать, т.к. принимая customer externalId мы считаем, что такой пользователь уже есть
                        $order->getObject()->getPropByName('customer_id')->setValue($crmCustomer['externalId']);
                    } else {
                        $customer = $objects->getObjectByName($crmCustomer['id'] . '-retailcrm');

                        if (!$customer) {
                            $customerObjectTypeId = $objectTypes->getBaseType('emarket', 'customer');

                            $customerId = $objects->addObject(
                                $crmCustomer['id'] . '-retailcrm',
                                $customerObjectTypeId
                            );

                            $customer = $objects->getObject($customerId);
                            $customer->setOwnerId($objects->getObjectIdByGUID('system-guest'));
                            $customer->commit();

                            $expirations = umiObjectsExpiration::getInstance();
                            $expirations->add($customerId, customer::$defaultExpiration);

                            if (!empty($crmCustomer['firstName'])) {
                                $customer->getPropByName('fname')->setValue($crmCustomer['firstName']);
                            }

                            if (!empty($crmCustomer['lastName'])) {
                                $customer->getPropByName('lname')->setValue($crmCustomer['lastName']);
                            }

                            if (!empty($crmCustomer['patronymic'])) {
                                $customer->getPropByName('father_name')->setValue($crmCustomer['patronymic']);
                            }

                            if (!empty($crmCustomer['email'])) {
                                $customer->getPropByName('email')->setValue($crmCustomer['email']);
                            }

                            if (isset($crmCustomer['phones']) && count($crmCustomer['phones']) > 0) {
                                $customer->getPropByName('phone')->setValue($crmCustomer['phones'][0]['number']);
                            }

                            if (isset($crmCustomer['address'])) {
                                $deliveryTypeId = $objectTypes->getTypeIdByGUID('emarket-deliveryaddress');

                                $deliveryObjectId = $objects->addObject(
                                    'Address for customer ' . $customer->getId(),
                                    $deliveryTypeId
                                );

                                $deliveryObject = $objects->getObject($deliveryObjectId);

                                $crmCustomerAddress = $crmCustomer['address'];

                                if (!empty($crmCustomerAddress['countryIso'])) {
                                    $selector = new selector('objects');
                                    $selector->types('object-type')->id($config->get('retailcrm', 'countryGuideId'));

                                    $countries = $selector->result();

                                    foreach ($countries as $country) {
                                        /** @var umiObject $country */

                                        $countryCode = $country->getValue('country_iso_code');

                                        if ($crmCustomerAddress['countryIso'] == $countryCode) {
                                            $deliveryObject->getPropByName('country')->setValue($country->getId());
                                            break;
                                        }
                                    }
                                }
                                if (!empty($crmCustomerAddress['index'])) {
                                    $deliveryObject->getPropByName('index')->setValue($crmCustomerAddress['index']);
                                }

                                if (!empty($crmCustomerAddress['region'])) {
                                    $deliveryObject->getPropByName('region')->setValue($crmCustomerAddress['region']);
                                }

                                if (!empty($crmCustomerAddress['city'])) {
                                    $deliveryObject->getPropByName('city')->setValue($crmCustomerAddress['city']);
                                }

                                if (!empty($crmCustomerAddress['street'])) {
                                    $deliveryObject->getPropByName('street')->setValue($crmCustomerAddress['street']);
                                }

                                if (!empty($crmCustomerAddress['building'])) {
                                    $deliveryObject->getPropByName('house')->setValue($crmCustomerAddress['building']);
                                }

                                if (!empty($crmCustomerAddress['flat'])) {
                                    $deliveryObject->getPropByName('flat')->setValue($crmCustomerAddress['flat']);
                                }

                                $deliveryAddresses = array(
                                    0 => $deliveryObject->getId()
                                );

                                $customer->getPropByName('delivery_addresses')->setValue($deliveryAddresses);
                            }
                        }

                        $order->getObject()->getPropByName('customer_id')->setValue($customer->getId());
                    }

                    $orderItems = $order->getItems();
                    foreach ($orderItems as $orderItem) {
                        $order->removeItem($orderItem);
                    }

                    $crmItems = $crmOrder['items'];
                    foreach ($crmItems as $crmItem) {
                        if (isset($crmItem['deleted']) && $crmItem['deleted'] == true) {
                            continue;
                        }
                        if (!isset($crmItem['offer']['externalId'])) {
                            continue;
                        }

                        if (mb_strpos($crmItem['offer']['externalId'], '#')) {
                            $data = explode('#', $crmItem['offer']['externalId']);
                            $itemId = $data[0];

                            $orderItem = orderItem::create($itemId);

                            $itemOptions = $data[1];
                            $itemOptions = explode('-', $itemOptions);
                            foreach ($itemOptions as $itemOption) {
                                $itemOption = explode('_', $itemOption);
                                $itemOptionGroupId = $itemOption[0];
                                $itemOptionValue = $itemOption[1];
                                $itemOptionObject = new umiField($itemOptionGroupId);

                                $orderItem->appendOption($itemOptionObject->getName(), $itemOptionValue);
                            }
                        } else {
                            $orderItem = orderItem::create($crmItem['offer']['externalId']);
                        }

                        /** @var optionedOrderItem $orderItem */
                        $orderItem->setAmount($crmItem['quantity']);

                        $order->appendItem($orderItem);
                    }

                    if (isset($crmOrder['paymentType'])) {
                        $relationOrderPaymentTypesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderPaymentTypeMap'));
                        $umiOrderPaymentType = $retailcrm->getRelationByMap($relationOrderPaymentTypesMap, $crmOrder['paymentType'], true);
                        $order->getObject()->getPropByName('payment_id')->setValue($umiOrderPaymentType);
                    }

                    if (isset($crmOrder['paymentStatus'])) {
                        $relationOrderPaymentStatusesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderPaymentStatusMap'));
                        $umiOrderPaymentStatus = $retailcrm->getRelationByMap($relationOrderPaymentStatusesMap, $crmOrder['paymentStatus'], true);
                        $order->getObject()->getPropByName('payment_status_id')->setValue($umiOrderPaymentStatus);
                    }

                    if (isset($crmOrder['delivery']['cost'])) {
                        $order->getObject()->getPropByName('delivery_price')->setValue($crmOrder['delivery']['cost']);
                    }
                    
                    if (isset($crmOrder['delivery']['code'])) {
                        $relationOrderDeliveryTypesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderDeliveryTypeMap'));
                        $umiOrderDeliveryType = $retailcrm->getRelationByMap($relationOrderDeliveryTypesMap, $crmOrder['delivery']['code'], true);
                        $order->getObject()->getPropByName('delivery_id')->setValue($umiOrderDeliveryType);
                    }

                    if (isset($crmOrder['delivery']['address']) && count($crmOrder['delivery']['address'])) {
                        $crmDeliveryAddress = $crmOrder['delivery']['address'];

                        $deliveryTypeId = $objectTypes->getTypeIdByGUID('emarket-deliveryaddress');
                        $deliveryObjectId = $objects->addObject('Address for order ' . $order->getId(), $deliveryTypeId);
                        $deliveryObject = $objects->getObject($deliveryObjectId);

                        if (!empty($crmDeliveryAddress['countryIso'])) {
                            $selector = new selector('objects');
                            try {
                                $selector->types('object-type')->id($config->get('retailcrm', 'countryGuideId'));
                                $countries = $selector->result();

                                foreach ($countries as $country) {
                                    /** @var umiObject $country */

                                    $countryCode = $country->getValue('country_iso_code');

                                    if ($crmDeliveryAddress['countryIso'] == $countryCode) {
                                        $deliveryObject->getPropByName('country')->setValue($country->getId());
                                        break;
                                    }
                                }
                            } catch (selectorException $e) {}
                        }

                        if (!empty($crmDeliveryAddress['index'])) {
                            $deliveryObject->getPropByName('index')->setValue($crmDeliveryAddress['index']);
                        }

                        if (!empty($crmDeliveryAddress['region'])) {
                            $deliveryObject->getPropByName('region')->setValue($crmDeliveryAddress['region']);
                        }

                        if (!empty($crmDeliveryAddress['city'])) {
                            $deliveryObject->getPropByName('city')->setValue($crmDeliveryAddress['city']);
                        }

                        if (!empty($crmDeliveryAddress['street'])) {
                            $deliveryObject->getPropByName('street')->setValue($crmDeliveryAddress['street']);
                        }

                        if (!empty($crmDeliveryAddress['building'])) {
                            $deliveryObject->getPropByName('house')->setValue($crmDeliveryAddress['building']);
                        }

                        if (!empty($crmDeliveryAddress['flat'])) {
                            $deliveryObject->getPropByName('flat')->setValue($crmDeliveryAddress['flat']);
                        }

                        $order->getObject()->getPropByName('delivery_address')->setValue($deliveryObject->getId());
                    }

                    if (!empty($crmOrder['number'])) {
                        $order->setName($crmOrder['number']);
                    } else {
                        $order->generateNumber();
                        $order->setName($order->getName() . ' ' . $crmOrder['id'] . "-retailcrm");
                    }

                    $this->api->ordersFixExternalIds(array(
                        array(
                            'id' => $crmOrder['id'],
                            'externalId' => $order->getId()
                        )
                    ));
                } else {
                    if (!$order) {
                        continue;
                    }

                    if (isset($crmOrder['items']) && count($crmOrder['items']) > 0) {
                        $orderItems = $order->getItems();
                        foreach ($orderItems as $orderItem) {
                            $order->removeItem($orderItem);
                        }

                        $crmOrderForItems = $this->api->ordersGet($crmOrder['externalId'])->getOrder();
                        $crmItems = $crmOrderForItems['items'];

                        foreach ($crmItems as $crmItem) {
                            if (isset($crmItem['deleted']) && $crmItem['deleted'] == true) {
                                continue;
                            }
                            if (!isset($crmItem['offer']['externalId'])) {
                                continue;
                            }

                            if (mb_strpos($crmItem['offer']['externalId'], '#')) {
                                $data = explode('#', $crmItem['offer']['externalId']);
                                $itemId = $data[0];

                                $orderItem = orderItem::create($itemId);

                                $itemOptions = $data[1];
                                $itemOptions = explode('-', $itemOptions);
                                foreach ($itemOptions as $itemOption) {
                                    $itemOption = explode('_', $itemOption);
                                    $itemOptionGroupId = $itemOption[0];
                                    $itemOptionValue = $itemOption[1];
                                    $itemOptionObject = new umiField($itemOptionGroupId);

                                    $orderItem->appendOption($itemOptionObject->getName(), $itemOptionValue);
                                }
                            } else {
                                $orderItem = orderItem::create($crmItem['offer']['externalId']);
                            }

                            /** @var optionedOrderItem $orderItem */
                            $orderItem->setAmount($crmItem['quantity']);
                            $order->appendItem($orderItem);
                        }
                    }

                    $customer = $objects->getObject($order->getCustomerId());

                    if (isset($crmOrder['phone'])) {
                        $customer->getPropByName('phone')->setValue($crmOrder['phone']);
                    }

                    if (isset($crmOrder['lastName'])) {
                        $customer->getPropByName('lname')->setValue($crmOrder['lastName']);
                    }

                    if (isset($crmOrder['firstName'])) {
                        $customer->getPropByName('fname')->setValue($crmOrder['firstName']);
                    }

                    if (isset($crmOrder['patronymic'])) {
                        $customer->getPropByName('father_name')->setValue($crmOrder['patronymic']);
                    }

                    if (isset($crmOrder['e-mail'])) {
                        $customer->getPropByName('e-mail')->setValue($crmOrder['e-mail']);
                    }

                    if (isset($crmOrder['paymentType'])) {
                        $relationOrderPaymentTypesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderPaymentTypeMap'));
                        $umiOrderPaymentType = $retailcrm->getRelationByMap($relationOrderPaymentTypesMap, $crmOrder['paymentType'], true);
                        $order->getObject()->getPropByName('payment_id')->setValue($umiOrderPaymentType);
                    }

                    if (isset($crmOrder['paymentStatus'])) {
                        $relationOrderPaymentStatusesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderPaymentStatusMap'));
                        $umiOrderPaymentStatus = $retailcrm->getRelationByMap($relationOrderPaymentStatusesMap, $crmOrder['paymentStatus'], true);
                        $order->getObject()->getPropByName('payment_status_id')->setValue($umiOrderPaymentStatus);
                    }

                    if (isset($crmOrder['delivery']['cost'])) {
                        $order->getObject()->getPropByName('delivery_price')->setValue($crmOrder['delivery']['cost']);
                    }
                    
                    if (isset($crmOrder['delivery']['code'])) {
                        $relationOrderDeliveryTypesMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderDeliveryTypeMap'));
                        $umiOrderDeliveryType = $retailcrm->getRelationByMap($relationOrderDeliveryTypesMap, $crmOrder['delivery']['code'], true);
                        $order->getObject()->getPropByName('delivery_id')->setValue($umiOrderDeliveryType);
                    }

                    if (isset($crmOrder['delivery']['address']) && count($crmOrder['delivery']['address'])) {
                        $crmDeliveryAddress = $crmOrder['delivery']['address'];

                        $deliveryTypeId = $objectTypes->getTypeIdByGUID('emarket-deliveryaddress');
                        $deliveryObject = $objects->getObjectByName('Address for order ' . $order->getId());
                        if (!$deliveryObject) {
                            $deliveryObjectId = $objects->addObject('Address for order ' . $order->getId(), $deliveryTypeId);
                            $deliveryObject = $objects->getObject($deliveryObjectId);
                        }

                        if (!empty($crmDeliveryAddress['countryIso'])) {
                            $selector = new selector('objects');
                            $selector->types('object-type')->id($config->get('retailcrm', 'countryGuideId'));

                            $countries = $selector->result();

                            foreach ($countries as $country) {
                                /** @var umiObject $country */

                                $countryCode = $country->getValue('country_iso_code');

                                if ($crmDeliveryAddress['countryIso'] == $countryCode) {
                                    $deliveryObject->getPropByName('country')->setValue($country->getId());
                                    break;
                                }
                            }
                        }

                        if (!empty($crmDeliveryAddress['index'])) {
                            $deliveryObject->getPropByName('index')->setValue($crmDeliveryAddress['index']);
                        }

                        if (!empty($crmDeliveryAddress['region'])) {
                            $deliveryObject->getPropByName('region')->setValue($crmDeliveryAddress['region']);
                        }

                        if (!empty($crmDeliveryAddress['city'])) {
                            $deliveryObject->getPropByName('city')->setValue($crmDeliveryAddress['city']);
                        }

                        if (!empty($crmDeliveryAddress['street'])) {
                            $deliveryObject->getPropByName('street')->setValue($crmDeliveryAddress['street']);
                        }

                        if (!empty($crmDeliveryAddress['building'])) {
                            $deliveryObject->getPropByName('house')->setValue($crmDeliveryAddress['building']);
                        }

                        if (!empty($crmDeliveryAddress['flat'])) {
                            $deliveryObject->getPropByName('flat')->setValue($crmDeliveryAddress['flat']);
                        }

                        $order->getObject()->getPropByName('delivery_address')->setValue($deliveryObject->getId());
                    }
                }

                if (!$order) {
                    continue;
                }

                $regedit->setVal('//modules/RetailCRM/IgnoreObjectUpdateEvent/' . $order->getObject()->getId(), time());

                if (isset($crmOrder['status'])) {
                    $relationMap = $retailcrm->getRelationMap($config->get('retailcrm', 'orderStatusMap'));
                    $umiOrderStatusCode = $retailcrm->getRelationByMap($relationMap, $crmOrder['status'], true);
                    if ($umiOrderStatusCode) {
                        // меняем дату редактирования заказа, для того, чтобы его не перехватил хенлдер и не выплюнул обратно в црм
                        $order->getObject()->setUpdateTime(time());
                        $order->setOrderStatus($umiOrderStatusCode);
                    }
                }

                $order->refresh();
                $config->set('retailcrm', 'lastHistorySinceId', $lastChange['id']);
            }
        }
    }

    public static function runCustomers()
    {

    }
}
