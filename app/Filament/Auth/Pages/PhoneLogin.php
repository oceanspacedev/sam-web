<?php

namespace App\Filament\Auth\Pages;

use App\Filament\Auth\Concerns\InteractsWithWhatsAppLogin;
use App\Models\WhatsappOtp;
use App\Services\WhatsAppOtpService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @property-read Action $loginAction
 * @property-read Schema $form
 */
class PhoneLogin extends SimplePage
{
    use InteractsWithWhatsAppLogin;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        if (Filament::getCurrentPanel() === null) {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            Filament::bootCurrentPanel();
        }

        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->maxWidth = 'full';
        $this->form->fill();
    }

    public function send(WhatsAppOtpService $otpService): void
    {
        $data = $this->form->getState();
        $number = $this->normalizeWhatsAppNumber($data['whatsapp_number'] ?? null);

        if (! $number) {
            throw ValidationException::withMessages([
                'data.whatsapp_number' => 'Nomor WhatsApp tidak valid.',
            ]);
        }

        $rateKey = 'whatsapp-otp:filament:'.md5($number).':'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            throw ValidationException::withMessages([
                'data.whatsapp_number' => 'Terlalu banyak permintaan OTP. Coba lagi sebentar lagi.',
            ]);
        }

        RateLimiter::hit($rateKey, 300);

        $user = $this->findEligibleWebUserByWhatsApp($number);

        if (! $user) {
            throw ValidationException::withMessages([
                'data.whatsapp_number' => $this->unavailableWhatsAppMessage(),
            ]);
        }

        try {
            $otpService->issue($user, $number, WhatsappOtp::PURPOSE_LOGIN);
        } catch (RuntimeException|Throwable) {
            throw ValidationException::withMessages([
                'data.whatsapp_number' => 'OTP belum bisa dikirim ke WhatsApp. Coba lagi sebentar lagi.',
            ]);
        }

        session()->flashInput(['phone' => $number]);
        session()->flash('status', 'Kode OTP sudah dikirim ke WhatsApp.');

        $this->redirect(route('phone-login.verify'));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('whatsapp_number')
                ->label('Nomor WhatsApp')
                ->tel()
                ->required()
                ->maxLength(30)
                ->autocomplete('tel')
                ->autofocus()
                ->helperText('Contoh: 081234567890'),
        ]);
    }

    public function loginAction(): Action
    {
        return Action::make('login')
            ->link()
            ->label('Kembali ke halaman masuk')
            ->icon(match (__('filament-panels::layout.direction')) {
                'rtl' => FilamentIcon::resolve(PanelsIconAlias::PAGES_PASSWORD_RESET_REQUEST_PASSWORD_RESET_ACTIONS_LOGIN_RTL) ?? Heroicon::ArrowRight,
                default => FilamentIcon::resolve(PanelsIconAlias::PAGES_PASSWORD_RESET_REQUEST_PASSWORD_RESET_ACTIONS_LOGIN) ?? Heroicon::ArrowLeft,
            })
            ->url(filament()->getLoginUrl());
    }

    public function getTitle(): string | Htmlable
    {
        return 'Masuk dengan WhatsApp';
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Masuk dengan WhatsApp';
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Kirim OTP WhatsApp')
                ->submit('send'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getSubheading(): string | Htmlable | null
    {
        if (! filament()->hasLogin()) {
            return null;
        }

        return $this->loginAction;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('send')
                ->footer([
                    Actions::make($this->getFormActions())
                        ->alignment($this->getFormActionsAlignment())
                        ->fullWidth($this->hasFullWidthFormActions())
                        ->key('form-actions'),
                ]),
        ]);
    }

    public function getView(): string
    {
        return 'filament.auth.phone-login';
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
