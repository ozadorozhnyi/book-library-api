<?php

namespace Tests\Unit\Console;

use App\Models\Book;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unit tests for {@see \App\Console\Commands\SetupCommand}.
 *
 * Strategy: behaviour-based — we run the command end-to-end against
 * SQLite in-memory and assert the *observable outcome* (database
 * state, Swagger artefact freshness). This is more robust than
 * mocking the dispatched sub-commands and matches how the command
 * will be invoked in real bootstrap.
 */
final class SetupCommandTest extends TestCase
{
    /*
     * DatabaseMigrations (re-runs migrate:fresh per test) instead of
     * RefreshDatabase (transaction-wrapped) — this command itself runs
     * migrate:fresh under --fresh / --demo, and SQLite cannot VACUUM
     * from inside an open transaction.
     */
    use DatabaseMigrations;

    /** Command is registered and auto-discoverable under the `app` namespace. */
    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('app:setup', Artisan::all());
    }

    /**
     * Default invocation runs migrations only — no seed data lands in
     * the database, but the schema is in place.
     */
    public function test_default_run_migrates_without_seeding(): void
    {
        $this->artisan('app:setup', ['--no-swagger' => true])
            ->assertExitCode(0);

        $this->assertSame(0, Book::count());
        // The books table must exist (migrations ran).
        $this->assertTrue(Schema::hasTable('books'));
    }

    /** --seed populates the database with the BookSeeder's 10 books. */
    public function test_seed_flag_populates_database(): void
    {
        $this->artisan('app:setup', ['--seed' => true, '--no-swagger' => true])
            ->assertExitCode(0);

        $this->assertSame(10, Book::count());
    }

    /**
     * --demo composes --fresh and --seed into a single switch — handy
     * for the interview demo bootstrap.
     */
    public function test_demo_flag_drops_recreates_and_seeds(): void
    {
        // Pre-populate something that should disappear on --fresh.
        Book::factory()->count(3)->create();
        $this->assertSame(3, Book::count());

        $this->artisan('app:setup', ['--demo' => true, '--no-swagger' => true])
            ->assertExitCode(0);

        // After --fresh + --seed the table has exactly the seeded count.
        $this->assertSame(10, Book::count());
    }

    /**
     * --fresh on its own drops every table and re-runs migrations
     * without seeding.
     */
    public function test_fresh_flag_recreates_schema_without_seeding(): void
    {
        Book::factory()->count(3)->create();

        $this->artisan('app:setup', ['--fresh' => true, '--no-swagger' => true])
            ->assertExitCode(0);

        $this->assertSame(0, Book::count());
        $this->assertTrue(Schema::hasTable('books'));
    }

    /**
     * --no-swagger suppresses the OpenAPI regeneration step. We assert
     * the artefact's modification time is unchanged after the command.
     */
    public function test_no_swagger_flag_does_not_regenerate_artefact(): void
    {
        $docPath = storage_path('api-docs/api-docs.json');
        if (! file_exists($docPath)) {
            // Generate once so we have a stable baseline.
            $this->artisan('l5-swagger:generate');
            clearstatcache();
        }
        $beforeMtime = filemtime($docPath);

        // Sleep 1s so any regeneration would change mtime to a later second.
        sleep(1);

        $this->artisan('app:setup', ['--no-swagger' => true])
            ->assertExitCode(0);

        clearstatcache();
        $afterMtime = filemtime($docPath);
        $this->assertSame($beforeMtime, $afterMtime);
    }

    /**
     * Running app:setup without --no-swagger DOES regenerate the
     * OpenAPI artefact — its mtime moves forward.
     */
    public function test_swagger_is_regenerated_by_default(): void
    {
        $docPath = storage_path('api-docs/api-docs.json');
        if (! file_exists($docPath)) {
            $this->artisan('l5-swagger:generate');
            clearstatcache();
        }
        $beforeMtime = filemtime($docPath);

        sleep(1);

        $this->artisan('app:setup')
            ->assertExitCode(0);

        clearstatcache();
        $afterMtime = filemtime($docPath);
        $this->assertGreaterThan($beforeMtime, $afterMtime);
    }

    /** Every successful run returns Laravel's SUCCESS exit code (0). */
    public function test_returns_success_exit_code(): void
    {
        $this->artisan('app:setup', ['--no-swagger' => true])
            ->assertExitCode(0);
    }
}
