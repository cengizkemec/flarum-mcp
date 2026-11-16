<?php

namespace CengizKemec\FlarumMcp\Tests\unit;

use CengizKemec\FlarumMcp\Mcp\ToolRegistry;
use CengizKemec\FlarumMcp\Mcp\BuiltInTools;
use CengizKemec\FlarumMcp\Permission\Permissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class LocalizationTest extends TestCase
{
    #[Test]
    public function every_tool_has_english_and_turkish_copy(): void
    {
        $registry = new ToolRegistry();
        BuiltInTools::register($registry);

        $locales = [
            'en' => Yaml::parseFile(__DIR__.'/../../locale/en.yml'),
            'tr' => Yaml::parseFile(__DIR__.'/../../locale/tr.yml'),
        ];

        foreach ($locales as $locale => $data) {
            foreach ($registry->all() as $tool) {
                $this->assertNotNull(
                    $this->get($data, 'cengizkemec-mcp.lib.tool.'.$tool->name.'.description'),
                    "Missing $locale description for {$tool->name}"
                );

                $this->assertNotNull(
                    $this->get($data, 'cengizkemec-mcp.lib.tool.'.$tool->name.'.title'),
                    "Missing $locale title for {$tool->name}"
                );
            }
        }
    }

    #[Test]
    public function every_permission_has_english_and_turkish_copy(): void
    {
        foreach (['en', 'tr'] as $locale) {
            $data = Yaml::parseFile(__DIR__."/../../locale/$locale.yml");

            foreach (Permissions::ids() as $permission) {
                $key = 'cengizkemec-mcp.admin.permissions.'.str_replace('.', '_', $permission);
                $this->assertNotNull($this->get($data, $key), "Missing $locale permission copy for $permission");
            }
        }
    }

    #[Test]
    public function core_admin_copy_exists_in_both_locales(): void
    {
        foreach (['en', 'tr'] as $locale) {
            $data = Yaml::parseFile(__DIR__."/../../locale/$locale.yml");

            foreach ([
                'cengizkemec-mcp.admin.tabs.overview',
                'cengizkemec-mcp.admin.connections.create_title',
                'cengizkemec-mcp.admin.skills.install_title',
                'cengizkemec-mcp.admin.approvals.approve',
                'cengizkemec-mcp.admin.audit.filter',
                'cengizkemec-mcp.lib.error.permission_denied',
                'cengizkemec-mcp.lib.mcp.instructions',
            ] as $key) {
                $this->assertNotNull($this->get($data, $key), "Missing $locale translation $key");
            }
        }
    }

    #[Test]
    public function every_literal_admin_ui_translation_key_exists_in_both_locales(): void
    {
        $source = file_get_contents(__DIR__.'/../../js/src/admin/components/McpPage.tsx');
        $this->assertNotFalse($source);

        preg_match_all("/this\.t[s]?\\('([^']+)'/", $source, $matches);

        $keys = array_values(array_filter(
            array_unique($matches[1]),
            fn (string $key): bool => ! str_ends_with($key, '.') && ! str_ends_with($key, '_')
        ));

        foreach ([
            'tabs.overview', 'tabs.connections', 'tabs.profiles', 'tabs.tools',
            'tabs.skills', 'tabs.resources', 'tabs.prompts', 'tabs.extensions',
            'tabs.approvals', 'tabs.audit', 'tabs.settings',
            'overview.connections', 'overview.active_connections', 'overview.requests',
            'overview.tool_executions', 'overview.denied', 'overview.errors',
            'overview.pending_approvals', 'overview.installed_skills', 'overview.tools',
            'overview.resources', 'overview.prompts',
            'connections.status_active', 'connections.status_disabled',
        ] as $dynamicKey) {
            $keys[] = $dynamicKey;
        }

        foreach (['en', 'tr'] as $locale) {
            $data = Yaml::parseFile(__DIR__."/../../locale/$locale.yml");

            foreach (array_unique($keys) as $key) {
                $this->assertNotNull(
                    $this->get($data, 'cengizkemec-mcp.admin.'.$key),
                    "Missing $locale admin translation $key"
                );
            }
        }
    }

    private function get(array $data, string $path): mixed
    {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
