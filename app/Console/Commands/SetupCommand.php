<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * One-shot bootstrap command for the Book Library API.
 *
 * Idempotent by default — `php artisan app:setup` runs pending
 * migrations, seeds nothing, and regenerates the OpenAPI document.
 * Flags compose orthogonally:
 *
 *   --fresh       drops every table before migrating (destructive!)
 *   --seed        runs db:seed after migrating
 *   --demo        shorthand for --fresh --seed (clean demo dataset)
 *   --no-swagger  skip the Swagger regeneration step
 *
 * The intent is a single command an interviewer or new contributor
 * can run after cloning the repo to reach a known good state.
 */
final class SetupCommand extends Command
{
    protected $signature = 'app:setup
        {--fresh : Drop every table before migrating (destructive)}
        {--seed : Run database seeders after migrating}
        {--demo : Shortcut for --fresh --seed — full reset with sample data}
        {--no-swagger : Skip the Swagger / OpenAPI regeneration step}';

    protected $description = 'Bootstrap the application: run migrations, optionally seed, and regenerate API docs.';

    public function handle(): int
    {
        // --demo expands into --fresh + --seed at the input level so
        // the rest of the method can branch on the canonical flags.
        if ($this->option('demo')) {
            $this->input->setOption('fresh', true);
            $this->input->setOption('seed', true);
        }

        $migrationCommand = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
        $this->components->info("Running {$migrationCommand}…");
        $this->call($migrationCommand, ['--force' => true]);

        if ($this->option('seed')) {
            $this->components->info('Seeding database…');
            $this->call('db:seed', ['--force' => true]);
        }

        if (! $this->option('no-swagger')) {
            $this->components->info('Regenerating OpenAPI documentation…');
            $this->call('l5-swagger:generate');
        }

        $this->newLine();
        $this->table(
            ['Step', 'Action'],
            [
                ['Migrations', $this->option('fresh') ? 'fresh (dropped + recreated)' : 'incremental'],
                ['Seeders', $this->option('seed') ? 'executed' : 'skipped'],
                ['Swagger', $this->option('no-swagger') ? 'skipped' : 'regenerated'],
            ],
        );

        $this->components->info('Application setup complete.');

        return self::SUCCESS;
    }
}
