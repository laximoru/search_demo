<?php

namespace LaximoSearch\controllers;


use LaximoSearch\modules\User;

class userController extends Controller
{
    public function login()
    {
        $user = $this->input->formData()['user'];

        if ($user['login'] && $user['password']) {
            if (User::loginToServices(trim($user['login']), $user['password'], $this->getConfig())) {
                if (!empty($user['json']) && $user['json']) {
                    $this->responseJson($user);
                }
            } elseif (!empty($user['json']) && $user['json']) {
                $this->responseJson(['error' => true, 'message' => $this->getLanguage()->t('AUTHORIZATION_FAILED')]);
            }
        }

        $this->redirectToUrl($user['return']);
    }

    public function logout()
    {
        User::logout();

        if ($this->input->getString('format') === 'json') {
            $this->responseJson(['success' => true]);
        }

        $this->redirect('', '', []);
    }
}