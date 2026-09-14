<?php

declare(strict_types=1);

namespace App\Core;

class App
{
    private static ?App $instance = null;

    private Request $request;
    private Bootstrap $bootstrap;

    private function __construct()
    {
        $this->request = new Request();
        $this->bootstrap = new Bootstrap();
    }

    public static function instance(): App
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function bootstrap(): Bootstrap
    {
        return $this->bootstrap;
    }

    public function version(): string
    {
        return getenv('APP_VERSION') ?: '1.0.0';
    }

    public function handle(): void
    {
        try {
            $this->bootstrap->run($this->request);
        } catch (\Throwable $e) {
            ErrorHandler::handle($e);
        }
    }
}
