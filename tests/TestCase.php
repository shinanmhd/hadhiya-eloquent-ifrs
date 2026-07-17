<?php

namespace IFRS\Tests;

use Faker\Factory as Faker;

use Orchestra\Testbench\TestCase as Orchestra;

use Illuminate\Support\Facades\Config;

use IFRS\User;

use IFRS\IFRSServiceProvider;

use IFRS\Models\Currency;
use IFRS\Models\ReportingPeriod;
use IFRS\Tests\Support\TestEntityResolver;

abstract class TestCase extends Orchestra
{
    public function setUp(): void
    {

        parent::setUp();

        Config::set('ifrs.user_model', User::class);
        Config::set('ifrs.entity_context.resolver', TestEntityResolver::class);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->faker = Faker::create();

        $user = factory(User::class)->create();
        $this->be($user);

        TestEntityResolver::setEntity($user->entity);

        $currency = factory(Currency::class)->create();

        $entity = $user->entity;
        $entity->currency_id = $currency->id;
        $entity->save();

        $this->reportingCurrencyId = $currency->id;

        $this->period = factory(ReportingPeriod::class)->create([
            "calendar_year" => date("Y"),
            "entity_id" => $user->entity->id,
        ]);
    }

    protected function tearDown(): void
    {
        TestEntityResolver::setEntity(null);

        parent::tearDown();
    }

    /**
     * Add the package provider
     *
     * @param  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [IFRSServiceProvider::class];
    }
}
