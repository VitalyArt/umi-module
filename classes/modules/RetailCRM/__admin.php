<?php

abstract class __RetailCRM_adm extends baseModuleAdmin
{

    public function manage()
    {
        $config = mainConfiguration::getInstance();

        $crmUrl = (string)$config->get('retailcrm', 'crmUrl');
        $apiKey = (string)$config->get('retailcrm', 'apiKey');

        $params = array(
            'access' => array(
                'string:crmUrl' => $crmUrl,
                'string:apiKey' => $apiKey
            )
        );

        // Костыль для локализации полей
        $translations = array();

        if (!empty($apiKey) && !empty($crmUrl)) {
            $api = new RCrmProxy(
                $config->get('retailcrm', 'crmUrl'),
                $config->get('retailcrm', 'apiKey')
            );

            if($api->paymentTypesList() !== false) {
                /*
                 * Order Payment Types
                 */
                /** @var RCrmApiClient $api */
                $crmPaymentTypes = $api->paymentTypesList()->getPaymentTypes();
                $umiPaymentTypes = new selector('objects');
                $umiPaymentTypes->types('object-type')->name('emarket', 'payment');

                $map = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderPaymentTypeMap'));

                $orderPaymentsMapping = array();
                foreach ($umiPaymentTypes->result() as $umiPaymentType) {
                    $umiPaymentTypeId = $umiPaymentType->getPropByName('payment_type_id')->getObjectId();
                    $umiPaymentTypeName = $umiPaymentType->getName();

                    $translations['order-payment-type-' . $umiPaymentTypeId] = $umiPaymentTypeName;

                    $orderPaymentsMapping['select:order-payment-type-' . $umiPaymentTypeId] = array();
                    $orderPaymentsMapping['select:order-payment-type-' . $umiPaymentTypeId]['value'] = RCrmHelpers::getRelationByMap($map,
                        $umiPaymentTypeId);

                    $orderPaymentsMapping['select:order-payment-type-' . $umiPaymentTypeId]['none'] = '';
                    foreach ($crmPaymentTypes as $crmPaymentType) {
                        $orderPaymentsMapping['select:order-payment-type-' . $umiPaymentTypeId][$crmPaymentType['code']] = $crmPaymentType['name'];
                    }
                }
                $params['orderPaymentsMapping'] = $orderPaymentsMapping;

                /*
                 * Order Delivery Type
                 */
                $crmDeliveryTypes = $api->deliveryTypesList()->getDeliveryTypes();
                $umiDeliveryTypes = new selector('objects');
                $umiDeliveryTypes->types('object-type')->name('emarket', 'delivery');

                $map = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderDeliveryTypeMap'));

                $orderDeliveryTypesMapping = array();
                foreach ($umiDeliveryTypes as $umiDeliveryType) {
                    $umiDeliveryTypeId = $umiDeliveryType->getId();
                    $umiDeliveryTypeName = $umiDeliveryType->getName();

                    $translations['order-delivery-type-' . $umiDeliveryTypeId] = $umiDeliveryTypeName;

                    $orderDeliveryTypesMapping['select:order-delivery-type-' . $umiDeliveryTypeId] = array();
                    $orderDeliveryTypesMapping['select:order-delivery-type-' . $umiDeliveryTypeId]['value'] = RCrmHelpers::getRelationByMap($map,
                        $umiDeliveryTypeId);

                    $orderDeliveryTypesMapping['select:order-delivery-type-' . $umiDeliveryTypeId]['none'] = '';
                    foreach ($crmDeliveryTypes as $crmDeliveryType) {
                        $orderDeliveryTypesMapping['select:order-delivery-type-' . $umiDeliveryTypeId][$crmDeliveryType['code']] = $crmDeliveryType['name'];
                    }

                }
                $params['orderDeliveryTypesMapping'] = $orderDeliveryTypesMapping;

                /*
                 * Order Payment Statuses
                 */
                $crmPaymentStatuses = $api->paymentStatusesList()->getPaymentStatuses();
                $umiPaymentStatuses = new selector('objects');
                $umiPaymentStatuses->types('object-type')->name('emarket', 'order_payment_status');

                $map = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderPaymentStatusMap'));

                $orderPaymentStatusesMapping = array();
                foreach ($umiPaymentStatuses->result() as $umiPaymentStatus) {
                    $umiPaymentStatusId = $umiPaymentStatus->getId();
                    $umiPaymentStatusName = $umiPaymentStatus->getName();

                    $translations['order-payment-status-' . $umiPaymentStatusId] = $umiPaymentStatusName;

                    $orderPaymentStatusesMapping['select:order-payment-status-' . $umiPaymentStatusId] = array();
                    $orderPaymentStatusesMapping['select:order-payment-status-' . $umiPaymentStatusId]['value'] = RCrmHelpers::getRelationByMap($map,
                        $umiPaymentStatusId);

                    $orderPaymentStatusesMapping['select:order-payment-status-' . $umiPaymentStatusId]['none'] = '';
                    foreach ($crmPaymentStatuses as $crmPaymentStatus) {
                        $orderPaymentStatusesMapping['select:order-payment-status-' . $umiPaymentStatusId][$crmPaymentStatus['code']] = $crmPaymentStatus['name'];
                    }
                }
                $params['orderPaymentStatusesMapping'] = $orderPaymentStatusesMapping;

                /*
                 * Order Statuses
                 */
                $crmOrderStatuses = $api->statusesList()->getStatuses();
                $umiOrderStatuses = new selector('objects');
                $umiOrderStatuses->types('object-type')->name('emarket', 'order_status');

                $map = RCrmHelpers::getRelationMap($config->get('retailcrm', 'orderStatusMap'));

                $params['orderStatusesMapping'] = array();
                foreach ($umiOrderStatuses->result() as $umiOrderStatus) {
                    $codeName = $umiOrderStatus->getValue('codename');

                    $translations['order-status-' . $codeName] = $umiOrderStatus->getName();

                    $params['orderStatusesMapping']['select:order-status-' . $codeName] = array();
                    $params['orderStatusesMapping']['select:order-status-' . $codeName]['value'] = RCrmHelpers::getRelationByMap($map, $codeName);
                    $params['orderStatusesMapping']['select:order-status-' . $codeName]['none'] = '';

                    foreach ($crmOrderStatuses as $crmOrderStatus) {
                        $params['orderStatusesMapping']['select:order-status-' . $codeName][$crmOrderStatus['code']] = $crmOrderStatus['name'];
                    }
                }

                $params['guidesMapping']['select:country'] = array();
                $params['guidesMapping']['select:country']['value'] = $config->get('retailcrm', 'countryGuideId');
                $params['guidesMapping']['select:country']['none'] = '';

                $objectTypes = umiObjectTypesCollection::getInstance();

                foreach ($objectTypes->getGuidesList() as $guideId => $guideName) {
                    $params['guidesMapping']['select:country'][$guideId] = $guideName;
                }
            } else {
                // TODO: Добавить вывод ошибки, что данные некорректные
                $params['incorrect-data'] = array();
            }
        }

        $mode = getRequest("param0");
        if ($mode == "do") {
            $params = $this->expectParams($params);

            $config->set('retailcrm', 'crmUrl', $params['access']['string:crmUrl']);
            $config->set('retailcrm', 'apiKey', $params['access']['string:apiKey']);

            if (!empty($params['access']['string:crmUrl']) && !empty($params['access']['string:apiKey'])) {
                /*
                 * Order Statuses
                 */
                if (!empty($params['orderStatusesMapping'])) {
                    $orderStatusMap = array();
                    foreach ($params['orderStatusesMapping'] as $umiOrderStatus => $crmOrderStatus) {
                        $umiOrderStatus = str_replace('select:order-status-', '', $umiOrderStatus);
                        $orderStatusMap[] = $umiOrderStatus . ' <-> ' . $crmOrderStatus;
                    }
                    $config->set('retailcrm', 'orderStatusMap', $orderStatusMap);
                }

                /*
                 * Order Payment Types
                 */
                if (!empty($params['orderPaymentsMapping'])) {
                    $orderPaymentTypeMap = array();
                    foreach ($params['orderPaymentsMapping'] as $umiOrderPaymentType => $crmOrderPaymentType) {
                        $umiOrderPaymentType = str_replace('select:order-payment-type-', '', $umiOrderPaymentType);
                        $orderPaymentTypeMap[] = $umiOrderPaymentType . ' <-> ' . $crmOrderPaymentType;
                    }
                    $config->set('retailcrm', 'orderPaymentTypeMap', $orderPaymentTypeMap);
                }

                /*
                 * Order Payment Statuses
                 */
                if (!empty($params['orderPaymentStatusesMapping'])) {
                    $orderPaymentStatusMap = array();
                    foreach ($params['orderPaymentStatusesMapping'] as $umiOrderPaymentStatus => $crmOrderPaymentStatus) {
                        $umiOrderPaymentStatus = str_replace('select:order-payment-status-', '', $umiOrderPaymentStatus);
                        $orderPaymentStatusMap[] = $umiOrderPaymentStatus . ' <-> ' . $crmOrderPaymentStatus;
                    }
                    $config->set('retailcrm', 'orderPaymentStatusMap', $orderPaymentStatusMap);
                }

                /*
                 * Order Delivery Types
                 */
                if (!empty($params['orderDeliveryTypesMapping'])) {
                    $orderDeliveryTypeMap = array();
                    foreach ($params['orderDeliveryTypesMapping'] as $umiOrderDeliveryType => $crmOrderDeliveryType) {
                        $umiOrderDeliveryType = str_replace('select:order-delivery-type-', '', $umiOrderDeliveryType);
                        $orderDeliveryTypeMap[] = $umiOrderDeliveryType . ' <-> ' . $crmOrderDeliveryType;
                    }
                    $config->set('retailcrm', 'orderDeliveryTypeMap', $orderDeliveryTypeMap);
                }

                if (!empty($params['guidesMapping'])) {
                    foreach ($params['guidesMapping'] as $guideName => $guideId) {
                        $guideName = str_replace('select:', '', $guideName);
                        $config->set('retailcrm', $guideName . 'GuideId', $guideId);
                    }
                }
            }

            $this->chooseRedirect();
        }

        $this->setDataType("settings");
        $this->setActionType("modify");

        $data = $this->prepareData($params, "settings");

        // Реалзиация костыля для локализации полей
        foreach ($data['nodes:group'] as $groupKey => $group) {
            foreach ($group['nodes:option'] as $optionKey => $option) {
                $label = $option['attribute:label'];

                if (strpos($label, 'option-') > -1) {
                    $optionName = str_replace('option-', '', $label);

                    if (isset($translations[$optionName])) {
                        $data['nodes:group'][$groupKey]['nodes:option'][$optionKey]['attribute:label'] = $translations[$optionName];
                    }
                }

            }
        }

        $this->setData($data);
        $this->doData();
    }
}