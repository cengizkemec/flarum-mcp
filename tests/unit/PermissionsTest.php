<?php

namespace CengizKemec\FlarumMcp\Tests\unit;

use CengizKemec\FlarumMcp\Permission\Permissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    #[Test]
    public function every_profile_only_contains_known_permissions(): void
    {
        foreach (Permissions::builtInProfiles() as $profile) {
            foreach ($profile['permissions'] as $permission) {
                $this->assertTrue(Permissions::exists($permission), $permission);
            }
        }
    }

    #[Test]
    public function profiles_grow_from_minimum_to_full(): void
    {
        $minimum = Permissions::builtInProfiles()[Permissions::PROFILE_MINIMUM]['permissions'];
        $standard = Permissions::builtInProfiles()[Permissions::PROFILE_STANDARD]['permissions'];
        $advanced = Permissions::builtInProfiles()[Permissions::PROFILE_ADVANCED]['permissions'];
        $full = Permissions::builtInProfiles()[Permissions::PROFILE_FULL]['permissions'];

        $this->assertEmpty(array_diff($minimum, $standard));
        $this->assertEmpty(array_diff($standard, $advanced));
        $this->assertEmpty(array_diff($advanced, $full));
        $this->assertContains('discussions.create', $standard);
        $this->assertContains('discussions.delete', $advanced);
        $this->assertNotContains('discussions.delete', $standard);
    }

    #[Test]
    public function administrative_only_permissions_are_not_in_full_access(): void
    {
        $full = Permissions::builtInProfiles()[Permissions::PROFILE_FULL]['permissions'];

        foreach (Permissions::ids() as $permission) {
            if (Permissions::isAdminOnly($permission)) {
                $this->assertNotContains($permission, $full);
            }
        }
    }

    #[Test]
    public function destructive_permissions_require_approval_when_enabled(): void
    {
        $this->assertTrue(Permissions::requiresApproval('discussions.delete'));
        $this->assertTrue(Permissions::requiresApproval('users.suspend'));
        $this->assertFalse(Permissions::requiresApproval('discussions.read'));
    }
}
