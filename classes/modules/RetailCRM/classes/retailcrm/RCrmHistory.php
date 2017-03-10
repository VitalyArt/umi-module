<?php

class RCrmHistory
{
    /** @var RCrmApiClient $api */
    private $api = null;

    /**
     * RCrmHistory constructor.
     */
    public function __construct()
    {
        $config = mainConfiguration::getInstance();

        $this->api = new RCrmProxy(
            $config->get('retailcrm', 'crmUrl'),
            $config->get('retailcrm', 'apiKey')
        );
    }

    public function runOrders()
    {
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

            if ($response->isSuccessful() && count($response['history'])) {
                $historyArray = array_merge($historyArray, $response['history']);
            } else {
                break;
            }
        } while ($response['pagination']['currentPage'] != $response['pagination']['totalPageCount']);

        if (count($historyArray)) {
            $lastChange = end($historyArray);

            $crmOrders = RCrmHelpers::getAssemblyOrder($historyArray);

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
                    $order->getObject()->setValue('order_date', umiDate::getTimeStamp($crmOrder['createdAt']));

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
                }

                if (!$order) {
                    continue;
                }

                if (isset($crmOrder['customer'])) {
                    $crmCustomer = $crmOrder['customer'];
                    if (isset($crmCustomer['externalId']) && $crmCustomer['externalId'] > 0) {
                        // TODO: проверить существует ли такой пользователь в системе, если нет, то создать, т.к. принимая customer externalId мы считаем, что такой пользователь уже есть
                        $order->getObject()->setValue('customer_id', $crmCustomer['externalId']);
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
                                $customer->setValue('fname', $crmCustomer['firstName']);
                            }

                            if (!empty($crmCustomer['lastName'])) {
                                $customer->setValue('lname', $crmCustomer['lastName']);
                            }

                            if (!empty($crmCustomer['patronymic'])) {
                                $customer->setValue('father_name', $crmCustomer['patronymic']);
                            }

                            if (!empty($crmCustomer['email'])) {
                                if ($customer->getTypeGUID() == 'emarket-customer') {
                                    $customer->setValue('email', $crmCustomer['email']);
                                } else {
                                    if ($customer->getTypeGUID() == 'users-user') {
                                        $customer->setValue('e-mail', $crmCustomer['email']);
                                    } else {
                                        $customer->setValue('email', $crmCustomer['email']);
                                    }
                                }
                            }

                            if (isset($crmCustomer['phones']) && count($crmCustomer['phones']) > 0) {
                                $customer->setValue('phone', $crmCustomer['phones'][0]['number']);
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
                                            $deliveryObject->setValue('country', $country->getId());
                                            break;
                                        }
                                    }
                                }
                                if (!empty($crmCustomerAddress['index'])) {
                                    $deliveryObject->setValue('index', $crmCustomerAddress['index']);
                                }

                                if (!empty($crmCustomerAddress['region'])) {
                                    $deliveryObject->setValue('region', $crmCustomerAddress['region']);
                                }

                                if (!empty($crmCustomerAddress['city'])) {
                                    $deliveryObject->setValue('city', $crmCustomerAddress['city']);
                                }

                                if (!empty($crmCustomerAddress['street'])) {
                                    $deliveryObject->setValue('street', $crmCustomerAddress['street']);
                                }

                                if (!empty($crmCustomerAddress['building'])) {
                                    $deliveryObject->setValue('house', $crmCustomerAddress['building']);
                                }

                                if (!empty($crmCustomerAddress['flat'])) {
                                    $deliveryObject->setValue('flat', $crmCustomerAddress['flat']);
                                }

                                $deliveryAddresses = array(
                                    0 => $deliveryObject->getId()
                                );

                                $customer->setValue('delivery_addresses', $deliveryAddresses);
                            }
                        }

                        $order->getObject()->setValue('customer_id', $customer->getId());
                    }
                }

                if (isset($crmOrder['items']) && count($crmOrder['items']) > 0) {
                    $orderItems = $order->getItems();
                    foreach ($orderItems as $orderItem) {
                        $order->removeItem($orderItem);
                    }

                    $crmOrderForItems = $this->api->ordersGet($crmOrder['externalId'])->offsetGet('order');
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
                    $customer->setValue('phone', $crmOrder['phone']);
                }

                if (isset($crmOrder['lastName'])) {
                    $customer->setValue('lname', $crmOrder['lastName']);
                }

                if (isset($crmOrder['firstName'])) {
                    $customer->setValue('fname', $crmOrder['firstName']);
                }

                if (isset($crmOrder['patronymic'])) {
                    $customer->setValue('father_name', $crmOrder['patronymic']);
                }

                if (!empty($crmCustomer['email'])) {
                    if ($customer->getTypeGUID() == 'emarket-customer') {
                        $customer->setValue('email', $crmCustomer['email']);
                    } else if ($customer->getTypeGUID() == 'users-user') {
                        $customer->setValue('e-mail', $crmCustomer['email']);
                    } else {
                        $customer->setValue('email', $crmCustomer['email']);
                    }
                }

                if (isset($crmOrder['paymentType'])) {
                    $relationOrderPaymentTypesMap = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderPaymentTypeMap'));
                    $umiOrderPaymentType = RCrmHelpers::getRelationByMap($relationOrderPaymentTypesMap, $crmOrder['paymentType'], true);
                    $order->getObject()->setValue('payment_id', $umiOrderPaymentType);
                }

                if (isset($crmOrder['paymentStatus'])) {
                    $relationOrderPaymentStatusesMap = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderPaymentStatusMap'));
                    $umiOrderPaymentStatus = RCrmHelpers::getRelationByMap($relationOrderPaymentStatusesMap, $crmOrder['paymentStatus'], true);
                    $order->getObject()->setValue('payment_status_id', $umiOrderPaymentStatus);
                }

                if (isset($crmOrder['delivery']['cost'])) {
                    $order->getObject()->setValue('delivery_price', $crmOrder['delivery']['cost']);
                }

                if (isset($crmOrder['delivery']['code'])) {
                    $relationOrderDeliveryTypesMap = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderDeliveryTypeMap'));
                    $umiOrderDeliveryType = RCrmHelpers::getRelationByMap($relationOrderDeliveryTypesMap, $crmOrder['delivery']['code'], true);
                    $order->getObject()->setValue('delivery_id', $umiOrderDeliveryType);
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
                                $deliveryObject->setValue('country', $country->getId());
                                break;
                            }
                        }
                    }

                    if (!empty($crmDeliveryAddress['index'])) {
                        $deliveryObject->setValue('index', $crmDeliveryAddress['index']);
                    }

                    if (!empty($crmDeliveryAddress['region'])) {
                        $deliveryObject->setValue('region', $crmDeliveryAddress['region']);
                    }

                    if (!empty($crmDeliveryAddress['city'])) {
                        $deliveryObject->setValue('city', $crmDeliveryAddress['city']);
                    }

                    if (!empty($crmDeliveryAddress['street'])) {
                        $deliveryObject->setValue('street', $crmDeliveryAddress['street']);
                    }

                    if (!empty($crmDeliveryAddress['building'])) {
                        $deliveryObject->setValue('house', $crmDeliveryAddress['building']);
                    }

                    if (!empty($crmDeliveryAddress['flat'])) {
                        $deliveryObject->setValue('flat', $crmDeliveryAddress['flat']);
                    }

                    $order->getObject()->setValue('delivery_address', $deliveryObject->getId());
                }

                $regedit->setVal('//modules/RetailCRM/IgnoreObjectUpdateEvent/' . $order->getObject()->getId(), time());

                if (isset($crmOrder['status'])) {
                    $relationMap = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderStatusMap'));
                    $umiOrderStatusCode = RCrmHelpers::getRelationByMap($relationMap, $crmOrder['status'], true);
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
