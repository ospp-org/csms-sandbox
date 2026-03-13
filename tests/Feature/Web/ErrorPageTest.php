<?php

declare(strict_types=1);

test('404 page renders custom view', function (): void {
    $this->get('/nonexistent-page-xyz')
        ->assertStatus(404)
        ->assertSee('Page not found');
});
