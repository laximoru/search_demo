<?php

namespace LaximoSearch\controllers;

use Exception;
use Laximo\Search\responseObjects\UsAutoInfo;
use Laximo\Search\responseObjects\UsSearchResultPageResponse;
use Laximo\Search\responseObjects\UsVehicle;

/**
 * @property UsSearchResultPageResponse result
 * @property UsVehicle[] foundedVehicles
 * @property bool query
 * @property float|int totalPages
 * @property bool foundTags
 * @property bool foundVendorCodes
 * @property array options
 * @property array tags
 * @property array languages
 * @property UsAutoInfo vehicleInfo
 * @property bool searchAvailable
 * @property int page
 * @property string vin
 */
class searchController extends Controller
{
    public function show()
    {
        $query = $this->input->getString('query', '');
        $autoId = $this->input->getInt('autoId');
        $size = $this->input->getInt('size', 20);
        $page = $this->input->getInt('page', 0);
        $skip = $page * $size;
        $options = $this->input->get('options');
        $tags = $this->input->get('tags');

        $us = $this->getSearchService();

        if ($autoId) {
            $this->vehicleInfo = $us->indexationGetAutoInfo($autoId)->data;
        }

        if ($options) {
            foreach ($options as $key => $option) {
                $options[$key] = $option ? 'true' : 'false';
            }
        }

        $this->query = '';

        if (strlen($query) || $tags && $options && $options['tagToggler']) {
            $this->searchAvailable = true;
            $this->result = $us->search(
                $query
                , $autoId && $this->vehicleInfo->state == UsVehicle::STATE_SUCCESS ? $autoId : null
                , ($tags && $options && $options['tagToggler'] ? $tags : [])
                , $this->getLanguage()->getLocalization()
                , $skip
                , $size);

            $this->foundTags = false;
            $this->foundVendorCodes = false;
            if (!empty($this->result->data)) {
                foreach ($this->result->data as $detail) {
                    if (is_array($detail->tags) && count($detail->tags)) {
                        $this->foundTags = true;
                    }
                    if (!empty($detail->vendorCode)) {
                        $this->foundVendorCodes = true;
                    }
                }

            }
            $autoInfoId = $this->result->parsedRequest->getAutoInfoId();
            $detectedVehicleIdent = $this->result->parsedRequest->getDetectedVehicleIdent();

            if ($detectedVehicleIdent) {
                $identifiedVehicles = $us->vehicleIdentify($detectedVehicleIdent, $this->getLanguage()->getLocalization());
                $this->foundedVehicles = $identifiedVehicles->data;

                if ($autoInfoId) {
                    if ($autoInfoId != $autoId) {
                        $this->vehicleInfo = $us->indexationGetAutoInfo($autoInfoId)->data;
                    }
                } else if (!empty($detectedVehicleIdent) && !$autoId) {
                    $vehicleCount = count($identifiedVehicles->data);
                    if ($vehicleCount > 1) {
                        $this->redirect('search', 'select', ['vin' => $detectedVehicleIdent, 'query' => $query]);
                    } else {
                        if ($identifiedVehicles->data[0]->state == UsVehicle::STATE_CREATED) {
                            $this->vehicleInfo = $us->indexationIndexVehicle($identifiedVehicles->data[0]->id)->data;
                        } else {
                            $this->vehicleInfo = $us->indexationGetAutoInfo($identifiedVehicles->data[0]->id)->data;
                        }
                    }
                }
            }

            $this->totalPages = !empty($this->result->totalPages) ? ceil($this->result->totalPages / $size) : 0;
        }

        $this->query = $query;
        $this->options = $options;
        $this->tags = $tags;
        $this->page = $page;
//        $this->advanced_search = true;

        $this->pathway->addItem('Unified Search', $this->getBaseUrl());
        $this->pathway->addItem($this->getLanguage()->t('SEARCH_DEMO'), $this->createUrl('search', 'show'));

        $this->render('search', 'view.twig', true);
    }

    public function getVinProgress()
    {
        $autoId = $this->input->getInt('autoId');
        $percent = 100;

        if ($autoId) {
            try {
                $vehicle = $this->getSearchService()->indexationGetAutoInfo($autoId);
                if ($vehicle->data->state == UsVehicle::STATE_SUCCESS) {
                    $percent = 100;
                } else if ($vehicle->data->state == UsVehicle::STATE_PROCESSING) {
                    switch ($vehicle->data->processingStage) {
                        case UsAutoInfo::PROCESSING_STAGE_IDENTIFIED:
                            $percent = 25;
                            break;
                        case UsAutoInfo::PROCESSING_STAGE_OEMS_RESOLVED:
                            $percent = 50;
                            break;
                        case UsAutoInfo::PROCESSING_STAGE_DETAILS_RESOLVED:
                            $percent = 75;
                            break;
                        default:
                            $percent = 100;
                    }
                } else {
                    $percent = 0;
                }
            } catch (Exception $ex) {
            }
        }
        $this->responseJson((object)['indexationProgress' => $percent]);
    }

    public function select()
    {
        $vin = $this->input->getString('vin');
        $us = $this->getSearchService();

        $this->pathway->addItem('Unified Search', $this->getBaseUrl());
        $this->pathway->addItem($this->getLanguage()->t('SEARCH_DEMO'), $this->createUrl('search', 'show'));
        $this->pathway->addItem($vin, '');

        $this->foundedVehicles = $us->vehicleIdentify($vin, $this->getLanguage()->getLocalization())->data;
        $this->query = $this->input->getString('query');
        $this->vin = $vin;
        $this->render('search', 'select.twig', true);
    }

    public function index()
    {
        $autoId = $this->input->getInt('autoId');
        $query = $this->input->getString('query');

        $us = $this->getSearchService();
        $vehicleInfo = $us->indexationGetAutoInfo($autoId)->data;
        if ($vehicleInfo->state == UsVehicle::STATE_CREATED) {
            $us->indexationIndexVehicle($autoId);
        }
        $this->redirect('search', 'show', ['query' => $query, 'autoId' => $autoId]);
    }

    public function getAutocomplete()
    {
        $us = $this->getSearchService();
        $query = $this->input->getString('query');

        if ($query && strlen($query) > 3) {
            $res = $us->complete($query);
            $this->responseJson($res->data);
        }

        $this->responseJson(['queryCompletions' => []]);
    }
}