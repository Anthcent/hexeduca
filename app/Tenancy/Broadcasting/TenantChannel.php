<?php

namespace App\Tenancy\Broadcasting;

use App\Tenancy\Models\School;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The single sanctioned way every module names and authorizes a
 * school-scoped broadcast channel.
 *
 * Reverb runs as a separate process with no visibility into the HTTP
 * kernel, TenantScope, or TenantContext, so isolation is enforced entirely
 * here, inside the Broadcast::channel() authorization closure — never via
 * middleware, and never against TenantContext (see authorize()).
 */
final class TenantChannel
{
    /**
     * Tenant segment prefix shared by every school-scoped channel name.
     */
    public const PREFIX = 'school';

    /**
     * Channels allowed to be non-tenant-scoped (per-user, not per-tenant).
     *
     * @var list<string>
     */
    public const ALLOWED_UNSCOPED = ['App.Models.User.{id}'];

    /**
     * Build the concrete wire base name: "school.7.grades.42" (no
     * "private-" prefix — Laravel applies that implicitly).
     */
    public static function name(int|School $school, string $resource, int|string $id): string
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        return self::PREFIX.'.'.$schoolId.'.'.$resource.'.'.$id;
    }

    /**
     * Build the registration pattern for Broadcast::channel(), e.g.
     * "school.{schoolId}.grades.{id}".
     */
    public static function pattern(string $resource): string
    {
        return self::PREFIX.'.{schoolId}.'.$resource.'.{id}';
    }

    /**
     * The ONLY sanctioned authorization predicate.
     *
     * MUST return false immediately when $user->school_id === null (explicit
     * null check, no cast) — never rely on `(int) null === (int) $schoolId`,
     * since PHP casts both null and non-numeric strings to 0. MUST also
     * reject $schoolId that is not a well-formed positive integer (via
     * ctype_digit((string) $schoolId)) before comparing, so malformed route
     * segments like "abc" cannot coerce to 0 and collide with a null
     * school_id.
     *
     * Deliberately never reads TenantContext: it is request-scoped and
     * absent for broadcasts emitted from queued jobs or console commands,
     * while $user->school_id is valid in every execution context.
     */
    public static function authorize(Authenticatable $user, int|string $schoolId): bool
    {
        $userSchoolId = $user->school_id ?? null;

        if ($userSchoolId === null) {
            return false;
        }

        if (! ctype_digit((string) $schoolId)) {
            return false;
        }

        return (int) $userSchoolId === (int) $schoolId;
    }

    /**
     * True if a channel name is tenant-scoped (school.{id}... prefixed) or
     * explicitly exempt via ALLOWED_UNSCOPED (discipline test).
     */
    public static function isCompliant(string $channelName): bool
    {
        if (in_array($channelName, self::ALLOWED_UNSCOPED, true)) {
            return true;
        }

        return (bool) preg_match('/^'.preg_quote(self::PREFIX, '/').'\.\d+\./', $channelName)
            || (bool) preg_match('/^'.preg_quote(self::PREFIX, '/').'\.\{schoolId\}\./', $channelName);
    }
}
