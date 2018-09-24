<?php

namespace Rareloop\Lumberjack\Providers;

use Rareloop\Lumberjack\Helpers;
use Rareloop\Lumberjack\Application;
use Rareloop\Lumberjack\Facades\Config;
use Rareloop\Lumberjack\Session\SessionManager;

class SessionServiceProvider extends ServiceProvider
{
    protected $session;

    public function register()
    {
        $this->session = new SessionManager($this->app);
        $this->app->bind('session', $this->session);
    }

    public function boot()
    {
        add_action('init', function () {
            $this->session->start();
        });

        // Due to the way we handle WordPressControllers sometimes the `send_headers` action is
        // called twice. Knowing this, we'll put a lock around adding the cookie
        $cookieSet = false;

        add_action('send_headers', function () use (&$cookieSet) {
            if (!$cookieSet) {
                $cookieOptions = [
                    'lifetime' => Config::get('session.lifetime', 120),
                    'path' => Config::get('session.path', '/'),
                    'domain' => Config::get('session.domain', null),
                    'secure' => Config::get('session.secure', false),
                    'httpOnly' => Config::get('session.http_only', true),
                ];

                setcookie(
                    $this->session->getName(),
                    $this->session->getId(),
                    time() + ($cookieOptions['lifetime'] * 60),
                    $cookieOptions['path'],
                    $cookieOptions['domain'],
                    $cookieOptions['secure'],
                    $cookieOptions['httpOnly']
                );

                $cookieSet = true;
            }
        });

        add_action('shutdown', function () {
            $request = Helpers::request();

            if ($request->method() === 'get' && !$request->ajax()) {
                $this->session->setPreviousUrl($request->fullUrl());
            }

            $this->session->save();
        });
    }
}