<?php

namespace App\Traits;

use App\Models\Order;
use App\Models\Inbound;
use App\Models\Plan;
use App\Services\ClientNamingService;
use App\Services\MarzbanService;
use App\Services\PasarGuardService;
use App\Services\XUIService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

trait ManagesServiceProvisioning
{
    /**
     * سرویس کاربر را در پنل مربوطه (Marzban/XUI) ایجاد یا تمدید می‌کند.
     *
     * @param string $panelType نوع پنل (marzban یا xui)
     * @param \Illuminate\Support\Collection $settings تنظیمات برنامه
     * @param Order $order سفارش
     * @param bool $isTelegramContext آیا از تلگرام فراخوانی شده؟ (برای مدیریت خطا)
     * @return array|false آرایه‌ای شامل ['config' => $config, 'expires_at' => $expires_at] در صورت موفقیت، یا false در صورت شکست
     */
    public function provisionService(string $panelType, $settings, Order $order, bool $isTelegramContext = false)
    {
        $user = $order->user;
        $plan = $order->plan;
        if (!$plan) {
            $this->handleProvisioningError("سفارش {$order->id} فاقد پلن است.", $isTelegramContext);
            return false;
        }

        $isRenewal = (bool)$order->renews_order_id;
        $originalOrder = null;

        if ($isRenewal) {
            $originalOrder = Order::find($order->renews_order_id);
            if (!$originalOrder) {
                $this->handleProvisioningError('سفارش اصلی جهت تمدید یافت نشد.', $isTelegramContext);
                return false;
            }
        }

        // نام کاربری بر اساس سفارش اصلی (در صورت تمدید) یا سفارش فعلی (در صورت خرید جدید)
        // Use sequential naming if enabled
        if ($isRenewal && $originalOrder && $originalOrder->panel_username) {
            $uniqueUsername = $originalOrder->panel_username;
        } else {
            // Check if order already has panel_username (custom or sequential from creation)
            $uniqueUsername = $order->panel_username ?? ClientNamingService::generate($user->id, $isRenewal ? $originalOrder->id ?? $order->id : $order->id);
        }

        // محاسبه تاریخ انقضای جدید
        $baseDate = now();
        if ($isRenewal) {
            $baseDate = (new \DateTime($originalOrder->expires_at));
            // اگر سرویس منقضی شده، تمدید از امروز حساب شود
            if ($baseDate < now()) {
                $baseDate = now();
            }
        }

        // $newExpiresAt به یک آبجکت DateTime تبدیل می‌شود
        $newExpiresAt = $baseDate->modify("+{$plan->duration_days} days");

        $finalConfig = null;
        $success = false;

        $targetServer = $order->server;
        if (!$targetServer && $order->server_id && class_exists('Modules\MultiServer\Models\Server')) {
            $targetServer = \Modules\MultiServer\Models\Server::find($order->server_id);
        }
        if (!$targetServer && $isRenewal && $originalOrder && $originalOrder->server_id && class_exists('Modules\MultiServer\Models\Server')) {
            $targetServer = \Modules\MultiServer\Models\Server::find($originalOrder->server_id);
        }

        if ($targetServer && $targetServer->is_active) {
            $panelType = strtolower($targetServer->type ?? $panelType);
            if ($panelType === 'sanaei') $panelType = 'xui';
        }

        try {
            if ($panelType === 'pasarguard') {
                $pasarguardHost = $targetServer ? $targetServer->full_host : $settings->get('pasarguard_host');
                $pasarguardUser = $targetServer ? $targetServer->username : $settings->get('pasarguard_username');
                $pasarguardPass = $targetServer ? $targetServer->password : $settings->get('pasarguard_password');
                $pasarguardNode = $targetServer ? ($targetServer->pasarguard_node_hostname ?? $pasarguardHost) : $settings->get('pasarguard_node_hostname');

                $pasarguardService = new PasarGuardService((string)$pasarguardHost, (string)$pasarguardUser, (string)$pasarguardPass, (string)$pasarguardNode);

                $userData = ['expire' => $newExpiresAt->getTimestamp(), 'data_limit' => ($plan->volume_gb ?? $plan->data_limit_gb ?? 0) * 1024 * 1024 * 1024];

                if ($isRenewal) {
                    $response = $pasarguardService->updateUser($uniqueUsername, $userData);
                    $pasarguardService->resetUserTraffic($uniqueUsername);
                } else {
                    $response = $pasarguardService->createUser(array_merge($userData, ['username' => $uniqueUsername]));
                }

                if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                    $finalConfig = $pasarguardService->generateSubscriptionLink($response);
                    $success = true;
                } else {
                    $error = $response['detail'] ?? 'پاسخ نامعتبر از PasarGuard.';
                    $this->handleProvisioningError($error, $isTelegramContext, ['response' => $response]);
                    return false;
                }

            } elseif ($panelType === 'marzban') {
                $marzbanHost = $targetServer ? $targetServer->full_host : $settings->get('marzban_host');
                $marzbanUser = $targetServer ? $targetServer->username : $settings->get('marzban_sudo_username');
                $marzbanPass = $targetServer ? $targetServer->password : $settings->get('marzban_sudo_password');
                $marzbanNode = $targetServer ? ($targetServer->marzban_node_hostname ?? $marzbanHost) : $settings->get('marzban_node_hostname');

                $marzbanService = new MarzbanService((string)$marzbanHost, (string)$marzbanUser, (string)$marzbanPass, (string)$marzbanNode);

                // مطمئن شوید مدل Plan ستون data_limit_gb را دارد (در کد شما volume_gb بود، من به data_limit_gb تغییر دادم)
                $userData = ['expire' => $newExpiresAt->getTimestamp(), 'data_limit' => ($plan->volume_gb ?? $plan->data_limit_gb ?? 0) * 1024 * 1024 * 1024];

                if ($isRenewal) {
                    $response = $marzbanService->updateUser($uniqueUsername, $userData);
                    $marzbanService->resetUserTraffic($uniqueUsername);
                } else {
                    $response = $marzbanService->createUser(array_merge($userData, ['username' => $uniqueUsername]));
                }

                if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                    $finalConfig = $marzbanService->generateSubscriptionLink($response);
                    $success = true;
                } else {
                    $error = $response['detail'] ?? 'پاسخ نامعتبر از مرزبان.';
                    $this->handleProvisioningError($error, $isTelegramContext, ['response' => $response]);
                    return false;
                }

            } elseif ($panelType === 'xui') {
                $inboundId = $settings->get('xui_default_inbound_id');
                if (!$inboundId) {
                    $this->handleProvisioningError('اینباند XUI در تنظیمات ست نشده.', $isTelegramContext); return false;
                }
                $xuiService = new XUIService($settings->get('xui_host'), $settings->get('xui_user'), $settings->get('xui_pass'));
                if (!$xuiService->login()) {
                    $this->handleProvisioningError('خطا در لاگین به پنل X-UI.', $isTelegramContext); return false;
                }
                $inbound = Inbound::find($inboundId);
                if (!$inbound || !$inbound->inbound_data) {
                    $this->handleProvisioningError('اطلاعات اینباند پیش‌فرض X-UI یافت نشد.', $isTelegramContext); return false;
                }

                $inboundData = json_decode($inbound->inbound_data, true);
                // مطمئن شوید مدل Plan ستون data_limit_gb را دارد (در کد شما volume_gb بود، من به data_limit_gb تغییر دادم)
                $clientData = ['email' => $uniqueUsername, 'total' => ($plan->volume_gb ?? $plan->data_limit_gb ?? 0) * 1024 * 1024 * 1024, 'expiryTime' => $newExpiresAt->getTimestamp() * 1000];

                if ($isRenewal) {
                    //TODO: منطق تمدید کاربر در XUI (یافتن کاربر و آپدیت)
                    $this->handleProvisioningError('تمدید خودکار برای پنل XUI هنوز پیاده‌سازی نشده است.', $isTelegramContext);
                    return false;
                }

                $response = $xuiService->addClient($inboundData['id'], $clientData);

                if ($response && isset($response['success']) && $response['success']) {
                    $linkType = $settings->get('xui_link_type', 'single');
                    if ($linkType === 'subscription') {
                        $subId = $response['generated_subId'] ?? null;
                        $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');

                        // Fallback: if subscription URL base is not set, try to use X-UI host
                        if (empty($subBaseUrl)) {
                            $xuiHost = $settings->get('xui_host');
                            if (!empty($xuiHost)) {
                                $parsed = parse_url($xuiHost);
                                $scheme = $parsed['scheme'] ?? 'http';
                                $host = $parsed['host'] ?? '';
                                $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                                
                                // Fix: If panel is on 2053, subscription is usually on 2096
                                if ($port === ':2053') {
                                    $port = ':2096';
                                }

                                $subBaseUrl = "{$scheme}://{$host}{$port}";
                            }
                        }

                        // Universal Fix: Ensure port 2053 is replaced by 2096
                        if (str_contains($subBaseUrl, ':2053')) {
                            $subBaseUrl = str_replace(':2053', ':2096', $subBaseUrl);
                        }

                        if ($subBaseUrl && $subId) {
                            $finalConfig = $subBaseUrl . '/sub/' . $subId;
                            $success = true;
                        } else {
                            $this->handleProvisioningError('آدرس پایه اشتراک XUI یا ID اشتراک ست نشده.', $isTelegramContext); return false;
                        }
                    } else { // single link
                        $uuid = $response['generated_uuid'] ?? null;
                        if (!$uuid) { $this->handleProvisioningError('UUID از پنل XUI دریافت نشد.', $isTelegramContext); return false; }

                        $streamSettings = json_decode($inboundData['streamSettings'], true);
                        $parsedUrl = parse_url($settings->get('xui_host'));
                        $serverAddress = !empty($inboundData['listen']) ? $inboundData['listen'] : $parsedUrl['host'];
                        $port = $inboundData['port'];
                        $remark = $inboundData['remark'];
                        $paramsArray = [
                            'type' => $streamSettings['network'] ?? null,
                            'security' => $streamSettings['security'] ?? null,
                            'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null),
                            'sni' => $streamSettings['tlsSettings']['serverName'] ?? null,
                            'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null
                        ];
                        $params = http_build_query(array_filter($paramsArray));
                        $fullRemark = $uniqueUsername . '|' . $remark;
                        $finalConfig = "vless://{$uuid}@{$serverAddress}:{$port}?{$params}#" . urlencode($fullRemark);
                        $success = true;
                    }
                } else {
                    $this->handleProvisioningError($response['msg'] ?? 'پاسخ نامعتبر از XUI', $isTelegramContext, ['response' => $response]);
                    return false;
                }
            }

            if ($success) {
                return ['config' => $finalConfig, 'expires_at' => $newExpiresAt];
            } else {
                $this->handleProvisioningError('موفقیت‌آمیز نبود (Success=false) اما خطایی رخ نداد.', $isTelegramContext);
                return false;
            }

        } catch (\Exception $e) {
            $this->handleProvisioningError("خطای سیستمی: " . $e->getMessage(), $isTelegramContext, ['trace' => $e->getTraceAsString()]);
            return false;
        }
    }

    /**
     * مدیریت خطاها در Trait
     */
    protected function handleProvisioningError(string $message, bool $isTelegram, array $context = [])
    {
        Log::error($message, $context);
        if (!$isTelegram) {
            // اگر در فیلامنت هستیم، نوتیفیکیشن نشان بده
            Notification::make()->title('خطا در ساخت سرویس')->body($message)->danger()->send();
        }
        // اگر در تلگرام باشیم، فقط لاگ می‌اندازد و false برمی‌گرداند تا در try/catch مدیریت شود
    }
}
