<?php

it('renders the branded 404 page for unknown urls', function () {
    $this->get('/this-page-does-not-exist-xyz')
        ->assertNotFound()
        ->assertSee('404', false)
        ->assertSee('This page could not be found', false)
        ->assertSee('Back to home', false);
});
