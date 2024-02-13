<?php

namespace Backpack\Store\Tests;

// ini_set('memory_limit', '-1');

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\artisan;
use function Orchestra\Testbench\workbench_path;

use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use Illuminate\Contracts\Config\Repository;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Backpack\Store\ServiceProvider as StoreServiceProvider;

use \Backpack\Store\app\FakeUser;
use \Backpack\Store\app\FakeAdmin;
use Backpack\Store\database\seeders\CategorySeeder;
use Backpack\Store\database\seeders\ProductSeeder;
use Backpack\Store\database\seeders\AttributeSeeder;
use Backpack\Store\database\seeders\PromocodeSeeder;

// #[WithMigration]
class TestCase extends Orchestra
{

  use RefreshDatabase;

  /**
   * Automatically enables package discoveries.
   *
   * @var bool
   */
  protected $enablesPackageDiscoveries = true;

  protected $admin;
  protected $user;

  protected function setUp(): void
  {
      parent::setUp();

      // Create fake admin
      $this->admin = FakeAdmin::factory()->create();

      // Create fake user
      $this->user = FakeUser::factory()->create();

      // Enter via backpack login system
      backpack_auth()->login($this->admin);
      
      // Run category seeder
      $this->seed(CategorySeeder::class);

      // Run product seeder
      $this->seed(ProductSeeder::class);

      // Run attribute seeder
      // $this->seed(AttributeSeeder::class);

      // Run promocode seeder
      $this->seed(PromocodeSeeder::class);

      // xz
      Factory::guessFactoryNamesUsing(
        fn (string $modelName) => 'Backpack\\Store\\Database\\Factories\\'.class_basename($modelName).'Factory'
      );
  }

  protected function getPackageProviders($app)
  {
      return [
        StoreServiceProvider::class,
      ];
  }

    /**
   * Define database migrations.
   *
   * @return void
   */
  protected function defineDatabaseMigrations()
  {
    $this->loadMigrationsFrom(__DIR__.'/database/migrations');
  }

   /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {

      // $kernel = app('Illuminate\Contracts\Http\Kernel');

      // $kernel->pushMiddleware(\Illuminate\Session\Middleware\StartSession::class);
      // $kernel->pushMiddleware(app\Http\Middleware\CheckIfAdmin::class);

      $app['config']->set('auth.guards', [
        'web' => [
          'driver' => 'session',
          'provider' => 'users',
        ],
        'profile' => [
          'driver' => 'session',
          'provider' => 'fakeUser',
        ]
      ]);

      $app['config']->set('auth.providers', [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'fakeUser' => [
            'driver' => 'eloquent',
            'model' => FakeUser::class,
        ],
      ]);

      // Setup default database to use sqlite :memory:
      tap($app['config'], function (Repository $config) {
          $config->set('database.default', 'testbench');
          $config->set('database.connections.testbench', [
              'driver'   => 'sqlite',
              'database' => ':memory:',
              'prefix'   => '',
          ]);
          
          // Setup queue database connections.
          // $config([
          //     'queue.batching.database' => 'testbench',
          //     'queue.failed.database' => 'testbench',
          // ]);
      });
    }
}
