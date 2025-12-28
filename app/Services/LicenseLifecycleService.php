<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\License;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service responsible for managing the lifecycle of licenses.
 *
 * This service encapsulates all business rules related to changing the
 * state of an existing license, including renewal, suspension, resumption,
 * and cancellation.
 *
 * It is intended to be used by trusted brand systems and deliberately
 * excludes any HTTP or authentication concerns.
 */
class LicenseLifecycleService
{
    /**
     * Change the lifecycle state of a license.
     *
     * This method applies a lifecycle transition to a license in a safe and
     * transactional manner. It ensures brand ownership, validates the
     * requested transition, and persists the updated license state.
     *
     * Supported actions:
     * - renew   (extend expiration date)
     * - suspend (temporarily disable the license)
     * - resume  (re-enable a suspended license)
     * - cancel  (permanently disable the license)
     *
     * @param Brand $brand
     *   The brand performing the lifecycle change.
     *
     * @param License $license
     *   The license whose lifecycle is being modified.
     *
     * @param string $action
     *   The lifecycle action to apply.
     *
     * @param string|null $expiresAt
     *   Optional expiration date, required when renewing a license.
     *
     * @return License
     *   The updated license instance.
     *
     * @throws InvalidArgumentException
     *   If the license does not belong to the brand, the action is invalid,
     *   or the transition is not allowed.
     */
    public function change(
        Brand $brand,
        License $license,
        string $action,
        ?string $expiresAt = null
    ): License {
        return DB::transaction(function () use (
            $brand,
            $license,
            $action,
            $expiresAt
        ) {
            $this->assertBrandOwnership($brand, $license);
            $this->applyTransition($license, $action, $expiresAt);

            $license->save();

            return $license->fresh();
        });
    }

    /**
     * Ensure that the license belongs to the given brand.
     *
     * This guard prevents cross-brand license lifecycle manipulation,
     * enforcing tenant isolation at the domain level.
     *
     * @param Brand $brand
     *   The brand attempting to modify the license.
     *
     * @param License $license
     *   The license being modified.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *   If the license does not belong to the brand.
     */
    protected function assertBrandOwnership(
        Brand $brand,
        License $license
    ): void {
        if ($license->licenseKey->brand_id !== $brand->id) {
            throw new InvalidArgumentException(
                'License does not belong to this brand.'
            );
        }
    }

    /**
     * Apply a lifecycle transition to the license.
     *
     * This method acts as a simple state machine, validating whether
     * the requested transition is allowed and delegating to the
     * appropriate handler.
     *
     * @param License $license
     *   The license being transitioned.
     *
     * @param string $action
     *   The lifecycle action to apply.
     *
     * @param string|null $expiresAt
     *   Optional expiration date for renewal.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *   If the transition is invalid or unsupported.
     */
    protected function applyTransition(
        License $license,
        string $action,
        ?string $expiresAt
    ): void {
        if ($license->status === 'cancelled') {
            throw new InvalidArgumentException(
                'Cancelled licenses cannot be modified.'
            );
        }

        match ($action) {
            'renew' => $this->renew($license, $expiresAt),
            'suspend' => $this->suspend($license),
            'resume' => $this->resume($license),
            'cancel' => $this->cancel($license),
            default => throw new InvalidArgumentException(
                "Unsupported lifecycle action: {$action}"
            ),
        };
    }

    /**
     * Renew a license by extending its expiration date.
     *
     * Renewing a license also ensures the license status is set to `valid`.
     *
     * @param License $license
     *   The license to renew.
     *
     * @param string|null $expiresAt
     *   The new expiration date.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *   If no expiration date is provided.
     */
    protected function renew(
        License $license,
        ?string $expiresAt
    ): void {
        if (! $expiresAt) {
            throw new InvalidArgumentException(
                'Expiration date is required to renew a license.'
            );
        }

        $license->expires_at = $expiresAt;
        $license->status = 'valid';
    }

    /**
     * Suspend a valid license.
     *
     * @param License $license
     *   The license to suspend.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *   If the license is not currently valid.
     */
    protected function suspend(License $license): void
    {
        if ($license->status !== 'valid') {
            throw new InvalidArgumentException(
                'Only valid licenses can be suspended.'
            );
        }

        $license->status = 'suspended';
    }

    /**
     * Resume a suspended license.
     *
     * @param License $license
     *   The license to resume.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *   If the license is not currently suspended.
     */
    protected function resume(License $license): void
    {
        if ($license->status !== 'suspended') {
            throw new InvalidArgumentException(
                'Only suspended licenses can be resumed.'
            );
        }

        $license->status = 'valid';
    }

    /**
     * Cancel a license.
     *
     * Cancellation is a terminal state and cannot be reversed.
     *
     * @param License $license
     *   The license to cancel.
     *
     * @return void
     */
    protected function cancel(License $license): void
    {
        $license->status = 'cancelled';
    }
}
