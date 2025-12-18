<x-filament-panels::page>
    @if(!$outlet_id)
        <div class="text-center py-12 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-800">
            <div class="mx-auto w-16 h-16 rounded-full bg-danger-50 dark:bg-danger-900/20 flex items-center justify-center mb-4">
                <x-heroicon-o-exclamation-circle class="h-8 w-8 text-danger-500" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Outlet Tidak Ditemukan</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Silakan pilih outlet dari halaman Live Visit.</p>
            <div class="mt-6">
                <x-filament::button tag="a" href="{{ \App\Filament\Pages\LiveVisit::getUrl() }}">
                    Kembali ke Live Visit
                </x-filament::button>
            </div>
        </div>
    @else
        {{-- Step: Location Confirmation --}}
        @if($step === 'location')
            <div class="space-y-6">
                {{-- Header --}}
                <div class="flex items-center gap-4 p-4 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-800">
                    <div class="w-12 h-12 rounded-lg bg-primary-50 dark:bg-primary-900/20 flex items-center justify-center shrink-0">
                        <x-heroicon-o-building-storefront class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-lg">{{ $outlet_name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $outlet_code }}</p>
                    </div>
                </div>

                {{-- Location Status --}}
                <div class="flex items-center justify-between px-4 py-3 bg-white dark:bg-gray-900 rounded-lg shadow-sm ring-1 ring-gray-200 dark:ring-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="relative flex h-3 w-3">
                            @if($locationReady)
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-success-500"></span>
                            @else
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-danger-500"></span>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $locationReady ? 'Lokasi Terdeteksi' : 'Mencari Lokasi...' }}
                            </p>
                            @if($locationReady)
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-0.5">{{ $current_latlong }}</p>
                            @endif
                        </div>
                    </div>
                    @if(!$locationReady)
                        <button type="button" onclick="requestLocation()" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                            Coba Lagi
                        </button>
                    @endif
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-800 p-6">
                    <div class="flex gap-3 mb-6 p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/30">
                        <x-heroicon-o-information-circle class="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0" />
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            Pastikan Anda berada di lokasi outlet. Foto dan video harus diambil di lokasi outlet untuk memastikan data akurat.
                        </p>
                    </div>

                    <x-filament::button 
                        wire:click="confirmLocation" 
                        class="w-full" 
                        size="lg" 
                        :disabled="!$locationReady">
                        @if($locationReady)
                            SAYA DI LOKASI - LANJUT
                        @else
                            Menunggu Lokasi...
                        @endif
                    </x-filament::button>
                </div>
            </div>

        {{-- Step: Form --}}
        @else
            <div class="space-y-6">
                {{-- Header with Back Button --}}
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="backToLocation" 
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 hover:text-gray-700 transition-colors">
                        <x-heroicon-o-arrow-left class="h-5 w-5" />
                    </button>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $outlet_name }}</h3>
                        <p class="text-sm text-gray-500">{{ $outlet_code }}</p>
                    </div>
                </div>

                {{-- Owner Info Section --}}
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-800 p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <x-heroicon-o-user class="h-5 w-5 text-gray-400" />
                        Informasi Pemilik
                    </h4>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium leading-6 text-gray-950 dark:text-white mb-2">Nama Pemilik</label>
                            <input type="text" wire:model="nama_pemilik" placeholder="Nama pemilik outlet"
                                class="block w-full rounded-lg border-none bg-white dark:bg-white/5 py-2.5 px-3 text-gray-950 dark:text-white ring-1 ring-inset ring-gray-950/10 dark:ring-white/20 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500 sm:text-sm sm:leading-6 shadow-sm placeholder:text-gray-400 dark:placeholder:text-gray-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium leading-6 text-gray-950 dark:text-white mb-2">Nomor Telepon</label>
                            <input type="tel" wire:model="nomor_pemilik" placeholder="08xxx"
                                class="block w-full rounded-lg border-none bg-white dark:bg-white/5 py-2.5 px-3 text-gray-950 dark:text-white ring-1 ring-inset ring-gray-950/10 dark:ring-white/20 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500 sm:text-sm sm:leading-6 shadow-sm placeholder:text-gray-400 dark:placeholder:text-gray-500">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium leading-6 text-gray-950 dark:text-white mb-2">Alamat Outlet</label>
                            <textarea wire:model="alamat_outlet" rows="3" placeholder="Alamat lengkap outlet"
                                class="block w-full rounded-lg border-none bg-white dark:bg-white/5 py-2.5 px-3 text-gray-950 dark:text-white ring-1 ring-inset ring-gray-950/10 dark:ring-white/20 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500 sm:text-sm sm:leading-6 shadow-sm placeholder:text-gray-400 dark:placeholder:text-gray-500"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Photo Section --}}
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-800 p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <x-heroicon-o-camera class="h-5 w-5 text-gray-400" />
                        Foto Outlet
                    </h4>

                    <div class="grid grid-cols-2 gap-4">
                        @foreach(['shop_sign' => 'Shop Sign', 'depan' => 'Depan', 'kanan' => 'Kanan', 'kiri' => 'Kiri'] as $field => $label)
                            <div class="relative group">
                                @php
                                    $photo = $this->{'photo_'.$field};
                                    $existing = $this->{'existing_'.$field};
                                @endphp

                                @if($photo || $existing)
                                    <div class="aspect-square rounded-xl bg-gray-100 dark:bg-gray-800 overflow-hidden relative ring-1 ring-gray-200 dark:ring-gray-700">
                                        @if($photo)
                                            <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                                            <button type="button" wire:click="removePhoto('photo_{{ $field }}')" 
                                                class="absolute top-2 right-2 p-1.5 rounded-full bg-black/50 hover:bg-black/70 text-white transition-colors backdrop-blur-sm">
                                                <x-heroicon-o-x-mark class="h-4 w-4" />
                                            </button>
                                        @else
                                            <img src="{{ Storage::disk(\App\Support\StorageDisk::default())->url($existing) }}" class="w-full h-full object-cover">
                                            <button type="button" wire:click="removeExistingPhoto('{{ $field }}')" 
                                                class="absolute top-2 right-2 p-1.5 rounded-full bg-black/50 hover:bg-black/70 text-white transition-colors backdrop-blur-sm">
                                                <x-heroicon-o-x-mark class="h-4 w-4" />
                                            </button>
                                        @endif
                                        <div class="absolute bottom-0 inset-x-0 p-2 bg-gradient-to-t from-black/60 to-transparent">
                                            <span class="text-xs font-medium text-white">{{ $label }}</span>
                                        </div>
                                    </div>
                                @else
                                    <label class="aspect-square rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 flex flex-col items-center justify-center cursor-pointer hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50/50 dark:hover:bg-primary-900/10 transition-all group-hover:text-primary-600">
                                        <input type="file" wire:model="photo_{{ $field }}" accept="image/*" class="hidden">
                                        <x-heroicon-o-plus class="h-8 w-8 text-gray-400 group-hover:text-primary-500 mb-1 transition-colors" />
                                        <span class="text-xs font-medium text-gray-500 group-hover:text-primary-600 transition-colors">{{ $label }}</span>
                                    </label>
                                @endif
                                <div wire:loading wire:target="photo_{{ $field }}" class="absolute inset-0 bg-white/80 dark:bg-gray-900/80 flex items-center justify-center rounded-xl z-10">
                                    <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-primary-500" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Video Section --}}
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-800 p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                        <x-heroicon-o-video-camera class="h-5 w-5 text-gray-400" />
                        Video Outlet
                    </h4>

                    <div class="relative">
                        @if($video || $existing_video)
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 border border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
                                        <x-heroicon-o-video-camera class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-gray-100 truncate">Video Outlet</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $video ? 'Siap diupload' : 'Tersimpan di database' }}</p>
                                    </div>
                                    <button type="button" wire:click="{{ $video ? "removePhoto('video')" : "removeExistingPhoto('video')" }}"
                                        class="p-2 rounded-lg hover:bg-danger-50 dark:hover:bg-danger-900/20 text-gray-400 hover:text-danger-600 transition-colors">
                                        <x-heroicon-o-trash class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        @else
                            <label class="flex flex-col items-center justify-center p-8 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl cursor-pointer hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50/50 dark:hover:bg-primary-900/10 transition-all group">
                                <input type="file" wire:model="video" accept="video/*" class="hidden">
                                <x-heroicon-o-video-camera class="h-10 w-10 text-gray-400 group-hover:text-primary-500 mb-2 transition-colors" />
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300 group-hover:text-primary-600 transition-colors">Upload Video</span>
                                <span class="text-xs text-gray-500 mt-1">Opsional jika foto lengkap</span>
                            </label>
                        @endif
                        <div wire:loading wire:target="video" class="absolute inset-0 bg-white/80 dark:bg-gray-900/80 flex items-center justify-center rounded-xl">
                            <div class="flex items-center gap-2 text-primary-600">
                                <x-heroicon-o-arrow-path class="h-5 w-5 animate-spin" />
                                <span class="text-sm font-medium">Mengupload video...</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status & Submit --}}
                <div class="fixed bottom-0 inset-x-0 p-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 z-10 lg:static lg:bg-transparent lg:border-none lg:p-0">
                    <div class="max-w-7xl mx-auto flex flex-col gap-4">
                        @if(!$this->hasAllPhotos())
                            <div class="flex items-center gap-2 text-warning-600 dark:text-warning-400 px-1">
                                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                                <p class="text-sm">Mohon lengkapi 4 foto atau 1 video</p>
                            </div>
                        @endif

                        <x-filament::button wire:click="submit" class="w-full" size="lg" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="submit">
                                SIMPAN PERUBAHAN
                            </span>
                            <span wire:loading wire:target="submit">Menyimpan...</span>
                        </x-filament::button>
                    </div>
                </div>
                {{-- Spacer for fixed bottom bar on mobile --}}
                <div class="h-24 lg:hidden"></div>
            </div>
        @endif
    @endif

    @push('scripts')
    <script>
        function requestLocation() {
            if (!navigator.geolocation) {
                @this.call('setLocationError', 'Browser tidak mendukung GPS');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latlong = `${position.coords.latitude},${position.coords.longitude}`;
                    @this.call('setLocation', latlong);
                },
                (error) => {
                    let message = 'Gagal mendapatkan lokasi';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Izin lokasi ditolak';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Lokasi tidak tersedia';
                            break;
                        case error.TIMEOUT:
                            message = 'Timeout';
                            break;
                    }
                    @this.call('setLocationError', message);
                },
                { 
                    enableHighAccuracy: true, 
                    timeout: 15000, 
                    maximumAge: 0 
                }
            );
        }

        document.addEventListener('DOMContentLoaded', function() {
            requestLocation();
        });

        document.addEventListener('livewire:navigated', function() {
            requestLocation();
        });
    </script>
    @endpush
</x-filament-panels::page>
