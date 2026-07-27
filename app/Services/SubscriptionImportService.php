<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionImportService
{
    /**
     * Import a subscription
     *
     * @param string $input VLESS URI or Subscription URL
     * @param User $user Owner user
     * @param string $source Source: 'web' or 'telegram'
     * @return array ['success' => bool, 'order' => Order|null, 'error' => string|null]
     */
    public static function import(string $input, User $user, string $source = 'web'): array
    {
        $input = trim($input);

        if (empty($input)) {
            return [
                'success' => false,
                'order' => null,
                'error' => 'Input cannot be empty.',
            ];
        }

        // Step 1: Detect input type and extract UUID
        $uuid = null;
        $originalConfig = $input;
        $parsedVlessUris = [];

        $inputType = VlessParserService::detectInputType($input);

        if ($inputType === 'vless') {
            $uuid = VlessParserService::extractUuidFromVless($input);
            if (!$uuid) {
                Log::warning('Invalid VLESS URI provided for import', [
                    'input' => Str::limit($input, 200),
                    'user_id' => $user->id,
                ]);
                return [
                    'success' => false,
                    'order' => null,
                    'error' => 'Invalid VLESS URI. Please check the format and UUID.',
                ];
            }
            $parsedVlessUris = [['uri' => $input, 'uuid' => $uuid]];

        } elseif ($inputType === 'url') {
            // Fetch subscription URL
            $fetchResult = SubscriptionFetcherService::fetch($input);
            if (!$fetchResult['success']) {
                return [
                    'success' => false,
                    'order' => null,
                    'error' => $fetchResult['error'] ?? 'Failed to fetch subscription URL.',
                ];
            }

            $content = $fetchResult['content'];
            $parsed = VlessParserService::parseSubscriptionContent($content);

            if (empty($parsed)) {
                Log::warning('No VLESS entries found in subscription content', [
                    'url' => $input,
                    'content_preview' => Str::limit($content, 200),
                    'user_id' => $user->id,
                ]);
                return [
                    'success' => false,
                    'order' => null,
                    'error' => 'No valid VLESS configurations found in subscription URL. Content may be malformed.',
                ];
            }

            // Use ONLY UUID from FIRST VLESS entry as per requirement
            $uuid = $parsed[0]['uuid'];
            $parsedVlessUris = $parsed;
            $originalConfig = $input; // Keep original subscription URL as config

        } else {
            return [
                'success' => false,
                'order' => null,
                'error' => 'Invalid input. Please provide a valid VLESS URI (vless://...) or Subscription URL (https://...).',
            ];
        }

        // Step 2: Validate UUID
        if (!VlessParserService::isValidUuid($uuid)) {
            return [
                'success' => false,
                'order' => null,
                'error' => 'Invalid UUID format extracted from input.',
            ];
        }

        // Step 3: Duplicate protection - check if UUID already belongs to any user
        $existingOrder = Order::where('panel_client_id', $uuid)->where('status', 'paid')->first();
        if ($existingOrder) {
            if ($existingOrder->user_id === $user->id) {
                return [
                    'success' => false,
                    'order' => null,
                    'error' => 'This subscription is already imported in your account.',
                ];
            } else {
                Log::warning('Duplicate UUID import attempt', [
                    'uuid' => $uuid,
                    'existing_user_id' => $existingOrder->user_id,
                    'attempting_user_id' => $user->id,
                ]);
                return [
                    'success' => false,
                    'order' => null,
                    'error' => 'This subscription already belongs to another user and cannot be imported.',
                ];
            }
        }

        // Step 4: Search panel using UUID
        try {
            $panelResult = PanelSearchService::searchByUuid($uuid);
        } catch (\Exception $e) {
            Log::error('Exception during panel search for UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'order' => null,
                'error' => 'Error searching panel for this UUID. Please try again later.',
            ];
        }

        if (!$panelResult) {
            Log::info('UUID not found in any panel', [
                'uuid' => $uuid,
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'order' => null,
                'error' => 'This UUID does not exist in any configured panel. Please check the UUID or contact support.',
            ];
        }

        // Step 5: Read subscription info from panel and synchronize
        try {
            $order = self::createOrderFromPanelData(
                $uuid,
                $panelResult,
                $user,
                $source,
                $originalConfig,
                $parsedVlessUris
            );

            Log::info('Subscription imported successfully', [
                'uuid' => $uuid,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'panel_type' => $panelResult['type'],
                'server_id' => $panelResult['server_id'] ?? null,
            ]);

            return [
                'success' => true,
                'order' => $order,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create order from panel data', [
                'uuid' => $uuid,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'order' => null,
                'error' => 'Failed to import subscription: ' . $e->getMessage(),
            ];
        }
    }

    protected static function createOrderFromPanelData(
        string $uuid,
        array $panelResult,
        User $user,
        string $source,
        string $originalConfig,
        array $parsedVlessUris
    ): Order {
        return DB::transaction(function () use ($uuid, $panelResult, $user, $source, $originalConfig, $parsedVlessUris) {
            $type = $panelResult['type'];
            $serverId = $panelResult['server_id'] ?? null;
            $server = $panelResult['server'] ?? null;
            $subscriptionLink = $panelResult['subscription_link'] ?? $originalConfig;
            $details = $panelResult['details'] ?? [];

            // Determine panel username, expiration, traffic
            $panelUsername = null;
            $expiresAt = null;
            $totalTraffic = 0;
            $usedTraffic = 0;
            $subId = null;
            $importMeta = [];

            if ($type === 'xui') {
                $client = $panelResult['client'];
                $panelUsername = $client['email'] ?? 'imported-' . substr($uuid, 0, 8);
                $subId = $client['subId'] ?? ($details['subId'] ?? null);

                // expiryTime is in milliseconds, 0 means unlimited
                $expiryMs = $details['expiryTime'] ?? ($client['expiryTime'] ?? 0);
                if ($expiryMs > 0) {
                    $expiresAt = Carbon::createFromTimestampMs($expiryMs);
                } else {
                    // Default to 1 year from now if unlimited, or keep null? Use 1 year for active subscription
                    $expiresAt = now()->addYear();
                }

                $totalTraffic = $details['totalGB'] ?? ($client['totalGB'] ?? 0);
                $usedTraffic = $details['traffic_used'] ?? 0;

                $importMeta = [
                    'panel_type' => 'xui',
                    'inbound_id' => $details['inbound_id'] ?? null,
                    'inbound_remark' => $details['inbound_remark'] ?? null,
                    'subId' => $subId,
                    'totalGB' => $totalTraffic,
                    'used_traffic' => $usedTraffic,
                    'original_config' => $originalConfig,
                    'parsed_count' => count($parsedVlessUris),
                    'first_vless_uri' => $parsedVlessUris[0]['uri'] ?? null,
                ];
            } else { // marzban
                $marzbanUser = $panelResult['user'] ?? $panelResult['client'];
                $panelUsername = $marzbanUser['username'] ?? 'imported-' . substr($uuid, 0, 8);

                $expireTimestamp = $details['expire'] ?? ($marzbanUser['expire'] ?? 0);
                if ($expireTimestamp > 0) {
                    $expiresAt = Carbon::createFromTimestamp($expireTimestamp);
                } else {
                    $expiresAt = now()->addYear();
                }

                $totalTraffic = $details['data_limit'] ?? ($marzbanUser['data_limit'] ?? 0);
                $usedTraffic = $details['used_traffic'] ?? ($marzbanUser['used_traffic'] ?? 0);

                $importMeta = [
                    'panel_type' => 'marzban',
                    'username' => $panelUsername,
                    'data_limit' => $totalTraffic,
                    'used_traffic' => $usedTraffic,
                    'status' => $details['status'] ?? 'active',
                    'proxies' => $details['proxies'] ?? [],
                    'original_config' => $originalConfig,
                    'parsed_count' => count($parsedVlessUris),
                ];
            }

            // Find matching plan based on traffic limit
            $plan = self::findMatchingPlan($totalTraffic);

            // If original input was subscription URL, config_details should be that URL
            // If VLESS URI, config_details is that VLESS URI
            // But for better UX, use subscription_link from panel (generated) as config
            // We'll store subscription_link as config_details for consistency with normal flow
            // However keep original input in import_meta
            $configDetails = $subscriptionLink ?: $originalConfig;

            // Ensure configDetails is not empty
            if (empty($configDetails)) {
                $configDetails = $originalConfig;
            }

            // Create order - must behave identically to normal orders
            $order = Order::create([
                'user_id' => $user->id,
                'plan_id' => $plan ? $plan->id : null,
                'server_id' => $serverId,
                'status' => 'paid',
                'source' => $source,
                'config_details' => $configDetails,
                'expires_at' => $expiresAt,
                'panel_username' => $panelUsername,
                'panel_client_id' => $uuid,
                'panel_sub_id' => $subId,
                'amount' => $plan ? $plan->price : 0,
                'is_imported' => true,
                'import_meta' => $importMeta,
            ]);

            return $order;
        });
    }

    protected static function findMatchingPlan(int $totalBytes): ?Plan
    {
        if ($totalBytes <= 0) {
            // Unlimited or 0 - return first active plan
            return Plan::where('is_active', true)->orderBy('price')->first();
        }

        // Convert bytes to GB
        $totalGB = (int) ceil($totalBytes / (1024 * 1024 * 1024));

        // Try exact match
        $exact = Plan::where('is_active', true)
            ->where('volume_gb', $totalGB)
            ->first();

        if ($exact) {
            return $exact;
        }

        // Try closest match (within 5GB)
        $closest = Plan::where('is_active', true)
            ->orderByRaw("ABS(volume_gb - {$totalGB})")
            ->first();

        if ($closest && abs($closest->volume_gb - $totalGB) <= 5) {
            return $closest;
        }

        // Fallback to first active
        return Plan::where('is_active', true)->orderBy('price')->first();
    }
}
