<?php

namespace OptiGov\FitConnect;

use Illuminate\Support\ServiceProvider;
use OptiGov\FitConnect\Crypto\Encryptor;
use OptiGov\FitConnect\Crypto\Signer;
use OptiGov\FitConnect\FitConnect\Client;
use OptiGov\FitConnect\Zbp\Client as ZbpClient;
use OptiGov\FitConnect\Zbp\SubmissionBuilder;

class FitConnectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fitconnect.php', 'fitconnect');

        $this->app->singleton(Client::class, function ($app) {
            return new Client(
                $app['config']['fitconnect'],
                new Encryptor,
            );
        });

        $this->app->singleton(ZbpClient::class, function ($app) {
            $privateKey = file_get_contents($app['config']['fitconnect']['private_key']);
            $certificate = file_get_contents($app['config']['fitconnect']['certificate']);

            $signer = new Signer($privateKey, $certificate);

            return new ZbpClient($app->make(Client::class), new SubmissionBuilder($signer), $app['config']['fitconnect']);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/fitconnect.php' => config_path('fitconnect.php'),
        ], 'fitconnect-config');
    }
}
