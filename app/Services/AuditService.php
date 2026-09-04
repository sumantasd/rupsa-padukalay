<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AuditService
{
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'secret',
        'remember_token',
        'cvv',
        'card_number',
        'pin',
    ];

    public function getAuthorizedStoreIds(User $user, ?int $requestedStoreId = null): ?array
    {
        if ($user->roles()->where('name', 'Super Admin')->exists()) {
            return $requestedStoreId ? [$requestedStoreId] : null;
        }

        $userStoreIds = $user->stores()->pluck('stores.id')->toArray();

        if ($requestedStoreId !== null) {
            if (! in_array($requestedStoreId, $userStoreIds)) {
                throw new \RuntimeException('Forbidden: You are not authorized to view audit logs for this store.', 403);
            }

            return [$requestedStoreId];
        }

        return $userStoreIds;
    }

    public function redactSensitiveData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $redacted = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, $this->sensitiveKeys)) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactSensitiveData($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    public function calculateChangedFields(?array $before, ?array $after): ?array
    {
        if (! $before || ! $after) {
            return null;
        }

        $diff = [];
        foreach ($after as $key => $val) {
            if (! array_key_exists($key, $before) || $before[$key] !== $val) {
                $diff[$key] = [
                    'old' => $before[$key] ?? null,
                    'new' => $val,
                ];
            }
        }

        return count($diff) > 0 ? $diff : null;
    }

    public function logEvent(array $params): AuditLog
    {
        $clientUuid = $params['client_trans_uuid'] ?? null;
        $eventType = $params['event_type'] ?? 'general_event';

        // Idempotency Protection for retried offline/client transactions
        if ($clientUuid) {
            $existing = AuditLog::where('client_trans_uuid', $clientUuid)
                ->where('event_type', $eventType)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $beforeState = isset($params['before_state']) ? $this->redactSensitiveData((array) $params['before_state']) : null;
        $afterState = isset($params['after_state']) ? $this->redactSensitiveData((array) $params['after_state']) : null;
        $changedFields = $params['changed_fields'] ?? $this->calculateChangedFields($beforeState, $afterState);

        return AuditLog::create([
            'audit_uuid' => (string) Str::uuid(),
            'user_id' => $params['user_id'] ?? request()?->user()?->id,
            'store_id' => $params['store_id'] ?? null,
            'pos_session_id' => $params['pos_session_id'] ?? null,
            'pos_register_id' => $params['pos_register_id'] ?? null,
            'module' => $params['module'] ?? 'general',
            'event_type' => $eventType,
            'auditable_type' => $params['auditable_type'] ?? null,
            'auditable_id' => $params['auditable_id'] ?? null,
            'client_trans_uuid' => $clientUuid,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'changed_fields' => $changedFields,
            'status' => $params['status'] ?? 'success',
            'ip_address' => $params['ip_address'] ?? request()?->ip(),
            'user_agent' => $params['user_agent'] ?? request()?->header('User-Agent'),
            'reason_notes' => $params['reason_notes'] ?? null,
            'created_at' => now(),
        ]);
    }

    public function buildBaseQuery(array $filters, User $user)
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $query = AuditLog::with(['user', 'store']);

        if ($authorizedStoreIds !== null) {
            $query->whereIn('store_id', $authorizedStoreIds);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['module'])) {
            $query->where('module', trim((string) $filters['module']));
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', trim((string) $filters['event_type']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', trim((string) $filters['status']));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('audit_uuid', 'LIKE', "%{$search}%")
                    ->orWhere('client_trans_uuid', 'LIKE', "%{$search}%")
                    ->orWhere('reason_notes', 'LIKE', "%{$search}%")
                    ->orWhere('auditable_type', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'desc');
    }

    public function getAuditLogs(array $filters, User $user): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->buildBaseQuery($filters, $user)->paginate($perPage);
    }

    public function getAuditLogDetails(int $id, User $user): AuditLog
    {
        $log = AuditLog::with(['user', 'store', 'posSession', 'posRegister'])->find($id);

        if (! $log) {
            throw new \RuntimeException('Audit log entry not found.', 404);
        }

        if ($log->store_id) {
            $this->getAuthorizedStoreIds($user, $log->store_id);
        }

        return $log;
    }

    public function getFinancialAuditTrail(array $filters, User $user): LengthAwarePaginator
    {
        $financialModules = ['pos_sale', 'sales_return', 'exchange', 'expense', 'store_credit', 'pos_drawer', 'loyalty'];
        $query = $this->buildBaseQuery($filters, $user)->whereIn('module', $financialModules);

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    public function getEntityAuditHistory(string $entityType, int $entityId, User $user): LengthAwarePaginator
    {
        $query = AuditLog::with(['user', 'store'])
            ->where('auditable_type', 'LIKE', "%{$entityType}%")
            ->where('auditable_id', $entityId)
            ->orderBy('id', 'desc');

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
            $query->where(function ($q) use ($userStoreIds) {
                $q->whereIn('store_id', $userStoreIds)->orWhereNull('store_id');
            });
        }

        return $query->paginate(15);
    }

    public function getUserActivityTrail(int $userId, array $filters, User $user): LengthAwarePaginator
    {
        $filters['user_id'] = $userId;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->buildBaseQuery($filters, $user)->paginate($perPage);
    }

    public function getStoreAuditHistory(int $storeId, array $filters, User $user): LengthAwarePaginator
    {
        $this->getAuthorizedStoreIds($user, $storeId);
        $filters['store_id'] = $storeId;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->buildBaseQuery($filters, $user)->paginate($perPage);
    }
}
