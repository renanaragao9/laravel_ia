<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_opens_the_public_blog(): void
    {
        $this->get('/')->assertOk()->assertSee('maiores de 18 anos');
    }
}
