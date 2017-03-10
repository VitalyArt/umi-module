<?php

class RCrmIcml
{
    /** @var  DOMDocument $dd */
    protected $dd;
    /** @var  DOMElement $eCategories */
    protected $eCategories;
    /** @var  DOMElement $eOffers */
    protected $eOffers;
    /** @var string $shopName */
    protected $shopName = 'shop';
    /** @var  string $shopUrl */
    protected $shopUrl;

    public function __construct()
    {
        $domainsCollection = domainsCollection::getInstance();
        $domainsCollectionList = $domainsCollection->getList();
        $domainCollection = $domainsCollectionList[1];
        
        if (mainConfiguration::getInstance()->get('system', 'server-protocol')) {
            $serverProtocol = mainConfiguration::getInstance()->get('system', 'server-protocol');
        } else {
            $serverProtocol = 'http';
        }

        $this->shopUrl = $serverProtocol . '://' . $domainCollection->getHost();
    }

    public function generateICML()
    {
        $string = '<?xml version="1.0" encoding="UTF-8"?>
            <yml_catalog date="' . date('Y-m-d H:i:s') . '">
                <shop>
                    <name>' . $this->shopName . '</name>
                    <categories/>
                    <offers/>
                </shop>
            </yml_catalog>
        ';

        $xml = new SimpleXMLElement(
            $string,
            LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE
        );

        $this->dd = new DOMDocument();
        $this->dd->preserveWhiteSpace = false;
        $this->dd->formatOutput = true;
        $this->dd->loadXML($xml->asXML());

        $this->eCategories = $this->dd->getElementsByTagName('categories')->item(0);
        $this->eOffers = $this->dd->getElementsByTagName('offers')->item(0);

        $this->addCategories();
        $this->addOffers();

        $downloadPath = __DIR__ . '/../../../../../';

        if (!file_exists($downloadPath)) {
            mkdir($downloadPath, 0755);
        }

        $this->dd->saveXML();
        $this->dd->save($downloadPath . 'retailcrm.xml');
    }

    /**
     *
     */
    private function addCategories()
    {
        $categories = new selector('pages');
        $categories->types('hierarchy-type')->name('catalog', 'category');

        $result = $categories->result();

        foreach ($result as $category) {
            /** @var umiHierarchyElement $category */

            /** @var DOMElement $e */
            $e = $this->eCategories->appendChild(
                $this->dd->createElement(
                    'category', $category->getName()
                )
            );

            $e->setAttribute('id', $category->getId());

            if ($category->getRel() > 0) {
                $e->setAttribute('parentId', $category->getRel());
            }
        }
    }

    private function getObjectUrl(umiHierarchyElement $obj)
    {
        $url = '/' . $obj->getAltName();

        $ids = array($obj->getRel());
        $parent = new umiHierarchyElement($obj->getRel());

        while (true) {
            $url = '/' . $parent->getAltName() . $url;

            if ($parent->getRel() != 0 && !in_array($parent->getRel(), $ids)) {
                $parent = new umiHierarchyElement($parent->getRel());
                array_push($ids, $parent->getRel());
            } else {
                break;
            }
        }

        $url = $this->shopUrl . $url;

        return $url;
    }

    private function getCombinationsFromMultyArray($sourceData)
    {
        $sourceDataKeys = array();
        foreach ($sourceData as $key => $value) {
            $sourceDataKeys[] = $key;
        }

        $data = array();
        $data[] = '';
        for ($i = 0; $i < count($sourceData); $i++) {
            $oldData = $data;
            $data = array();

            foreach ($oldData as $value) {
                foreach ($sourceData[$sourceDataKeys[$i]] as $value2) {
                    $data[] = (!empty($value) ? $value . ',' : '') . $sourceDataKeys[$i] . '-' . $value2;
                }
            }
        }

        $resultData = array();
        foreach ($data as $value) {
            $items = explode(',', $value);
            $columns = array();

            foreach ($items as $item) {
                $item = explode('-', $item);
                $columns[$item[0]] = $item[1];
            }

            $resultData[] = $columns;
        }

        return $resultData;
    }

