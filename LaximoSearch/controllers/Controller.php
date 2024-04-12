<?php

namespace LaximoSearch\controllers;

use GuayaquilLib\ServiceOem;
use Laximo\Search\Config;
use Laximo\Search\SearchService;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use LaximoSearch\modules\Input;
use LaximoSearch\modules\Language;
use LaximoSearch\modules\Menu;
use LaximoSearch\modules\pathway\Pathway;
use LaximoSearch\modules\ServiceProxy;
use LaximoSearch\modules\User;


/**
 * @property string errorMessage
 * @property false|mixed $action
 * @property bool|mixed|string $baseUrl
 * @property false|mixed $controller
 */
class Controller
{
    /**
     * @var  Pathway
     */
    public $pathway;

    /**
     * @var Menu
     */
    public $menu;
    /**
     * @var string[]
     */
    public $requestText = [];
    /**
     * @var string[]
     */
    public $responseText = [];
    /**
     * @var bool
     */
    public $hideRequests = true;
    /**
     * @var array
     * @default null
     */
    protected $config = null;
    /**
     * @var User
     */
    protected $user;

    /**
     * @var Input
     */
    protected $input;
    /**
     * @var Language
     */
    private $language;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->input = new Input();
        $this->menu = new Menu();
        $this->action = $this->input->getString('action');
        $this->controller = $this->input->getString('controller');
        $this->user = User::getUser();
        $this->config = $this->getConfig();
        $this->baseUrl = $this->getBaseUrl();
        $this->pathway = new Pathway();

