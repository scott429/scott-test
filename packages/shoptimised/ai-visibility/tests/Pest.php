<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Shoptimised\AiVisibility\Tests\TestCase;

// Feature tests boot a Testbench app with a fresh in-memory database.
uses(TestCase::class, RefreshDatabase::class)->in('Feature');

// Unit tests are pure and need no application container.
