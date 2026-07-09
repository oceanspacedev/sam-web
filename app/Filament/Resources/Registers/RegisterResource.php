<?php

namespace App\Filament\Resources\Registers;

use App\Exceptions\Api\BadRequestException;
use App\Filament\Resources\Registers\Pages\CreateRegister;
use App\Filament\Resources\Registers\Pages\EditRegister;
use App\Filament\Resources\Registers\Pages\ListRegisters;
use App\Filament\Resources\Registers\Pages\ViewRegister;
use App\Models\Outlet;
use App\Models\Register;
use App\Models\User;
use App\Services\FilenameGeneratorService;
use App\Services\RegisterApprovalService;
use App\Support\FilamentOrganizationalScope;
use App\Support\FilamentTableEagerLoad;
use App\Support\OrganizationalHierarchyOptions;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class RegisterResource extends Resource
{
    protected static ?string $model = Register::class;

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-file-check-02';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        Group::make([
                            Section::make('Data Outlet')
                                ->schema([
                                    TextInput::make('nama_outlet')
                                        ->required()
                                        ->maxLength(255)
                                        ->live()
                                        ->label('Nama Outlet'),
                                    TextInput::make('distric')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Distrik'),
                                    Textarea::make('alamat_outlet')
                                        ->required()
                                        ->columnSpanFull()
                                        ->label('Alamat Outlet'),
                                    TextInput::make('nama_pemilik_outlet')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Nama Pemilik Outlet'),
                                    TextInput::make('nomer_tlp_outlet')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Nomor Telepon Outlet'),
                                    TextInput::make('nomer_wakil_outlet')
                                        ->maxLength(255)
                                        ->label('Nomor Wakil Outlet'),
                                    TextInput::make('ktp_outlet')
                                        ->maxLength(255)
                                        ->label('KTP Pemilik Outlet')
                                        ->visible(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                        ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type'))),
                                    TextInput::make('latlong')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Koordinat Lat/Long'),
                                ])
                                ->columns(2),
                            Section::make('Dokumentasi')
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'md' => 2,
                                    ])->schema([
                                        FileUpload::make('poto_shop_sign')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Tanda Toko')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-photo', $userId);
                                                // Output: rp241204123-a1b2c3-550e8400-e29b-41d4-a716-446655440000.jpg
                                            }),
                                        FileUpload::make('poto_depan')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Depan')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-photo', $userId);
                                            }),
                                        FileUpload::make('poto_kiri')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Kiri')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-photo', $userId);
                                            }),
                                        FileUpload::make('poto_kanan')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Kanan')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-photo', $userId);
                                            }),
                                        FileUpload::make('poto_ktp')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto KTP Pemilik')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->visible(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-ktp', $userId);
                                            }),
                                        FileUpload::make('video')
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Video Toko')
                                            ->afterStateHydrated(function (Set $set, $state): void {
                                                if ($state === '-') {
                                                    $set('video', null);
                                                }
                                            })
                                            ->dehydrateStateUsing(function ($state) {
                                                if (blank($state) || $state === '-') {
                                                    return '-';
                                                }

                                                if (is_array($state)) {
                                                    $first = reset($state);

                                                    return filled($first) ? (string) $first : '-';
                                                }

                                                return $state;
                                            })
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-video', $userId);
                                            }),
                                    ]),
                                ]),
                            Section::make('Promotor dan Frontliner')
                                ->schema([
                                    TextInput::make('oppo')
                                        ->required()
                                        ->numeric()
                                        ->label('Oppo'),
                                    TextInput::make('vivo')
                                        ->required()
                                        ->numeric()
                                        ->label('Vivo'),
                                    TextInput::make('realme')
                                        ->required()
                                        ->numeric()
                                        ->label('Realme'),
                                    TextInput::make('samsung')
                                        ->required()
                                        ->numeric()
                                        ->label('Samsung'),
                                    TextInput::make('xiaomi')
                                        ->required()
                                        ->numeric()
                                        ->label('Xiaomi'),
                                    TextInput::make('fl')
                                        ->required()
                                        ->numeric()
                                        ->label('FL'),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Group::make([
                            Section::make('Informasi Tambahan')
                                ->schema([
                                    ToggleButtons::make('type')
                                        ->label('Tipe')
                                        ->options([
                                            'LEAD' => 'LEAD',
                                            'NOO' => 'NOO',
                                        ])
                                        ->required()
                                        ->live()
                                        ->inline()
                                        ->colors([
                                            'LEAD' => 'warning',
                                            'NOO' => 'success',
                                        ])
                                        ->default('NOO'),
                                    Select::make('created_by_id')
                                        ->label('Dibuat Oleh')
                                        ->searchable()
                                        ->required()
                                        ->relationship('createdBy', 'nama_lengkap')
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, ?int $state): void {
                                            if (! $state) {
                                                $set('tm_id', null);

                                                return;
                                            }

                                            $creator = User::find($state);

                                            if (! $creator) {
                                                $set('tm_id', null);

                                                return;
                                            }

                                            $set('tm_id', optional($creator->tm)->id ?? $creator->id);
                                        }),
                                ])
                                ->columns(1),
                            Section::make('Struktur Organisasi')
                                ->schema([
                                    Grid::make(['default' => 1])
                                        ->schema([
                                            Select::make('badanusaha_id')
                                                ->label('Badan Usaha')
                                                ->searchable()
                                                ->required()
                                                ->live()
                                                ->placeholder('Pilih badan usaha')
                                                ->options(fn (): array => OrganizationalHierarchyOptions::badanUsaha(activeOnly: false))
                                                ->getSearchResultsUsing(fn (string $search): array => OrganizationalHierarchyOptions::searchBadanUsaha($search, activeOnly: false))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::badanUsahaLabel($value, activeOnly: false))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('divisi_id', null);
                                                    $set('region_id', null);
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('divisi_id')
                                                ->label('Divisi')
                                                ->searchable()
                                                ->required()
                                                ->live()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::division($get('badanusaha_id'), activeOnly: false))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchDivision($search, $get('badanusaha_id'), activeOnly: false))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('region_id', null);
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('region_id')
                                                ->label('Region')
                                                ->searchable()
                                                ->required()
                                                ->live()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('divisi_id'), activeOnly: false))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('divisi_id'), activeOnly: false))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::regionLabel($value))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('cluster_id')
                                                ->label('Cluster')
                                                ->searchable()
                                                ->required()
                                                ->live()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::cluster($get('region_id'), activeOnly: false))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchCluster($search, $get('region_id'), activeOnly: false))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::clusterLabel($value)),
                                        ]),
                                ]),
                            Section::make('TM')
                                ->schema([
                                    Select::make('tm_id')
                                        ->label('Nama TM')
                                        ->required()
                                        ->searchable()
                                        ->options(fn (Get $get): array => self::getTmOptions('', $get('created_by_id'), $get('tm_id')))
                                        ->getSearchResultsUsing(fn (string $search, Get $get): array => self::getTmOptions($search, $get('created_by_id'), $get('tm_id')))
                                        ->getOptionLabelUsing(fn ($value): ?string => self::tmLabel($value))
                                        ->live(),
                                ]),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 4]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function isLead(?string $type): bool
    {
        return strtoupper((string) $type) === 'LEAD';
    }

    public static function hasDuplicateOutletCode(?Register $record, ?string $kodeOutlet): bool
    {
        return self::duplicateOutletForCode($record, $kodeOutlet) !== null;
    }

    public static function duplicateOutletForCode(?Register $record, ?string $kodeOutlet): ?Outlet
    {
        if (! $record || ! filled($kodeOutlet)) {
            return null;
        }

        return Outlet::query()
            ->where('divisi_id', $record->divisi_id)
            ->where('kode_outlet', trim((string) $kodeOutlet))
            ->where(function (Builder $query) use ($record): void {
                $query->whereNull('register_id')
                    ->orWhere('register_id', '!=', $record->id);
            })
            ->first();
    }

    /**
     * @return array{
     *     checked_kode_outlet:string,
     *     has_duplicate:bool,
     *     duplicate_outlet_summary:string|null,
     *     duplicate_suggestion_title:string|null,
     *     duplicate_suggestion_body:string|null,
     *     next_branch_code:string|null
     * }
     */
    public static function duplicateOutletCodeCheckResult(?Register $record, ?string $kodeOutlet): array
    {
        $checkedKodeOutlet = trim((string) $kodeOutlet);
        $duplicateOutlet = self::duplicateOutletForCode($record, $checkedKodeOutlet);

        if (! $record || ! $duplicateOutlet) {
            return [
                'checked_kode_outlet' => $checkedKodeOutlet,
                'has_duplicate' => false,
                'duplicate_outlet_summary' => null,
                'duplicate_suggestion_title' => null,
                'duplicate_suggestion_body' => null,
                'next_branch_code' => null,
            ];
        }

        $nextBranchCode = app(RegisterApprovalService::class)
            ->previewNextBranchCode($checkedKodeOutlet, (int) $record->divisi_id);
        $suggestion = self::duplicateOutletSuggestion($record, $duplicateOutlet, $nextBranchCode);

        return [
            'checked_kode_outlet' => $checkedKodeOutlet,
            'has_duplicate' => true,
            'duplicate_outlet_summary' => self::duplicateOutletSummary($duplicateOutlet),
            'duplicate_suggestion_title' => $suggestion['title'],
            'duplicate_suggestion_body' => $suggestion['body'],
            'next_branch_code' => $nextBranchCode,
        ];
    }

    public static function checkOutletCodeForApproval(Get $get, Set $set, ?Register $record): void
    {
        $kodeOutlet = trim((string) $get('kode_outlet'));

        if (blank($kodeOutlet)) {
            self::resetOutletCodeCheck($set);

            throw ValidationException::withMessages([
                'kode_outlet' => 'Kode outlet wajib diisi sebelum dicek.',
            ]);
        }

        $result = self::duplicateOutletCodeCheckResult($record, $kodeOutlet);

        $set('outlet_code_checked', true);
        $set('checked_kode_outlet', $result['checked_kode_outlet']);
        $set('has_duplicate_outlet_code', $result['has_duplicate']);
        $set('duplicate_outlet_summary', $result['duplicate_outlet_summary']);
        $set('duplicate_suggestion_title', $result['duplicate_suggestion_title']);
        $set('duplicate_suggestion_body', $result['duplicate_suggestion_body']);
        $set('next_branch_code', $result['next_branch_code']);

        if (! $result['has_duplicate']) {
            $set('duplicate_resolution', RegisterApprovalService::DUPLICATE_BRANCH);

            Notification::make()
                ->title('Kode outlet tersedia')
                ->success()
                ->send();

            return;
        }

        $set('duplicate_resolution', null);

        Notification::make()
            ->title('Kode outlet sudah dipakai')
            ->body($result['duplicate_suggestion_title'] ?? 'Pilih tindakan sebelum approval.')
            ->warning()
            ->send();
    }

    public static function resetOutletCodeCheck(Set $set): void
    {
        $set('outlet_code_checked', false);
        $set('checked_kode_outlet', null);
        $set('has_duplicate_outlet_code', false);
        $set('duplicate_resolution', null);
        $set('duplicate_outlet_summary', null);
        $set('duplicate_suggestion_title', null);
        $set('duplicate_suggestion_body', null);
        $set('next_branch_code', null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function validateOutletCodeCheckBeforeApproval(?Register $record, array $data): void
    {
        $kodeOutlet = trim((string) ($data['kode_outlet'] ?? ''));
        $checkedKodeOutlet = trim((string) ($data['checked_kode_outlet'] ?? ''));

        if (! array_key_exists('limit', $data) || blank($data['limit'])) {
            throw ValidationException::withMessages([
                'kode_outlet' => 'Cek kode outlet dulu sebelum approval.',
            ]);
        }

        if (blank($checkedKodeOutlet) || $checkedKodeOutlet !== $kodeOutlet) {
            throw ValidationException::withMessages([
                'kode_outlet' => 'Cek kode outlet lagi setelah mengubah kode.',
            ]);
        }

        if (self::hasDuplicateOutletCode($record, $kodeOutlet) && blank($data['duplicate_resolution'] ?? null)) {
            throw ValidationException::withMessages([
                'duplicate_resolution' => 'Pilih Buat Cabang atau Override Outlet Lama sebelum approval.',
            ]);
        }
    }

    public static function duplicateOutletSuggestionPreview(Get $get): HtmlString
    {
        $title = trim((string) $get('duplicate_suggestion_title'));
        $body = trim((string) $get('duplicate_suggestion_body'));
        $summary = trim((string) $get('duplicate_outlet_summary'));
        $nextBranchCode = trim((string) $get('next_branch_code'));

        if (blank($title) && blank($body)) {
            return new HtmlString('');
        }

        $html = '<div class="rounded-xl border border-warning-200 bg-warning-50 p-4 shadow-sm dark:border-warning-800 dark:bg-warning-900/30">';
        $html .= '<div class="flex gap-3">';

        $html .= '<div class="flex-shrink-0 text-warning-500 mt-0.5">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
        </div>';

        $html .= '<div class="space-y-2 text-sm text-warning-800 dark:text-warning-200 w-full">';

        $html .= '<div>';
        $html .= '<div class="font-semibold text-base">'.e($title).'</div>';
        if (filled($body)) {
            $html .= '<div class="mt-1 opacity-90 leading-relaxed">'.e($body).'</div>';
        }
        $html .= '</div>';

        if (filled($summary) || filled($nextBranchCode)) {
            $html .= '<div class="mt-3 pt-3 border-t border-warning-200/60 dark:border-warning-700/60 space-y-1.5">';
            if (filled($summary)) {
                $html .= '<div><span class="font-medium">Outlet Lama:</span> <span class="opacity-80">'.e($summary).'</span></div>';
            }
            if (filled($nextBranchCode)) {
                $html .= '<div><span class="font-medium">Kode Berikutnya:</span> <span class="font-mono font-medium bg-warning-200/50 px-1.5 py-0.5 rounded dark:bg-warning-900/50">'.e($nextBranchCode).'</span></div>';
            }
            $html .= '</div>';
        }

        $html .= '</div></div></div>';

        return new HtmlString($html);
    }

    /**
     * @return array{title:string,body:string}
     */
    protected static function duplicateOutletSuggestion(Register $register, Outlet $outlet, string $nextBranchCode): array
    {
        $nameSimilarity = self::textSimilarity($register->nama_outlet, $outlet->nama_outlet);
        $addressSimilarity = self::textSimilarity($register->alamat_outlet, $outlet->alamat_outlet);
        $sameOwner = self::sameComparableText($register->nama_pemilik_outlet, $outlet->nama_pemilik_outlet);
        $samePhone = self::sameComparableText($register->nomer_tlp_outlet, $outlet->nomer_tlp_outlet);
        $differentArea = self::differentFilledValue($register->region_id, $outlet->region_id)
            || self::differentFilledValue($register->cluster_id, $outlet->cluster_id);

        if (($samePhone || $sameOwner || $addressSimilarity >= 82.0) && $nameSimilarity >= 55.0) {
            return [
                'title' => 'Saran: kemungkinan update data outlet lama',
                'body' => 'Data baru sangat dekat dengan outlet lama. Pakai Override kalau ini memang koreksi atau pembaruan data outlet lama; histori perubahan akan tersimpan.',
            ];
        }

        if ($nameSimilarity >= 70.0 && ($addressSimilarity < 65.0 || $differentArea)) {
            return [
                'title' => 'Saran: kemungkinan cabang',
                'body' => "Nama outlet mirip, tetapi alamat atau wilayah berbeda. Pakai Buat Cabang untuk membuat {$nextBranchCode} tanpa mengubah kode utama.",
            ];
        }

        if ($nameSimilarity < 45.0 && $addressSimilarity < 45.0) {
            return [
                'title' => 'Saran: kemungkinan outlet baru dengan kode bentrok',
                'body' => "Nama dan alamat berbeda jauh dari outlet lama. Kalau tetap satu grup outlet, pilih Buat Cabang ({$nextBranchCode}); kalau bukan cabang, revisi kode outlet dulu.",
            ];
        }

        return [
            'title' => 'Saran: cek data lama sebelum memilih',
            'body' => 'Datanya mirip sebagian. Pilih Buat Cabang kalau ini lokasi/outlet berbeda, atau Override kalau ini pembaruan outlet lama.',
        ];
    }

    protected static function duplicateOutletSummary(Outlet $outlet): string
    {
        $parts = array_filter([
            $outlet->kode_outlet,
            self::shortText($outlet->nama_outlet),
            self::shortText($outlet->alamat_outlet, 90),
        ], fn ($value): bool => filled($value));

        return implode(' - ', $parts);
    }

    protected static function textSimilarity(?string $left, ?string $right): float
    {
        $left = self::normalizeComparableText($left);
        $right = self::normalizeComparableText($right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);

        return (float) $percent;
    }

    protected static function sameComparableText(?string $left, ?string $right): bool
    {
        $left = self::normalizeComparableText($left);
        $right = self::normalizeComparableText($right);

        return $left !== '' && $left === $right;
    }

    protected static function normalizeComparableText(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    protected static function differentFilledValue(mixed $left, mixed $right): bool
    {
        return filled($left) && filled($right) && (string) $left !== (string) $right;
    }

    protected static function shortText(?string $value, int $limit = 70): string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        if (strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, max(0, $limit - 3))).'...';
    }

    protected static function registerTypeColor(?string $type): string
    {
        return match (strtoupper((string) $type)) {
            'LEAD' => 'warning',
            'NOO' => 'success',
            default => 'gray',
        };
    }

    protected static function registerTypeLabel(?string $type): string
    {
        return match (strtoupper((string) $type)) {
            'LEAD' => 'LEAD',
            'NOO' => 'NOO',
            default => '-',
        };
    }

    protected static function getTmOptions(string $search, ?int $creatorId, ?int $currentTmId, int $limit = 50): array
    {
        $options = [];
        $keyword = trim($search);

        if ($creatorId) {
            $creator = User::find($creatorId);

            if ($creator) {
                $tm = $creator->tm;

                if ($tm) {
                    $options[$tm->id] = $tm->nama_lengkap;
                } else {
                    $options[$creator->id] = $creator->nama_lengkap;
                }
            }
        }

        $query = User::query()
            ->select(['id', 'nama_lengkap'])
            ->when($keyword !== '', fn (Builder $builder) => $builder->where('nama_lengkap', 'like', '%'.$keyword.'%'))
            ->orderBy('nama_lengkap')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->nama_lengkap])
            ->toArray();

        if ($currentTmId && ! array_key_exists($currentTmId, $options) && ! array_key_exists($currentTmId, $query)) {
            $currentTm = User::query()->select(['id', 'nama_lengkap'])->find($currentTmId);
            if ($currentTm) {
                $options[$currentTm->id] = $currentTm->nama_lengkap;
            }
        }

        return $options + $query;
    }

    protected static function tmLabel(int|string|null $id): ?string
    {
        if (! $id) {
            return null;
        }

        return User::query()->whereKey($id)->value('nama_lengkap');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        Group::make([
                            Section::make('Data Outlet')
                                ->schema([
                                    TextEntry::make('nama_outlet')
                                        ->label('Nama Outlet'),
                                    TextEntry::make('distric')
                                        ->label('Distrik'),
                                    TextEntry::make('alamat_outlet')
                                        ->label('Alamat Outlet')
                                        ->columnSpanFull(),
                                    TextEntry::make('nama_pemilik_outlet')
                                        ->label('Nama Pemilik Outlet'),
                                    TextEntry::make('nomer_tlp_outlet')
                                        ->label('Nomor Telepon Outlet'),
                                    TextEntry::make('nomer_wakil_outlet')
                                        ->label('Nomor Wakil Outlet'),
                                    TextEntry::make('ktp_outlet')
                                        ->label('KTP Pemilik Outlet'),
                                    TextEntry::make('latlong')
                                        ->label('Koordinat Lat/Long')
                                        ->url(fn ($state) => $state ? "https://www.google.com/maps/place/{$state}" : null, shouldOpenInNewTab: true)
                                        ->color('primary'),
                                ])
                                ->columns(2),
                            Section::make('Dokumentasi')
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'md' => 2,
                                    ])->schema([
                                        ImageEntry::make('poto_shop_sign')
                                            ->label('Foto Tanda Toko')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        ImageEntry::make('poto_depan')
                                            ->label('Foto Depan')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        ImageEntry::make('poto_kiri')
                                            ->label('Foto Kiri')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        ImageEntry::make('poto_kanan')
                                            ->label('Foto Kanan')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        ImageEntry::make('poto_ktp')
                                            ->label('Foto KTP Pemilik')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        TextEntry::make('video')
                                            ->label('Video Toko')
                                            ->formatStateUsing(fn ($state) => filled($state) && $state !== '-' ? new HtmlString('<a href="'.StorageDisk::url($state).'" target="_blank" class="text-primary-600 hover:underline">Lihat Video</a>') : '-')
                                            ->html(),
                                    ]),
                                ]),
                            Section::make('Promotor dan Frontliner')
                                ->schema([
                                    TextEntry::make('oppo')
                                        ->label('Oppo'),
                                    TextEntry::make('vivo')
                                        ->label('Vivo'),
                                    TextEntry::make('realme')
                                        ->label('Realme'),
                                    TextEntry::make('samsung')
                                        ->label('Samsung'),
                                    TextEntry::make('xiaomi')
                                        ->label('Xiaomi'),
                                    TextEntry::make('fl')
                                        ->label('FL'),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Group::make([
                            Section::make('Informasi Tambahan')
                                ->schema([
                                    TextEntry::make('createdBy.nama_lengkap')
                                        ->label('Dibuat Oleh'),
                                    TextEntry::make('created_at')
                                        ->label('Tanggal Dibuat')
                                        ->date('d M Y'),
                                    TextEntry::make('type')
                                        ->label('Tipe')
                                        ->badge()
                                        ->color(fn (?string $state): string => self::registerTypeColor($state))
                                        ->formatStateUsing(fn (?string $state): string => self::registerTypeLabel($state)),
                                    TextEntry::make('keterangan')
                                        ->label('Keterangan')
                                        ->placeholder('-'),
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'APPROVED' => 'success',
                                            'REJECTED' => 'danger',
                                            'CONFIRMED' => 'info',
                                            default => 'gray',
                                        }),
                                ]),
                            Section::make('Struktur Organisasi')
                                ->schema([
                                    TextEntry::make('badanusaha.name')
                                        ->label('Badan Usaha'),
                                    TextEntry::make('divisi.name')
                                        ->label('Divisi'),
                                    TextEntry::make('divisionSetting.default_register_radius')
                                        ->label('Radius Default')
                                        ->formatStateUsing(fn ($state) => $state ? $state.' m' : '-')
                                        ->badge()
                                        ->color('primary'),
                                    TextEntry::make('region.name')
                                        ->label('Region'),
                                    TextEntry::make('cluster.name')
                                        ->label('Cluster'),
                                    TextEntry::make('tm.nama_lengkap')
                                        ->label('TM'),
                                ]),
                            Section::make('Outlet Hasil')
                                ->schema([
                                    TextEntry::make('outlet.kode_outlet')
                                        ->label('Kode Outlet')
                                        ->placeholder('-'),
                                    TextEntry::make('outlet.nama_outlet')
                                        ->label('Nama Outlet')
                                        ->placeholder('-'),
                                    TextEntry::make('outlet.status_outlet')
                                        ->label('Status Outlet')
                                        ->badge()
                                        ->color(fn (?string $state): string => match ($state) {
                                            'MAINTAIN' => 'success',
                                            'UNMAINTAIN' => 'warning',
                                            'UNPRODUCTIVE' => 'danger',
                                            default => 'gray',
                                        })
                                        ->placeholder('-'),
                                    TextEntry::make('outlet.deleted_at')
                                        ->label('Status Data')
                                        ->badge()
                                        ->color('danger')
                                        ->formatStateUsing(fn ($state) => $state ? 'Data Outlet Dihapus' : null)
                                        ->visible(fn ($record) => $record->outlet?->deleted_at !== null),
                                    TextEntry::make('outlet.id')
                                        ->label('Lihat Outlet')
                                        ->formatStateUsing(fn ($state) => $state ? 'Buka Detail Outlet' : null)
                                        ->url(fn ($record) => $record->outlet && Gate::allows('ViewAny:Outlet')
                                            ? route('filament.admin.resources.outlets.view', $record->outlet->id)
                                            : null)
                                        ->color('primary')
                                        ->visible(fn ($record) => $record->outlet
                                            && $record->outlet?->deleted_at === null
                                            && Gate::allows('ViewAny:Outlet'))
                                        ->placeholder('-'),
                                ])
                                ->visible(fn ($record) => $record->status === 'APPROVED' && $record->outlet !== null),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 4]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->date('d M Y'),
                TextColumn::make('createdBy.nama_lengkap')
                    ->label('Dibuat Oleh')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Jenis Register')
                    ->badge()
                    ->color(fn (?string $state): string => self::registerTypeColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::registerTypeLabel($state))
                    ->sortable(),
                TextColumn::make('kode_outlet')
                    ->label('Kode Outlet'),
                TextColumn::make('divisi.name')
                    ->label('Divisi')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('badanusaha.name')
                    ->label('Badan Usaha')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                TextColumn::make('alamat_outlet')
                    ->label('Alamat Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nama_pemilik_outlet')
                    ->label('Nama Pemilik Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ktp_outlet')
                    ->label('Nomor KTP Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nomer_tlp_outlet')
                    ->label('Nomor Telepon Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nomer_wakil_outlet')
                    ->label('Nomor Wakil Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distric')
                    ->label('Distrik'),
                TextColumn::make('region.name')
                    ->label('Region')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cluster.name')
                    ->label('Cluster')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_ktp')
                    ->label('Foto KTP')
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('KTP'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_shop_sign')
                    ->label('Foto Tanda Outlet')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_depan')
                    ->label('Foto Depan')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_kanan')
                    ->label('Foto Kanan')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_kiri')
                    ->label('Foto Kiri')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('video')
                    ->label('Video Outlet')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('VIDEO'))
                    ->color('primary')
                    ->url(fn ($state): ?string => filled($state) && $state !== '-' ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('oppo')
                    ->label('Oppo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vivo')
                    ->label('Vivo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('realme')
                    ->label('Realme')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('samsung')
                    ->label('Samsung')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('xiaomi')
                    ->label('Xiaomi')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fl')
                    ->label('Frontliner')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latlong')
                    ->label('Lokasi (LatLong)')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->color('primary')
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('limit')
                    ->label('Limit')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui') // Updated for clarity
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis Register')
                    ->options([
                        'LEAD' => 'LEAD',
                        'NOO' => 'NOO',
                    ]),
                Filter::make('region')
                    ->schema([
                        Select::make('businessEntity')
                            ->label('Badan Usaha')
                            ->live()
                            ->searchable()
                            ->options(fn (): array => OrganizationalHierarchyOptions::badanUsaha(activeOnly: false))
                            ->getSearchResultsUsing(fn (string $search): array => OrganizationalHierarchyOptions::searchBadanUsaha($search, activeOnly: false))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::badanUsahaLabel($value, activeOnly: false))
                            ->placeholder('Pilih Business Entity')
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $set('division', null);
                                $set('region', null);
                            }),
                        Select::make('division')
                            ->label('Divisi')
                            ->live()
                            ->searchable()
                            ->options(fn (callable $get): array => OrganizationalHierarchyOptions::division($get('businessEntity'), activeOnly: false))
                            ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchDivision($search, $get('businessEntity'), activeOnly: false))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value))
                            ->placeholder('Pilih Division')
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $set('region', null);
                            }),
                        Select::make('region')
                            ->label('Region')
                            ->searchable()
                            ->placeholder('Pilih Region')
                            ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('division'), activeOnly: false))
                            ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('division'), activeOnly: false))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::regionLabel($value))
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['businessEntity'] ?? null) {
                            $query->where('badanusaha_id', $data['businessEntity']);
                        }
                        if ($data['division'] ?? null) {
                            $query->where('divisi_id', $data['division']);
                        }
                        if ($data['region'] ?? null) {
                            $query->where('region_id', $data['region']);
                        }

                        return $query;
                    }),
                TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['RestoreAny:Register', 'ForceDeleteAny:Register'], Register::class)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('update_ktp')
                    ->label('Update KTP')
                    ->icon('heroicon-o-identification')
                    ->color('primary')
                    ->visible(fn ($record) => strtoupper((string) $record->type) === 'LEAD' && Gate::allows('Upgrade:Register'))
                    ->form([
                        TextInput::make('ktp_outlet')
                            ->label('Nomor KTP Outlet')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('poto_ktp')
                            ->label('Foto KTP')
                            ->image()
                            ->disk(StorageDisk::default())
                            ->fetchFileInformation(false)
                            ->required()
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                $userId = Auth::id();
                                $filenameGenerator = new FilenameGeneratorService;

                                return $filenameGenerator->generate($file, 'register-ktp', $userId);
                            }),
                    ])
                    ->action(function ($record, $data): void {
                        $record->update([
                            'ktp_outlet' => $data['ktp_outlet'],
                            'poto_ktp' => $data['poto_ktp'],
                            'type' => 'NOO',
                        ]);

                        Notification::make()
                            ->title('KTP Updated')
                            ->success()
                            ->send();
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->slideOver()
                    ->modalWidth('md')
                    ->visible(fn ($record) => ($record->status === 'PENDING' || $record->status === 'CONFIRMED') && Gate::allows('Approve:Register', $record) && strtoupper((string) $record->type) !== 'LEAD')
                    ->form([
                        Hidden::make('outlet_code_checked')
                            ->default(false)
                            ->dehydrated(false),
                        Hidden::make('checked_kode_outlet'),
                        Hidden::make('has_duplicate_outlet_code')
                            ->default(false)
                            ->dehydrated(false),
                        Hidden::make('duplicate_outlet_summary')
                            ->dehydrated(false),
                        Hidden::make('duplicate_suggestion_title')
                            ->dehydrated(false),
                        Hidden::make('duplicate_suggestion_body')
                            ->dehydrated(false),
                        Hidden::make('next_branch_code')
                            ->dehydrated(false),
                        TextInput::make('kode_outlet')
                            ->regex('/^\S+$/')
                            ->helperText('Isi kode outlet, lalu klik ikon kaca pembesar untuk mengecek.')
                            ->default(fn ($record) => $record->kode_outlet)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set): void {
                                self::resetOutletCodeCheck($set);
                            })
                            ->required()
                            ->suffixAction(
                                Action::make('check_outlet_code')
                                    ->label('Cek')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->color('primary')
                                    ->action(function (Get $get, Set $set, ?Register $record): void {
                                        self::checkOutletCodeForApproval($get, $set, $record);
                                    })
                            ),
                        Placeholder::make('duplicate_outlet_suggestion_preview')
                            ->label('Saran hasil cek')
                            ->content(fn (Get $get): HtmlString => self::duplicateOutletSuggestionPreview($get))
                            ->visible(fn (Get $get): bool => (bool) $get('outlet_code_checked') && (bool) $get('has_duplicate_outlet_code')),
                        TextInput::make('limit')
                            ->numeric()
                            ->default(fn ($record) => $record->limit)
                            ->visible(fn (Get $get): bool => (bool) $get('outlet_code_checked'))
                            ->required(),
                        ToggleButtons::make('duplicate_resolution')
                            ->label('Kode outlet sudah dipakai')
                            ->options([
                                RegisterApprovalService::DUPLICATE_BRANCH => 'Buat Cabang',
                                RegisterApprovalService::DUPLICATE_OVERRIDE => 'Override Outlet Lama',
                            ])
                            ->helperText('Buat Cabang membuat kode -CB1, -CB2, dan seterusnya. Override mengganti outlet lama dan menyimpan histori perubahan.')
                            ->inline()
                            ->visible(fn (Get $get): bool => (bool) $get('outlet_code_checked') && (bool) $get('has_duplicate_outlet_code'))
                            ->required(),
                    ])
                    ->action(function ($record, $data): void {
                        /** @var User|null $authUser */
                        $authUser = Auth::user();

                        self::validateOutletCodeCheckBeforeApproval($record, $data);

                        $record->forceFill([
                            'kode_outlet' => $data['kode_outlet'],
                            'limit' => $data['limit'],
                            'confirmed_at' => $record->confirmed_at ?? Carbon::now(),
                            'confirmed_by_id' => $record->confirmed_by_id ?? $authUser?->id,
                            'status' => 'CONFIRMED',
                        ])->save();

                        try {
                            $result = app(RegisterApprovalService::class)->approve(
                                $record,
                                $authUser,
                                $data['duplicate_resolution'] ?? RegisterApprovalService::DUPLICATE_BRANCH
                            );
                        } catch (BadRequestException $exception) {
                            Notification::make()
                                ->title('Approval gagal')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            throw ValidationException::withMessages([
                                'kode_outlet' => $exception->getMessage(),
                            ]);
                        }

                        Notification::make()
                            ->title($result['register']->nama_outlet.' Approved')
                            ->body('Kode outlet: '.$result['final_kode_outlet'])
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->slideOver()
                    ->modalWidth('md')
                    ->visible(fn ($record) => $record->status !== 'REJECTED' && $record->status !== 'APPROVED' && Gate::allows('Reject:Register', $record) && strtoupper((string) $record->type) !== 'LEAD')
                    ->schema([
                        Textarea::make('alasan')
                            ->required(),
                    ])
                    ->action(function ($record, $data): void {
                        /** @var User|null $authUser */
                        $authUser = Auth::user();

                        $record->update([
                            'rejected_at' => Carbon::now(),
                            'rejected_by_id' => $authUser?->id,
                            'status' => 'REJECTED',
                            'keterangan' => $data['alasan'],
                        ]);
                        Notification::make()
                            ->title($record->nama_outlet.' Rejected')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(function (?ListRegisters $livewire = null): bool {
                            $activeTab = $livewire?->activeTab;

                            return Gate::allows('Approve:Register') && $activeTab === 'confirmed';
                        })
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            /** @var User|null $authUser */
                            $authUser = Auth::user();

                            $approved = 0;
                            $skipped = 0;
                            $skippedNames = [];

                            $records->each(function (Register $record) use (&$approved, &$skipped, &$skippedNames, $authUser): void {
                                if ($record->status !== 'CONFIRMED') {
                                    $skipped++;
                                    $skippedNames[] = $record->nama_outlet ?? 'ID '.$record->id;

                                    return;
                                }

                                try {
                                    app(RegisterApprovalService::class)->approve(
                                        $record,
                                        $authUser,
                                        RegisterApprovalService::DUPLICATE_BRANCH
                                    );
                                } catch (BadRequestException) {
                                    $skipped++;
                                    $skippedNames[] = $record->nama_outlet ?? 'ID '.$record->id;

                                    return;
                                }

                                $approved++;
                            });

                            if ($approved > 0) {
                                Notification::make()
                                    ->title("{$approved} register di-approve")
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                $list = implode(', ', array_slice($skippedNames, 0, 3));
                                $more = count($skippedNames) > 3 ? ' dan lainnya' : '';

                                Notification::make()
                                    ->title("{$skipped} data dilewati")
                                    ->body("Status bukan CONFIRMED: {$list}{$more}")
                                    ->danger()
                                    ->send();
                            }
                        }),
                    BulkAction::make('bulk_reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(function (?ListRegisters $livewire = null): bool {
                            $activeTab = $livewire?->activeTab;

                            return Gate::allows('Reject:Register') && in_array($activeTab, ['pending', 'confirmed'], true);
                        })
                        ->form([
                            Textarea::make('alasan')
                                ->label('Alasan')
                                ->required(),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            /** @var User|null $authUser */
                            $authUser = Auth::user();

                            $rejected = 0;
                            $skipped = 0;
                            $skippedNames = [];

                            $records->each(function (Register $record) use (&$rejected, &$skipped, &$skippedNames, $authUser, $data): void {
                                if (in_array($record->status, ['APPROVED', 'REJECTED'], true)) {
                                    $skipped++;
                                    $skippedNames[] = $record->nama_outlet ?? 'ID '.$record->id;

                                    return;
                                }

                                $record->update([
                                    'rejected_at' => Carbon::now(),
                                    'rejected_by_id' => $authUser?->id,
                                    'status' => 'REJECTED',
                                    'keterangan' => $data['alasan'],
                                ]);

                                $rejected++;
                            });

                            if ($rejected > 0) {
                                Notification::make()
                                    ->title("{$rejected} register di-reject")
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                $list = implode(', ', array_slice($skippedNames, 0, 3));
                                $more = count($skippedNames) > 3 ? ' dan lainnya' : '';

                                Notification::make()
                                    ->title("{$skipped} data dilewati")
                                    ->body("Status sudah APPROVED/REJECTED: {$list}{$more}")
                                    ->danger()
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where(function (Builder $query) use ($user): void {
                FilamentOrganizationalScope::applyDirectColumns($query, $user, 'registers');
            })
            ->with(FilamentTableEagerLoad::registerHierarchy());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegisters::route('/'),
            'create' => CreateRegister::route('/create'),
            'view' => ViewRegister::route('/{record}'),
            'edit' => EditRegister::route('/{record}/edit'),
        ];
    }
}
