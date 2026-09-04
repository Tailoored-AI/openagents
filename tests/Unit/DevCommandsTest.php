<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\DevCommands;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    DevCommands::except();
});

afterEach(function (): void {
    DevCommands::except();
});

it('excludes the development server from the dev command under Sail', function (): void {
    config()->set('app.sail', true);

    (new AppServiceProvider(app()))->boot();

    expect(collect(DevCommands::commands())->pluck('name'))
        ->not->toContain('server')
        ->toContain('vite');
});

it('keeps the development server in the dev command outside Sail', function (): void {
    config()->set('app.sail', false);

    (new AppServiceProvider(app()))->boot();

    expect(collect(DevCommands::commands())->pluck('name'))->toContain('server');
});
