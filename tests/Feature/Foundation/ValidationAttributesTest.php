<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

it('uses Arabic field names in Arabic validation messages', function () {
    App::setLocale('ar');

    $errors = Validator::make(
        ['eventTypes' => ['x' => null]],
        ['amount' => ['required'], 'beneficiaryName' => ['required'], 'eventTypes.*' => ['required']],
    )->errors();

    expect($errors->first('amount'))->toContain('المبلغ')->not->toContain('amount')
        ->and($errors->first('beneficiaryName'))->toContain('اسم المستفيد')
        ->and($errors->first('eventTypes.x'))->toContain('نوع الحدث');
});

it('uses readable English field names in English validation messages', function () {
    App::setLocale('en');

    $errors = Validator::make([], ['newPhone' => ['required']])->errors();

    expect($errors->first('newPhone'))->toContain('new phone number');
});
