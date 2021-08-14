<?php


namespace Makingcg\Subscription;


use Illuminate\Support\Manager;
use Makingcg\Subscription\Engines\Engine;
use Makingcg\Subscription\Engines\FlutterWaveEngine;
use Makingcg\Subscription\Engines\StripeEngine;

class EngineManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('vuefilemanager-subscription.driver', 'stripe');
    }

    public function createStripeDriver(): Engine
    {
        return new StripeEngine();
    }

    public function createFlutterWaveDriver(): Engine
    {
        return new FlutterWaveEngine();
    }
}
