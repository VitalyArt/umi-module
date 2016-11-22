<?php

abstract class RetailcrmHelpers {

    /**
     * @param $mapArr array
     * @return array
     */
    public function getRelationMap($mapArr) {
        if(empty($mapArr)) return array();

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
    public function getRelationByMap($map, $item, $reversed = false) {
        if(!$reversed) {
            if(isset($map[$item]) && !empty($map[$item]))
                return $map[$item];
            else
                return null;
        } else {
            foreach ($map as $umiStatusOrder => $crmStatusOrder) {
                if($crmStatusOrder == $item)
                    return $umiStatusOrder;
            }
            return null;
        }
    }
}