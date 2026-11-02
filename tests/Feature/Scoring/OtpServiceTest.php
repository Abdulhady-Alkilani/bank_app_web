<?php

use App\Enums\OtpStatus;
use App\Enums\TransactionStatus;
use App\Mail\OtpCodeMail;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Otp\OtpService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->customer = Customer::factory()->create(['email' => 'c@bank.test']);
    $this->tx = Transaction::factory()->held()->create();
    $this->otp = app(OtpService::class);

    $this->issue = function () {
        $challenge = $this->otp->issue($this->customer, $this->tx);
        $code = null;
        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return [$challenge, $code];
    };
});

it('issues a hashed 6 digit code, e-mails it and releases the transaction on success', function () {
    [$challenge, $code] = ($this->issue)();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($challenge->code_hash)->not->toBe($code)
        ->and($challenge->status)->toBe(OtpStatus::Pending)
        ->and($challenge->expires_at->isFuture())->toBeTrue()
        ->and($this->otp->verify($challenge, $code))->toBeTrue()
        ->and($challenge->fresh()->status)->toBe(OtpStatus::Verified)
        ->and($this->tx->fresh()->status)->toBe(TransactionStatus::Completed);
});

it('counts wrong attempts and rejects the transaction after the maximum', function () {
    [$challenge, $code] = ($this->issue)();
    $wrong = $code === '000000' ? '111111' : '000000';

    expect($this->otp->verify($challenge, $wrong))->toBeFalse()
        ->and($challenge->fresh()->attempts)->toBe(1)
        ->and($this->otp->remainingAttempts($challenge->fresh()))->toBe(2)
        ->and($this->otp->verify($challenge, $wrong))->toBeFalse()
        ->and($this->otp->verify($challenge, $wrong))->toBeFalse()
        ->and($challenge->fresh()->status)->toBe(OtpStatus::Failed)
        ->and($this->tx->fresh()->status)->toBe(TransactionStatus::Rejected)
        ->and(AuditLog::where('action', 'otp.failed')->exists())->toBeTrue();

    // A failed challenge never verifies, even with the right code.
    expect($this->otp->verify($challenge, $code))->toBeFalse();
});

it('honours the configured maximum attempts', function () {
    app(SettingsService::class)->set('otp.max_attempts', 1);
    [$challenge, $code] = ($this->issue)();

    $this->otp->verify($challenge, $code === '000000' ? '111111' : '000000');

    expect($challenge->fresh()->status)->toBe(OtpStatus::Failed);
});

it('expires challenges', function () {
    [$challenge, $code] = ($this->issue)();

    $this->travel(6)->minutes();

    expect($this->otp->verify($challenge, $code))->toBeFalse()
        ->and($challenge->fresh()->status)->toBe(OtpStatus::Expired)
        ->and($this->tx->fresh()->status)->toBe(TransactionStatus::Held);
});

it('stores the plain code in cache only in the local environment', function () {
    $challenge = $this->otp->issue($this->customer, $this->tx);
    expect(Cache::has('otp:dev:'.$challenge->id))->toBeFalse();

    $this->app['env'] = 'local';
    $local = $this->otp->issue($this->customer, $this->tx);

    expect(Cache::get('otp:dev:'.$local->id))->toMatch('/^\d{6}$/');
});
