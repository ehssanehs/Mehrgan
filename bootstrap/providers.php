<?php

return [
    App\Providers\AppServiceProvider::class,
    // Registers OrderPaid => RewardReferrerListener.
    App\Providers\EventServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\ViewServiceProvider::class,
];
