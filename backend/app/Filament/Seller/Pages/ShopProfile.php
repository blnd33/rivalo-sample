<?php

namespace App\Filament\Seller\Pages;

use App\Models\Seller;
use App\Support\Audit;
use App\Support\ImageSanitizer;
use App\Support\SellerContext;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The shop's public description and logo, and private contact details for RIVALO staff.
 * The shop name, commission and agreement are set by RIVALO (ask staff to change them).
 */
class ShopProfile extends Page
{
    protected string $view = 'filament.seller.shop-profile';

    protected static ?string $slug = 'shop-profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return SellerContext::current() !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('Shop profile');
    }

    public function getTitle(): string
    {
        return __('Shop profile');
    }

    private function seller(): Seller
    {
        $seller = SellerContext::current();
        abort_unless($seller, 403);

        return $seller;
    }

    public function mount(): void
    {
        $s = $this->seller();
        $this->form->fill([
            'bio_translations' => $s->bio_translations ?? [],
            'logo_path' => $s->logo_path,
            'contact_phone' => $s->contact_phone,
            'contact_email' => $s->contact_email,
            'business_address' => $s->business_address,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $s = $this->seller();
        $bio = fn (string $locale, string $label) => Tab::make($label)->schema([
            Textarea::make("bio_translations.{$locale}")->hiddenLabel()->rows(4)->maxLength(600)
                ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']),
        ]);

        return $schema->statePath('data')->disabled(! SellerContext::canWrite())->components([
            Section::make(__('Set by RIVALO'))->columns(3)->schema([
                TextEntry::make('name')->label(__('Shop name'))->state($s->name),
                TextEntry::make('commission')->label(__('Commission'))->state($s->commissionLabel()),
                TextEntry::make('agreement')->label(__('Agreement'))->state($s->agreement_reference),
            ])->description(__('To change these, contact RIVALO.')),
            Section::make(__('Shown in the app'))->schema([
                FileUpload::make('logo_path')->label(__('Logo'))->image()->avatar()->disk('public')->directory('sellers')->maxSize(4096)
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file) {
                        $clean = ImageSanitizer::reencode($file, 800);
                        $path = 'sellers/'.Str::uuid().'.'.($clean === null ? $file->extension() : 'jpg');
                        Storage::disk('public')->put($path, $clean ?? $file->get());

                        return $path;
                    })
                    ->helperText(__('Use only images you own.')),
                Tabs::make('bio')->label(__('About your shop'))->tabs([
                    $bio('en', 'English'),
                    $bio('ar', 'العربية'),
                    $bio('kmr', 'Kurdî (Badînî)'),
                ]),
            ]),
            Section::make(__('For RIVALO staff only'))->columns(2)->description(__('Never shown to customers.'))->schema([
                TextInput::make('contact_phone')->label(__('Phone'))->tel()->maxLength(32),
                TextInput::make('contact_email')->label(__('Email'))->email()->maxLength(160),
                TextInput::make('business_address')->label(__('Business address'))->maxLength(255)->columnSpanFull(),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(SellerContext::canWrite(), 403);
        $seller = Seller::whereKey($this->seller()->id)->firstOrFail();
        $data = $this->form->getState();
        $oldLogo = $seller->logo_path;
        $seller->update([
            'bio_translations' => array_filter($data['bio_translations'] ?? [], 'filled') ?: null,
            'logo_path' => $data['logo_path'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'business_address' => $data['business_address'] ?? null,
        ]);
        if ($oldLogo && $oldLogo !== $seller->logo_path) {
            Storage::disk('public')->delete($oldLogo);
        }
        Audit::record('seller.profile_updated', $seller);
        Notification::make()->success()->title(__('Saved'))->send();
    }
}
