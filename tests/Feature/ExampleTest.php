<?php

test('returns a successful response', function () {
    loginAsTestUser();

    $this->get(route('reports.index'))->assertOk();
});

test('the dashboard redirects to login for guests', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));
});
