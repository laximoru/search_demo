<?php

namespace LaximoSearch\controllers;

use Exception;
use GuayaquilLib\objects\oem\AttributeObject;
use GuayaquilLib\objects\oem\ImageMapObject;
use GuayaquilLib\objects\oem\PartListObject;
use GuayaquilLib\objects\oem\QuickDetailListObject;
use GuayaquilLib\objects\oem\UnitObject;
use GuayaquilLib\objects\oem\VehicleObject;
use GuayaquilLib\Oem;
use GuayaquilLib\ServiceOem;
use Laximo\Search\responseObjects\UsOemParams;
use Laximo\Search\responseObjects\UsSearchByOemsResponse;

/**
 * @property array details
 * @property array units
 * @property string detailIds
 * @property UsOemParams vehicleParams
 * @property UnitObject unit
 * @property ImageMapObject imagemap
 * @property array detailImageCodes
 * @property array oemServiceData
 * @property |null highlightCode
 */
class unitController extends Controller
{
    public function show()
    {
        $autoId = $this->input->getInt('autoId');
        $detailIds = $this->input->getInt('detailIds');
        $oem = $this->input->getString('oem');

        $us = $this->getSearchService();

        $oems = $us->resolveDetailIdsToOemsForAutoInfo($autoId, explode(',', $detailIds));
        $params = $us->getParamsForOemService($autoId)->data;

        $oemServiceData = [];
        $service = $this->getOemService();
        $notSupported = false;
        foreach ($oems->data as $originalDetail) {
            try {
                $oemServiceData[] = $service->findPartInVehicle($params->catalog, $params->ssd, $originalDetail->oem, $this->getOemLocale());
            } catch (Exception $ex) {
                if (strpos($ex->getMessage(), 'NOTSUPPORTED')) {
                    $notSupported = true;
                } else {
                    $this->renderError('500', $ex->getMessage());
                }
            }
        }

        if ($notSupported) {
            $this->render('unit', 'notSupported.twig');
        } else {
            $nodes = [];
            foreach ($oemServiceData as $webServiceData) {
                foreach ($webServiceData->getCategories() as $category) {
                    foreach ($category->getUnits() as $unit) {
                        $nodes[] = $unit;
                    }
                }
            }

            if (count($nodes) == 1) {
                /** @var $unit UnitObject */
                $unit = reset($nodes);

                $this->redirect('unit', 'unit', [
                    'detailIds' => $detailIds,
                    'unitid' => $unit->getUnitId(),
                    'ssd' => $unit->getSsd(),
                    'catalog' => $params->catalog,
                    'vehicleId' => $params->vehicleId,
                    'oem' => $oem,
                    'autoId' => $autoId
                ]);
            }

            $this->pathway->addItem('Unified Search', $this->getBaseUrl());
            $this->pathway->addItem($this->getLanguage()->t('SEARCH_DEMO'), $this->createUrl('search', 'show'));
            $this->pathway->addItem($oem, '');

            $this->oemServiceData = $oemServiceData;
            $this->detailIds = $detailIds;
            $this->vehicleParams = $params;
            $this->autoId = $autoId;

            $this->render('unit', 'view.twig', true);
        }
    }

    /**
     * @param $name
     * @param AttributeObject[] $attrs
     * @return mixed|string
     */
    private function getAttr($name, array $attrs)
    {
        foreach ($attrs as $attr) {
            if ($attr->getName() === $name) {
                return $attr->getValue();
            }
        }
        return '';
    }

    public function unit()
    {
        $autoId = $this->input->getString('autoId');
        $oem = $this->input->getString('oem');
        $unitId = $this->input->getString('unitid');
        $ssd = $this->input->getString('ssd');
        $catalog = $this->input->getString('catalog');
        $vehicleId = $this->input->getString('vehicleId');

        $service = $this->getOemService();
        /** @var PartListObject $detailsList */
        /** @var UnitObject $unit */
        /** @var ImageMapObject $imageMap */
        /** @var VehicleObject $vehicleInfo */
        try {
            list($unit, $detailsList, $imageMap, $vehicleInfo) = $service->queryButch([
                Oem::getUnitInfo($catalog, $ssd, $unitId, $this->getOemLocale()),
                Oem::listPartsByUnit($catalog, $ssd, $unitId, $this->getOemLocale()),
                Oem::listImageMapByUnit($catalog, $ssd, $unitId),
                Oem::getVehicleInfo($catalog, $vehicleId, $ssd, $this->getOemLocale()),
            ]);
        } catch (Exception $ex) {
            $this->renderError('500', $ex->getMessage());
        }

        $oems = [];
        $detailImageCodes = [];
        $this->highlightCode = null;

        if (!empty($detailsList->getParts())) {
            foreach ($detailsList->getParts() as $detail) {
                if (!empty($detail->getOem())) {
                    $oems[] = $detail->getOem();
                }
            }
        }

        $us = $this->getSearchService();
        $searchByOems = $us->searchByOems($autoId, $oems, $this->getLanguage()->getLocalization());
        $details = [];
        foreach ($detailsList->getParts() as $original) {
            foreach ($searchByOems->data as $part) {
                if ($this->filterOem($part->oem) == $this->filterOem($original->getOem()) && count($part->details)) {
                    foreach ($part->details as $item) {
                        $detailImageCodes[$item->oem . $item->brand] = $original->getCodeOnImage();
                        $item->amount = $this->getAttr('amount', $original->getAttributes());
                        $item->note = $this->getAttr('note', $original->getAttributes());
                        $item->code = $original->getCodeOnImage();
                        $details[$original->getCodeOnImage()][$item->oem . $item->brand] = $item;

                        if ($item->oem == $oem) {
                            $this->highlightCode = 'i' . $original->getCodeOnImage();
                        }
                    }
                }
            }
        }

        $this->pathway->addItem('Unified Search', $this->getBaseUrl());
        $this->pathway->addItem($this->getLanguage()->t('SEARCH_DEMO'), $this->createUrl('search', 'show'));
        $this->pathway->addItem($unit->getName(), '');

        $this->unit = $unit;
        $this->details = $details;
        $this->oem = $oem;
        $this->imagemap = $imageMap;
        $this->detailImageCodes = $detailImageCodes;
        $this->vehicleInfo = $vehicleInfo;

        $this->render('unit', 'unit.twig', true);
    }
}