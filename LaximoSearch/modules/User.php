<?php

namespace LaximoSearch\modules;

use Exception;
use GuayaquilLib\ServiceOem;
use Laximo\Search\Config;
use Laximo\Search\responseObjects\UsUser;
use Laximo\Search\SearchService;

class User
{
    /** @var User */
    static $user = null;

    /** @var string */
    protected $login = '';

    /** @var string */
    protected $password = '';

    /** @var string[] */
    protected $services = [];

    public function __construct($storedData = '')
    {
        if ($storedData) {
            $data = json_decode($storedData, true);
            $this->login = $data['login'];
            $this->password = $data['password'];
            $this->services = $data['services'];
        }
    }

    /**
     * @return User
     */
    public static function getUser(): User
    {
        return self::$user;
    }

    /**
     * @param User $user
     */
    public static function setUser(User $user)
    {
        self::$user = $user;
    }

    public static function loginToServices(string $user, string $pass, $config): ?User
    {
        $services = [];

        try {
            $oem = new ServiceOem($user, $pass);
            $oem->listCatalogs();
            $services['oem'] = 'oem';
        } catch (Exception $e) {
        }

        $configData = [
            'login' => $user,
            'password' => $pass,
        ];
        if ($config['LaximoSearchService']['serviceUrl']) {
            $configData['serviceUrl'] = $config['LaximoSearchService']['serviceUrl'];
        }

        $us = new SearchService(new Config($configData));
        try {
            $info = $us->user();
            $services['us'] = 'us';
            if ($info->isPermitted(UsUser::PERMISSION_MANAGE_OFFERS)) {
                $services['us_upload'] = 'us_upload';
            }
        } catch (Exception $e) {
        }

        if (array_key_exists('us', $services)) {
            $user = new User(json_encode([
                'login' => $user,
                'password' => $pass,
                'services' => $services,
            ]));
            User::setUser($user);
            $_SESSION['userData'] = $user->toString();
            return $user;
        } else {
            User::logout();
        }
        return null;
    }

    public function toString()
    {
        return json_encode([
            'login' => $this->login,
            'password' => $this->password,
            'services' => $this->services,
        ]);
    }

    public static function logout()
    {
        unset($_SESSION['userData']);
        User::setUser(new User());
    }

    /**
     * @return string
     */
    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return array_key_exists('us', $this->services);
    }

    /**
     * @return bool
     */
    public function isServiceAvailable(string $service): bool
    {
        return array_key_exists($service, $this->services);
    }
}

User::setUser(new User(@$_SESSION['userData']));
