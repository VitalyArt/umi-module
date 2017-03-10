<?php

class RCrmHelpers
{

    /**
     * @param $mapArr array
     * @return array
     */
    public static function getRelationMap($mapArr)
    {
        if (empty($mapArr)) {
            return array();
        }

        $map = array();
        foreach ($mapArr as $mapItem) {
            $mapItem = explode(' <-> ', $mapItem);
            $map[$mapItem[0]] = $mapItem[1];
        }

        return $map;
    }

    /**
     * @param $map array
     * @param $item string
     * @param $reversed bool
     * @return string|null
     */
    public static function getRelationByMap($map, $item, $reversed = false)
    {
        if (!$reversed) {
            if (isset($map[$item]) && !empty($map[$item])) {
                return $map[$item];
            } else {
                return null;
            }
        } else {
            foreach ($map as $umiStatusOrder => $crmStatusOrder) {
                if ($crmStatusOrder == $item) {
                    return $umiStatusOrder;
                }
            }
            return null;
        }
    }

    /**
     * @param $orderHistory array
     * @return array
     */
    public static function getAssemblyOrder($orderHistory)
    {
        if (file_exists(__DIR__ . '/../../data/objects.xml')) {
            $objects = simplexml_load_file(__DIR__ . '/../../data/objects.xml');
            foreach ($objects->fields->field as $object) {
                $fields[(string)$object["group"]][(string)$object["id"]] = (string)$object;
            }
        }

        $orders = array();

        foreach ($orderHistory as $change) {
            $change['order'] = self::removeEmpty($change['order']);

            $orderId = $change['order']['id'];

            if (isset($change['order']['items'])) {
                $items = array();
                foreach ($change['order']['items'] as $item) {
                    if (isset($change['created'])) {
                        $item['created'] = 1;
                    }
                    $items[$item['id']] = $item;
                }
                $change['order']['items'] = $items;
            }

            if (isset($change['order']['contragent']['contragentType'])) {
                $change['order']['contragentType'] = $change['order']['contragent']['contragentType'];
                unset($change['order']['contragent']);
            }

            if (isset($orders[$orderId])) {
                $orders[$orderId] = array_merge($orders[$orderId], $change['order']);
            } else {
                $orders[$orderId] = $change['order'];
            }

            if (isset($change['item'])) {
                $itemId = $change['item']['id'];

                if ($orders[$orderId]['items'][$itemId]) {
                    $orders[$orderId]['items'][$itemId] = array_merge($orders[$orderId]['items'][$itemId],
                        $change['item']);
                } else {
                    $orders[$orderId]['items'][$itemId] = $change['item'];
                }

                if (empty($change['oldValue']) && $change['field'] == 'order_product') {
                    $orders[$orderId]['items'][$itemId]['created'] = true;
                }

                if (empty($change['newValue']) && $change['field'] == 'order_product') {
                    $orders[$orderId]['items'][$itemId]['deleted'] = true;
                }

                if (!$orders[$orderId]['items'][$itemId]['created'] && isset($fields['item']) && $fields['item'][$change['field']]) {
                    $orders[$orderId]['items'][$itemId][$fields['item'][$change['field']]] = $change['newValue'];
                }
            } else {
                if (isset($fields['delivery'][$change['field']]) && $fields['delivery'][$change['field']] == 'service') {
                    $orders[$orderId]['delivery']['service']['code'] = self::historyNewValue($change['newValue']);
                } elseif (isset($fields['delivery'][$change['field']])) {
                    $orders[$orderId]['delivery'][$fields['delivery'][$change['field']]] = self::historyNewValue($change['newValue']);
                } elseif (isset($fields['orderAddress'][$change['field']])) {
                    $orders[$orderId]['delivery']['address'][$fields['orderAddress'][$change['field']]] = $change['newValue'];
                } elseif (isset($fields['integrationDelivery'][$change['field']])) {
                    $orders[$orderId]['delivery']['service'][$fields['integrationDelivery'][$change['field']]] = self::historyNewValue($change['newValue']);
                } elseif (isset($fields['customerContragent'][$change['field']])) {
                    $orders[$orderId][$fields['customerContragent'][$change['field']]] = self::historyNewValue($change['newValue']);
                } elseif (strripos($change['field'], 'custom_') !== false) {
                    $orders[$orderId]['customFields'][str_replace('custom_', '', $change['field'])] = self::historyNewValue($change['newValue']);
                } elseif (isset($fields['order'][$change['field']])) {
                    $orders[$orderId][$fields['order'][$change['field']]] = self::historyNewValue($change['newValue']);
                }

                if (isset($change['created'])) {
                    $orders[$orderId]['created'] = 1;
                }

                if (isset($change['deleted'])) {
                    $orders[$orderId]['deleted'] = 1;
                }
            }
        }

        return array_values($orders);
    }

    /**
     * @param $value mixed
     * @return string
     */
    public static function historyNewValue($value)
    {
        if (isset($value['code'])) {
            return $value['code'];
        } else {
            return $value;
        }
    }

    /**
     * @param $inputArray mixed
     * @return array
     */
    public static function removeEmpty($inputArray)
    {
        $outputArray = array();
        if (!empty($inputArray)) {
            foreach ($inputArray as $key => $element) {
                if (!empty($element) || $element === 0 || $element === '0') {
                    if (is_array($element)) {
                        $element = self::removeEmpty($element);
                    }
                    $outputArray[$key] = $element;
                }
            }
        }

        return $outputArray;
    }
}