    private function addOffers()
    {
        $offers = new selector('pages');
        $offers->types('hierarchy-type')->name('catalog', 'object');

        $result = $offers->result();

        foreach ($result as $offer) {
            /** @var umiHierarchyElement $offer */

            $objects = umiObjectsCollection::getInstance();

            $offerObject = new umiObject($offer->getObjectId());

            /** @var umiFieldsGroup $optionsObject */
            $optionsObject = $offerObject->getType()->getFieldsGroupByName('catalog_option_props');

            $options = array();
            $optionValues = array();
            $optionGroups = array();
            $optionPrices = array();
            foreach ($optionsObject->getFields() as $optionField) {
                /** @var umiField $optionField */

                $optionGroups[$optionField->getId()] = $optionField;

                $values = $offerObject->getValue($optionField->getName());

                foreach ($values as $value) {
                    $valueObject = $objects->getObject($value['rel']);
                    $options[$optionField->getId()][] = $valueObject->getId();

                    $optionPrices[$valueObject->getId()] = $value['float'];
                    $optionValues[$valueObject->getId()] = $valueObject;
                }
            }

            if (count($options)) {
                $offerOptions = $this->getCombinationsFromMultyArray($options);
            } else {
                // Если нет опционных товаров(товарных предложений) передаём массив с 1 пустым элементом - базовый товар
                $offerOptions = array('');
            }

            foreach ($offerOptions as $offerOption) {
                if (!empty($offerOption)) {
                    $options = array();
                    foreach ($offerOption as $offerOptionId => $offerOptionValue) {
                        $options[] = $offerOptionId . '_' . $offerOptionValue;
                    }

                    $offerId = $offer->getId() . '#' . implode('-', $options);
                } else {
                    $offerId = $offer->getId();
                }

                /** @var DOMElement $e */
                $e = $this->eOffers->appendChild($this->dd->createElement('offer'));
                $e->setAttribute('id', $offerId);
                $e->setAttribute('productId', $offer->getId());
                $quantity = $offerObject->getPropByName('common_quantity')->getValue();
                $e->setAttribute('quantity', !empty($quantity) ? $quantity : 0);

                /**
                 * Offer activity
                 */
                $activity = $offer->getIsActive() == 1 ? 'Y' : 'N';
                $e->appendChild(
                    $this->dd->createElement('productActivity')
                )->appendChild(
                    $this->dd->createTextNode($activity)
                );

                /**
                 * Offer category
                 */
                $e->appendChild($this->dd->createElement('categoryId'))
                    ->appendChild(
                        $this->dd->createTextNode($offer->getRel())
                    );

                /**
                 * Name & price
                 */
                if (!empty($offerOption)) {
                    $options = array();
                    foreach ($offerOption as $offerOptionId => $offerOptionValue) {
                        $options[] = $optionGroups[$offerOptionId]->getTitle() . ': ' . $optionValues[$offerOptionValue]->getName();
                    }
                    $offerName = $offer->getName() . ' (' . implode(', ', $options) . ')';
                } else {
                    $offerName = $offer->getName();
                }

                $e->appendChild($this->dd->createElement('name'))
                    ->appendChild($this->dd->createTextNode($offerName));

                $e->appendChild($this->dd->createElement('productName'))
                    ->appendChild($this->dd->createTextNode($offer->getName()));

                $price = $offerObject->getPropByName('price')->getValue();

                if (!empty($offerOption)) {
                    foreach ($offerOption as $offerOptionId => $offerOptionValue) {
                        $price += $optionPrices[$offerOptionValue];
                    }
                }

                $e->appendChild($this->dd->createElement('price'))
                    ->appendChild($this->dd->createTextNode($price));

                /**
                 * Options
                 */
                if (!empty($offerOption)) {
                    foreach ($offerOption as $offerOptionId => $offerOptionValue) {
                        $option = $this->dd->createElement('param');
                        $option->setAttribute('code', $optionGroups[$offerOptionId]->getName());
                        $option->setAttribute('name', $optionGroups[$offerOptionId]->getTitle());
                        $option->appendChild($this->dd->createTextNode($optionValues[$offerOptionValue]->getName()));
                        $e->appendChild($option);
                    }
                }

                /**
                 * Image
                 */
                /** @var umiImageFile $photo */
                if ($offerObject->getPropByName('images') !== null) {
                    $photos = $offerObject->getPropByName('images')->getValue();

                    if (is_array($photos) && count($photos)) {
                        $photo = reset($photos);
                        $photoPath = $this->shopUrl . $photo->getFilePath(true);
                        $e->appendChild($this->dd->createElement('picture'))->appendChild($this->dd->createTextNode($photoPath));
                    }
                }

                /**
                 * Url
                 */
                $url = $this->getObjectUrl($offer);
                $e->appendChild($this->dd->createElement('url'))
                    ->appendChild(
                        $this->dd->createTextNode($url)
                    );

                /**
                 * Additional characteristics
                 */
                if ($offerObject->getPropByName('weight')) {
                    $weight = $this->dd->createElement('param');
                    $weight->setAttribute('code', 'weight');
                    $weight->setAttribute('name', 'Вес');
                    $weight->appendChild($this->dd->createTextNode($offerObject->getPropByName('weight')->getValue() * 1000));
                    $e->appendChild($weight);
                }
            }
        }
    }
}
