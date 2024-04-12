<?php

namespace LaximoSearch\controllers;

use Exception;
use Laximo\Search\exceptions\AccessDeniedException;

ini_set('max_execution_time', 600);

/**
 * @property array files
 * @property float totalPages
 * @property array tasks
 * @property array errors
 * @property array errorsFiles
 * @property string downloadErrorsFileUrl
 * @property string uploadFileUrl
 */
class filesController extends Controller
{
    public function show()
    {
        $format = $this->input->getString('format', false);

        if (!$this->user->isLoggedIn()) {
            $this->renderError('401', '401 Unavailable', $this->input->getString('format', null));
        }

        $us = $this->getSearchService();

        $size = $this->input->getInt('size', 20);
        $page = $this->input->getInt('page', 0);
        $skip = $page * $size;

        try {
            $tasks = $us->offersList($skip, $size)->data;

            if ($tasks) {
                $this->pathway->addItem('Unified Search', $this->getBaseUrl());
                $this->pathway->addItem($this->getLanguage()->t('SEARCH_DEMO'), $this->createUrl('search', 'show'));
                $this->pathway->addItem($this->getLanguage()->t('LOAD_OFFERS'), $this->createUrl('files', 'show'));
            }

            $this->tasks = $tasks;
            $this->errors = [];

            $this->uploadFileUrl = $this->createUrl('files', 'load');
            $this->downloadErrorsFileUrl = $this->createUrl('files', 'download');

            $this->render('files', 'view.twig', true, $format);
        } catch (AccessDeniedException $ex) {
            $this->render('files', 'noaccess.twig', true, $format);
        }
    }

    public function downloadDemo()
    {
        $name = 'importFileExample.csv';

        $path = ROOTPATH . '/template/assets/' . $name;
        if (file_exists($path)) {
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Cache-Control: public"); // needed for internet explorer
            header("Content-Type: application/zip");
            header("Content-Transfer-Encoding: Binary");
            header("Content-Length:" . filesize($path));
            header("Content-Disposition: attachment; filename=$name");
            readfile($path);
            die();
        } else {
            die("Error: File not found.");
        }
    }

    public function load()
    {
        $delimiters = [
            'default' => null,
            'tab' => "\t",
            'coma' => ",",
            'semicolon' => ";",
        ];
        $file = $this->input->getFiles('file');
        $charset = $this->input->getString('charset', 'default');
        $delimiter = $this->input->getString('delimiter', 'default');

        if ($charset == 'default') {
            $charset = null;
        }
        $delimiter = $delimiters[$delimiter];

        if (!$file || !$file['tmp_name']) {
            die('Upload error! File is long.');
        }

        try {
            $res = $this->getSearchService()->offersProcess($file['tmp_name'], $file['name'], $delimiter, $charset);
        } catch (Exception $ex) {
            echo $this->getLanguage()->t($ex->getMessage());
            die;
        }

        $this->responseJson($res);
    }

    public function downloadErrors()
    {
        $taskId = $this->input->getInt('taskId');
        $us = $this->getSearchService();
        $task = $us->offersGet($taskId);
        $res = $us->offersErrors($taskId);

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=errors." . $task->sourceFile);
        echo $res;
        die();
    }

    public function downloadSource()
    {
        $taskId = $this->input->getInt('taskId');
        $us = $this->getSearchService();
        $task = $us->offersGet($taskId);
        $res = $us->offersSource($taskId);

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=" . $task->sourceFile);
        echo $res;
        die();
    }
}