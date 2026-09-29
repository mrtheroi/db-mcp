<?php

use App\Mail\LoginCodeMail;

it('shows the code in its own element in the html part', function () {
    (new LoginCodeMail('482913'))->assertSeeInHtml('>482913<', false);
});

it('shows the memry logo from an absolute url in the html part', function () {
    (new LoginCodeMail('482913'))->assertSeeInHtml(
        'src="'.asset('images/memry-logo-horizontal.png').'"',
        false,
    );
});

it('tells when the code expires and what to do if it was not requested in the html part', function () {
    (new LoginCodeMail('482913'))
        ->assertSeeInHtml('It expires in 10 minutes.')
        ->assertSeeInHtml('If you did not request it, you can ignore this email.');
});

it('opens the html part with a preheader that keeps the code out of inbox previews', function () {
    $html = (new LoginCodeMail('482913'))->render();

    $preheader = 'Your memry login code expires in 10 minutes.';

    expect($html)->toContain($preheader)
        ->and(strpos($html, $preheader))->toBeLessThan(strpos($html, '482913'));
});

it('keeps the plain text part with the code and the expiry', function () {
    (new LoginCodeMail('482913'))
        ->assertSeeInText('Your memry login code is: 482913')
        ->assertSeeInText('It expires in 10 minutes. If you did not request it, you can ignore this email.')
        ->assertDontSeeInText('<');
});

it('keeps the subject', function () {
    (new LoginCodeMail('482913'))->assertHasSubject('Your memry login code');
});
