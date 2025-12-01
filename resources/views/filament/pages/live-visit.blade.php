<x-filament-panels::page>
    {{-- Radius Modal --}}
    @if($showRadiusModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" wire:click.self="closeRadiusModal">
            <div class="bg-white dark:bg-gray-900 rounded-xl p-6 mx-4 max-w-sm w-full shadow-2xl ring-1 ring-gray-900/5">
                <div class="text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-warning-50 dark:bg-warning-900/20 mb-4">
                        <x-heroicon-o-map-pin class="h-6 w-6 text-warning-600" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Di Luar Jangkauan</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Anda berada terlalu jauh dari lokasi outlet untuk melakukan check-in.
                    </p>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-4 text-center">
                    <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($userDistance, 0) }}m</p>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mt-1">Jarak Anda</p>
                    </div>
                    <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($outletRadius, 0) }}m</p>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mt-1">Maksimal</p>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button type="button" wire:click="closeRadiusModal"
                        class="flex-1 px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Tutup
                    </button>
                    @if($outletLatlong)
                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $outletLatlong }}&travelmode=walking" 
                            target="_blank"
                            wire:click="navigateToOutlet"
                            class="flex-[2] flex items-center justify-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors shadow-sm">
                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                            Navigasi
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Visit Aktif (Checkout Flow) --}}
    @if($this->activeVisit)
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-gray-800 overflow-hidden">
            <div class="border-b border-gray-100 dark:border-gray-800 p-4 sm:p-6 bg-gray-50/50 dark:bg-gray-800/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-2.5 h-2.5 rounded-full bg-success-500 animate-pulse absolute -top-0.5 -right-0.5 ring-2 ring-white dark:ring-gray-900"></div>
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg ring-1 ring-gray-200 dark:ring-gray-700">
                                <x-heroicon-o-building-storefront class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                            </div>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Kunjungan Aktif</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $this->activeVisit->outlet?->nama_outlet ?? '-' }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-xs font-medium">
                            <x-heroicon-o-clock class="h-3.5 w-3.5" />
                            <span>Check-in {{ $this->activeVisit->check_in_time?->format('H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-6">
                <form wire:submit="checkout" class="space-y-6">
                    {{-- Foto Checkout --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Foto Checkout <span class="text-danger-500">*</span>
                        </label>
                        
                        <div class="space-y-3">
                            <div class="relative group">
                                <input type="file" 
                                    id="checkout-camera"
                                    wire:model="checkout_photo" 
                                    accept="image/*" 
                                    capture="user"
                                    class="hidden">
                                
                                <button type="button" onclick="document.getElementById('checkout-camera').click()" 
                                    class="w-full flex flex-col items-center justify-center p-8 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50/50 dark:hover:bg-primary-900/10 transition-all group-hover:text-primary-600">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                        <x-heroicon-o-camera class="h-6 w-6 text-gray-500 dark:text-gray-400 group-hover:text-primary-600" />
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Ambil Foto Checkout</span>
                                    <span class="text-xs text-gray-500 mt-1">Wajib foto langsung di lokasi</span>
                                </button>
                            </div>

                            <div wire:loading wire:target="checkout_photo" class="flex items-center justify-center gap-2 text-primary-600 text-sm py-2">
                                <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                                <span>Mengupload foto...</span>
                            </div>

                            @if($checkout_photo)
                                <div class="flex items-center gap-3 p-3 bg-success-50 dark:bg-success-900/20 border border-success-100 dark:border-success-900/30 rounded-lg">
                                    <div class="w-8 h-8 rounded-full bg-success-100 dark:bg-success-900/50 flex items-center justify-center shrink-0">
                                        <x-heroicon-o-check class="h-4 w-4 text-success-600 dark:text-success-400" />
                                    </div>
                                    <p class="text-sm font-medium text-success-900 dark:text-success-100">Foto berhasil diambil</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Transaksi --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Ada Transaksi?
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="checkout_transaksi" value="YES" class="sr-only peer">
                                <div class="flex items-center justify-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-700 peer-checked:border-success-500 peer-checked:bg-success-50 dark:peer-checked:bg-success-900/20 peer-checked:text-success-700 dark:peer-checked:text-success-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all">
                                    <x-heroicon-o-check-circle class="h-5 w-5" />
                                    <span class="font-medium">Ya</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="checkout_transaksi" value="NO" class="sr-only peer">
                                <div class="flex items-center justify-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-700 peer-checked:border-danger-500 peer-checked:bg-danger-50 dark:peer-checked:bg-danger-900/20 peer-checked:text-danger-700 dark:peer-checked:text-danger-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all">
                                    <x-heroicon-o-x-circle class="h-5 w-5" />
                                    <span class="font-medium">Tidak</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Laporan --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Laporan Kunjungan <span class="text-danger-500">*</span>
                        </label>
                        <textarea wire:model="checkout_laporan" rows="3" 
                            placeholder="Tuliskan hasil kunjungan..."
                            class="block w-full rounded-lg border-none bg-white dark:bg-white/5 py-2.5 px-3 text-gray-950 dark:text-white ring-1 ring-inset ring-gray-950/10 dark:ring-white/20 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500 sm:text-sm sm:leading-6 shadow-sm placeholder:text-gray-400 dark:placeholder:text-gray-500"></textarea>
                    </div>

                    <div class="pt-2">
                        <x-filament::button type="submit" color="danger" class="w-full" size="lg" wire:loading.attr="disabled" onclick="setCheckoutLocation()">
                            <span wire:loading.remove wire:target="checkout">
                                SELESAI KUNJUNGAN
                            </span>
                            <span wire:loading wire:target="checkout">Memproses...</span>
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>
    @else
        {{-- Check-in Flow --}}
        <div class="space-y-6">
            
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
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $locationReady ? 'GPS Aktif' : ($locationError ?? 'Mencari lokasi...') }}
                    </span>
                </div>
                @if(!$locationReady)
                    <button type="button" onclick="requestLocation()" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                        Coba Lagi
                    </button>
                @endif
            </div>

            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-gray-800 overflow-hidden">
                {{-- Step: Select Outlet --}}
                @if($checkinStep === 'select')
                    {{-- Tabs --}}
                    <div class="border-b border-gray-200 dark:border-gray-800">
                        <div class="flex">
                            <button type="button" wire:click="$set('checkin_tipe', 'PLANNED')"
                                class="flex-1 py-4 text-sm font-medium border-b-2 transition-colors {{ $checkin_tipe === 'PLANNED' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                                PLANNED
                            </button>
                            <button type="button" wire:click="$set('checkin_tipe', 'EXTRACALL')"
                                class="flex-1 py-4 text-sm font-medium border-b-2 transition-colors {{ $checkin_tipe === 'EXTRACALL' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                                EXTRACALL
                            </button>
                        </div>
                    </div>

                    {{-- Search (Extracall) --}}
                    @if($checkin_tipe === 'EXTRACALL')
                        <div class="p-4 border-b border-gray-100 dark:border-gray-800">
                            <div class="relative">
                                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                                <input type="text" 
                                    wire:model.live.debounce.300ms="outletSearch"
                                    placeholder="Cari nama atau kode outlet..."
                                    class="block w-full rounded-lg border-none bg-white dark:bg-white/5 py-2.5 pl-10 pr-4 text-gray-950 dark:text-white ring-1 ring-inset ring-gray-950/10 dark:ring-white/20 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500 sm:text-sm sm:leading-6 shadow-sm placeholder:text-gray-400 dark:placeholder:text-gray-500">
                            </div>
                        </div>
                    @endif

                    {{-- Selected Outlet --}}
                    @if($this->selectedOutlet)
                        <div class="p-4 bg-primary-50/30 dark:bg-primary-900/10 border-b border-primary-100 dark:border-primary-900/20">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 uppercase tracking-wider">Outlet Terpilih</span>
                                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $this->selectedOutlet->nama_outlet }}</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->selectedOutlet->kode_outlet }}</p>
                                </div>
                                <button type="button" wire:click="clearOutlet" class="p-1 rounded-full hover:bg-white/50 dark:hover:bg-gray-800/50 text-gray-400 hover:text-gray-600 transition-colors">
                                    <x-heroicon-o-x-mark class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- List --}}
                    <div class="max-h-[240px] overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $outlets = $checkin_tipe === 'PLANNED' ? $this->plannedOutlets : $this->extracallOutlets;
                        @endphp

                        @forelse($outlets as $outlet)
                            <button type="button" 
                                wire:click="selectOutlet({{ $outlet->id }})"
                                class="w-full text-left p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group relative">
                                @if($checkin_outlet_id === $outlet->id)
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary-500"></div>
                                @endif
                                
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100 {{ $checkin_outlet_id === $outlet->id ? 'text-primary-600 dark:text-primary-400' : '' }}">
                                            {{ $outlet->nama_outlet }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-mono">
                                                {{ $outlet->kode_outlet }}
                                            </span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-[200px]">
                                                {{ $outlet->alamat_outlet ?? $outlet->distric ?? '-' }}
                                            </span>
                                        </div>
                                    </div>
                                    @if($checkin_outlet_id === $outlet->id)
                                        <x-heroicon-o-check-circle class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                                    @else
                                        <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-300 group-hover:text-gray-400" />
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="py-12 text-center">
                                <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                                    <x-heroicon-o-building-storefront class="h-6 w-6 text-gray-400" />
                                </div>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">Tidak ada outlet</p>
                                <p class="text-sm text-gray-400 mt-1">
                                    {{ $checkin_tipe === 'PLANNED' ? 'Tidak ada jadwal kunjungan hari ini' : 'Coba cari dengan kata kunci lain' }}
                                </p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Footer Action --}}
                    @if($this->selectedOutlet)
                        <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900">
                            <x-filament::button 
                                wire:click="proceedToPhoto" 
                                class="w-full"
                                size="lg"
                                :disabled="!$locationReady">
                                Lanjut Check-in
                            </x-filament::button>
                        </div>
                    @endif

                {{-- Step: Photo --}}
                @elseif($checkinStep === 'photo')
                    <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                        <button type="button" wire:click="backToSelect" class="p-2 -ml-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 hover:text-gray-700 transition-colors">
                            <x-heroicon-o-arrow-left class="h-5 w-5" />
                        </button>
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Konfirmasi Check-in</h3>
                    </div>

                    <div class="p-6">
                        <div class="mb-6">
                            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Outlet Tujuan</p>
                                <p class="font-semibold text-gray-900 dark:text-gray-100 text-lg">{{ $this->selectedOutlet->nama_outlet }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $this->selectedOutlet->alamat_outlet }}</p>
                            </div>
                        </div>

                        <form wire:submit="checkin" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Foto Selfie <span class="text-danger-500">*</span>
                                </label>
                                
                                <div class="relative group">
                                    <input type="file" 
                                        id="checkin-camera"
                                        wire:model="checkin_photo" 
                                        accept="image/*" 
                                        capture="user"
                                        class="hidden">
                                    
                                    <button type="button" onclick="document.getElementById('checkin-camera').click()" 
                                        class="w-full flex flex-col items-center justify-center p-8 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50/50 dark:hover:bg-primary-900/10 transition-all group-hover:text-primary-600">
                                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                            <x-heroicon-o-camera class="h-6 w-6 text-gray-500 dark:text-gray-400 group-hover:text-primary-600" />
                                        </div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Ambil Foto</span>
                                        <span class="text-xs text-gray-500 mt-1">Wajib foto selfie di lokasi</span>
                                    </button>
                                </div>

                                <div wire:loading wire:target="checkin_photo" class="flex items-center justify-center gap-2 text-primary-600 text-sm py-2">
                                    <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                                    <span>Mengupload foto...</span>
                                </div>

                                @if($checkin_photo)
                                    <div class="mt-3 flex items-center gap-3 p-3 bg-success-50 dark:bg-success-900/20 border border-success-100 dark:border-success-900/30 rounded-lg">
                                        <div class="w-8 h-8 rounded-full bg-success-100 dark:bg-success-900/50 flex items-center justify-center shrink-0">
                                            <x-heroicon-o-check class="h-4 w-4 text-success-600 dark:text-success-400" />
                                        </div>
                                        <p class="text-sm font-medium text-success-900 dark:text-success-100">Foto siap</p>
                                    </div>
                                @endif
                            </div>

                            <x-filament::button 
                                type="submit" 
                                class="w-full" 
                                size="lg"
                                wire:loading.attr="disabled"
                                :disabled="!$checkin_photo">
                                <span wire:loading.remove wire:target="checkin">
                                    MULAI KUNJUNGAN
                                </span>
                                <span wire:loading wire:target="checkin">Memproses...</span>
                            </x-filament::button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Riwayat Visit --}}
    <div class="mt-8">
        <div class="flex items-center justify-between mb-4 px-1">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Riwayat Hari Ini</h3>
            <span class="text-xs font-medium px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                {{ $this->todayVisits->count() }}
            </span>
        </div>
        
        @if($this->todayVisits->isEmpty())
            <div class="text-center py-12 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-800">
                <p class="text-gray-500 dark:text-gray-400 text-sm">Belum ada kunjungan hari ini</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($this->todayVisits as $visit)
                    <div class="group bg-white dark:bg-gray-900 rounded-xl p-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 hover:ring-primary-500/50 transition-all">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex gap-3 min-w-0">
                                <div class="mt-1.5 shrink-0">
                                    <div class="w-2 h-2 rounded-full {{ $visit->check_out_time ? 'bg-success-500' : 'bg-warning-500' }}"></div>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $visit->outlet?->nama_outlet ?? '-' }}</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $visit->outlet?->kode_outlet }}</p>
                                    
                                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                                        <span class="flex items-center gap-1 bg-gray-50 dark:bg-white/5 px-2 py-1 rounded-md ring-1 ring-inset ring-gray-500/10">
                                            <x-heroicon-o-clock class="h-3 w-3" />
                                            {{ $visit->check_in_time?->format('H:i') }}
                                            @if($visit->check_out_time)
                                                <span class="mx-1">&rarr;</span>
                                                {{ $visit->check_out_time->format('H:i') }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col items-end gap-2 shrink-0">
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $visit->tipe_visit === 'PLANNED' ? 'bg-blue-50 text-blue-700 ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30' : 'bg-purple-50 text-purple-700 ring-purple-700/10 dark:bg-purple-400/10 dark:text-purple-400 dark:ring-purple-400/30' }}">
                                    {{ $visit->tipe_visit }}
                                </span>
                                @if($visit->transaksi === 'YES')
                                    <span class="inline-flex items-center gap-1 rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                        <x-heroicon-o-currency-dollar class="h-3 w-3" />
                                        Transaksi
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        let currentPosition = null;

        function requestLocation() {
            if (!navigator.geolocation) {
                @this.call('setLocationError', 'Browser tidak mendukung GPS');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    currentPosition = `${position.coords.latitude},${position.coords.longitude}`;
                    @this.call('setLocation', currentPosition);
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

        function setCheckoutLocation() {
            if (currentPosition) {
                @this.set('checkout_latlong', currentPosition);
            }
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
