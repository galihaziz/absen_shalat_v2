<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_see_the_student_login_at_root(): void
    {
        $this->get('/')->assertOk()->assertSee('Nomor induk')->assertSee('PIN');
    }
}