        $this->language = new Language($this->config['property']['default_lang']);
        $this->hideRequests = !$this->user->isLoggedIn() || $this->config['property']['hideRequests'] == 'true';
    }

    protected function getSearchService(): SearchService
    {
        $config = new Config($this->config['LaximoSearchService']);
        //$config->setDebug(true);
        return new ServiceProxy($config, $this);
    }

    protected function getOemService(): ServiceOem
    {
        $service = new ServiceOem($this->config['OEMService']['login'], $this->config['OEMService']['key']);

        return $service;
    }

    protected function getOemLocale(): string
    {
        $locale = array_key_exists('locale', $this->config['OEMService']) ? $this->config['OEMService']['locale'] : 'ru_RU';

        return $locale;
    }

    protected function getConfig()
    {
        $config = json_decode(file_get_contents(ROOTPATH . '/config.json'), true);

        if ($this->user->isLoggedIn()) {
            $config['LaximoSearchService']['login'] = $this->user->getLogin();
            $config['LaximoSearchService']['password'] = $this->user->getPassword();

            $config['OEMService']['login'] = $this->user->getLogin();
            $config['OEMService']['key'] = $this->user->getPassword();
        }

        return $config;
    }

    /**
     * @return Language
     */
    public function getLanguage(): Language
    {
        return $this->language;
    }

    /**
     * @return bool|mixed
     */
    public function getBaseUrl()
    {
        return !empty($_SERVER['DOCUMENT_URI']) ? $_SERVER['DOCUMENT_URI'] : '/';
    }

    public function renderError($code, $message, $format = null)
    {
        $this->errorMessage = $message;

        if ($format === 'json') {
            $this->responseJson(['error' => true, 'code' => $code, 'message' => $this->errorMessage]);
            die();
        }

        if ($code == 401 || $code == 404 || $code == 500 || $code == 502) {
            $this->render('tmpl', $code . '.twig');
        } else {
            $this->render('tmpl', '500.twig');
        }
        die();
    }

    public function responseJson($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    public function render($tpl = '', $view = 'view.twig', $fullHtml = true, $renderJson = false)
    {
        if ($renderJson) {
            $vars = get_object_vars($this);
            unset($vars['config']);
            unset($vars['user']);

            $this->responseJson($vars);
        }

        $user = $this->user;
        $vars = (array)$this;
        $menu = $this->menu->getMenu('main', null, $this);
        $vars = array_merge($vars, [
            'user' => $user,
            'vars' => $vars,
            'menuItems' => $menu['items'],
        ]);
        $this->loadTwig($tpl, $view, $vars, $fullHtml);
    }

    public function getLocalization()
    {
        return $this->getLanguage()->getLocalization();
    }

    public function createUrl($controller = null, $action = null, $format = null, array $params = [])
    {
        if (!$controller && !$action) {
            return 'index.php';
        }

        $paths = [];

        if ($controller) {
            if (is_array($controller)) {
                $paths = array_merge($paths, $controller);
            } else {
                $paths['controller'] = lcfirst($controller) . 'Controller';
            }
        }

        if ($action) {
            if (is_array($action)) {
                $paths = array_merge($paths, $action);
            } else {
                $paths['action'] = $action;
            }
        }

        if ($format) {
            if (is_array($format)) {
                $paths = array_merge($paths, $format);
            } else {
                $paths['format'] = $format;
            }
        }

        foreach ($params as $key => $param) {
            $params[$key] = trim($param);
        }

        if ($params) {
            $paths = array_merge($paths, $params);
        }

        $baseUrl = $_SERVER['HTTP_HOST'] . '/';

        if ($paths) {
            $url = ('index.php?' . http_build_query($paths));
            if (strpos($url, $baseUrl) === false) {
                $url = 'index.php?' . http_build_query($paths);
            }
        } else {
            $url = $baseUrl;
        }

        return urldecode($url);
    }

    public function noSpaces($name) : string
    {
        $name = (string)$name;
        return preg_replace('/\s+/', ' ', $name);
    }

    public function filterOem($oem) : string
    {
        $oem = (string)$oem;
        return preg_replace('/[^a-zа-я0-9]+/i', '', $oem);
    }

    public function loadTwig($tpl = '', $view = '', $vars = [], $fullHtml = true)
    {
        if ($tpl === '') {
            $tpl = 'tmpl';
        }

        $rootDir = ROOTPATH;

        $loader = new FilesystemLoader([
            $rootDir . DIRECTORY_SEPARATOR . 'template',
            $rootDir . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $tpl . '/',
        ]);

        $twig = new Environment($loader, [
            'cache' => false,
            'auto_reload' => true,
        ]);

        $twig->addFilter(new TwigFilter('t', [$this->getLanguage(), 't']));
        $twig->addFunction(new TwigFunction('createUrl', [$this, 'createUrl']));
        $twig->addFunction(new TwigFunction('getHeadScripts', [$this, 'getHeadScripts']));
        $twig->addFunction(new TwigFunction('pagination', [$this, 'pagination']));
        $twig->addFilter(new TwigFilter('dump', 'var_dump'));
        $twig->addFilter(new TwigFilter('printr', 'print_r'));
        $twig->addFilter(new TwigFilter('noSpaces', [$this, 'noSpaces']));

        $twig->addFilter(new TwigFilter('cast_to_array', function ($stdClassObject) {
            $response = [];
            if ($stdClassObject) {
                foreach ($stdClassObject as $key => $value) {
                    $response[$key] = $value;
                }

                return $response;
            }

            return [];
        }));

        if ($fullHtml) {
            $vars['templateName'] = $view;
            $vars['current'] = getenv('REQUEST_URI');
            echo $twig->render('layouts\index.twig', $vars);
        } else {
            echo $twig->render($view, $vars);
        }

        return $twig;
    }

    public function pagination(
        $block = '',
        $additionalParam1 = [],
        $additionalParam2 = [],
        $additionalParam3 = [],
        $totalPages = false
    )
    {
        $this->totalPages = $totalPages;
        $sizes = [
            20,
            50,
            100,
            500,
            1000
        ];

        $this->block = $block;
        $this->cPage = $this->input->getString('page', 0);
        $this->controller = str_replace('Controller', '', (new \ReflectionClass($this))->getShortName());
        $this->action = $this->input->getString('action');
        $this->format = $this->input->getString('format');
        $this->pageSizes = $sizes;

        $this->param1 = $additionalParam1;
        $this->param2 = $additionalParam2;
        $this->param3 = $additionalParam3;


        $this->render('tmpl', 'pagination.twig', false);
    }

    public function filter(
        $type = 'text',
        $filterName = '',
        $filterType = '',
        $defaultValue = '',
        $filterKeyValues = [],
        $multi = '',
        $itemsBlock = '',
        $tooltip = '',
        $checked = ''
    )
    {
        $this->cPage = $this->input->getString('page', 0);
        $this->controller = str_replace('Controller', '', (new \ReflectionClass($this))->getShortName());
        $this->action = $this->input->getString('action');
        $this->format = $this->input->getString('format');
        $this->type = $type;
        $this->filterName = $filterName;
        $this->filterType = $filterType;
        $this->defaultValue = $defaultValue;
        $this->filterKeyValues = $filterKeyValues;
        $this->multi = $multi;
        $this->itemsBlock = $itemsBlock;
        $this->tooltip = $tooltip;
        $this->checked = $checked;

        $this->render('tmpl', 'filterInput.twig', false);
    }

    public function redirect($controller, $action, $params, $message = null, $messageType = 'alert')
    {
        $params['message'] = $message;
        $params['messageType'] = $messageType;

        $location = $this->createUrl($controller, $action, $params);

        header('Location:' . $location);
    }

    public function redirectToUrl($url)
    {
        header('Location:' . $url);
    }

    public function showIndex()
    {
        $this->render('tmpl', 'index.twig');
    }

    public function getHeadScripts()
    {
        $scripts = scandir(ROOTPATH . '/template/assets/js');
        $scriptsStr = '';

        foreach ($scripts as $script) {
            if ($script !== '.' && $script !== '..')
                $scriptsStr .= '<script src="LaximoSearch/template/assets/js/' . $script . '"></script>';
        }

        return $scriptsStr;
    }
}
