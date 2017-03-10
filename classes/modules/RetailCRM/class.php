<?php
class RetailCRM extends def_module {
    public function __construct()
    {
        parent::__construct();

        if (cmsController::getInstance()->getCurrentMode() == 'admin') {
            $this->__loadLib('__admin.php');
            $this->__implement('__RetailCRM_adm');
        }

        // Подключаем модуль интернет магазина
        cmsController::getInstance()->getModule("emarket");

        // RetailCRM classes
        $this->__loadLib('classes/retailcrm/RCrmActions.php');
        $this->__loadLib('classes/retailcrm/RCrmApiClient.php');
        $this->__loadLib('classes/retailcrm/RCrmApiResponse.php');
        $this->__loadLib('classes/retailcrm/RCrmHistory.php');
        $this->__loadLib('classes/retailcrm/RCrmHttpClient.php');
        $this->__loadLib('classes/retailcrm/RCrmIcml.php');
        $this->__loadLib('classes/retailcrm/RCrmProxy.php');

        // Exceptions
        $this->__loadLib('classes/retailcrm/RCrmCurlException.php');
        $this->__loadLib('classes/retailcrm/RCrmJsonException.php');

        // Helpers
        $this->__loadLib('classes/retailcrm/RCrmHelpers.php');
        $this->__implement('RCrmHelpers');

        // Events
        $this->__loadLib('__events.php');
        $this->__implement('__RetailCRM_events');
    }
}