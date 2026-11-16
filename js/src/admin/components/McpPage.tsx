import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Alert from 'flarum/common/components/Alert';
import Button from 'flarum/common/components/Button';
import Form from 'flarum/common/components/Form';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type { Approval, AuditLog, Connection, Integration, Overview, PermissionDefinition, Profile, Prompt, Resource, Settings, Skill, Tool } from '../types';

const TABS = [
    'overview',
    'connections',
    'profiles',
    'tools',
    'skills',
    'resources',
    'prompts',
    'extensions',
    'approvals',
    'audit',
    'settings',
] as const;

type Tab = (typeof TABS)[number];

export default class McpPage extends ExtensionPage {
    activeTab: Tab = 'overview';
    loading = false;
    error: string | null = null;
    token: string | null = null;
    preview: { title: string; content: string } | null = null;
    data: any = {};
    form: any = {};
    settingsForm: Partial<Settings> = {};
    auditFilters: any = {};

    oninit(vnode: Mithril.Vnode<any, this>): void {
        super.oninit(vnode);

        this.form = {
            connection: {},
            profile: { permissions: [] },
            prompt: { arguments: [] },
            skillUrl: '',
            skillFile: null,
        };
        this.auditFilters = { status: '', toolName: '', connectionId: '' };

        this.load('overview');
    }

    className(): string {
        return super.className() + ' McpPage';
    }

    content(vnode: Mithril.VnodeDOM<any, this>): any {
        return (
            <div className="McpPage-inner">
                {this.token && (
                    <Alert type="success" dismissible={true} ondismiss={() => (this.token = null)}>
                        {this.t('messages.token_once')}
                        <pre className="McpToken">{this.token}</pre>
                    </Alert>
                )}

                {this.preview && (
                    <div className="McpPreview">
                        <div className="McpPreview-header">
                            <strong>{this.preview.title}</strong>
                            <Button className="Button Button--link" onclick={() => (this.preview = null)}>
                                {this.t('common.close')}
                            </Button>
                        </div>
                        <pre>{this.preview.content}</pre>
                    </div>
                )}

                <nav className="McpTabs">
                    {TABS.map((tab) => (
                        <button
                            type="button"
                            className={'McpTab' + (this.activeTab === tab ? ' active' : '')}
                            onclick={() => this.load(tab)}
                        >
                            {this.t('tabs.' + tab)}
                        </button>
                    ))}
                </nav>

                {this.error && <Alert type="error">{this.error}</Alert>}

                {this.loading ? (
                    <LoadingIndicator />
                ) : (
                    <div className="McpTabContent">
                        {this.activeTab === 'overview' && this.renderOverview()}
                        {this.activeTab === 'connections' && this.renderConnections()}
                        {this.activeTab === 'profiles' && this.renderProfiles()}
                        {this.activeTab === 'tools' && this.renderTools()}
                        {this.activeTab === 'skills' && this.renderSkills()}
                        {this.activeTab === 'resources' && this.renderResources()}
                        {this.activeTab === 'prompts' && this.renderPrompts()}
                        {this.activeTab === 'extensions' && this.renderIntegrations()}
                        {this.activeTab === 'approvals' && this.renderApprovals()}
                        {this.activeTab === 'audit' && this.renderAudit()}
                        {this.activeTab === 'settings' && this.renderSettings()}
                    </div>
                )}
            </div>
        );
    }

    private t(key: string, params: any = {}): any {
        return app.translator.trans('cengizkemec-mcp.admin.' + key, params);
    }

    private ts(key: string, params: any = {}): string {
        return extractText(this.t(key, params));
    }

    private profileLabel(key: string): string {
        const profile = (this.data.profiles?.profiles || []).find((item: Profile) => item.key === key);

        if (!profile) {
            return key;
        }

        return profile.name || this.ts((profile.name_key || '').replace('cengizkemec-mcp.admin.', '')) || key;
    }

    private apiUrl(path: string): string {
        return app.forum.attribute<string>('apiUrl') + path;
    }

    private request<T = any>(method: string, path: string, body?: any): Promise<T> {
        return app.request<T>({ method, url: this.apiUrl(path), body });
    }

    private async load(tab: Tab): Promise<void> {
        this.activeTab = tab;
        this.loading = true;
        this.error = null;
        m.redraw();

        try {
            switch (tab) {
                case 'overview':
                    this.data.overview = await this.request<Overview>('GET', '/mcp/overview');
                    break;
                case 'connections':
                    this.data.connections = await this.request<any>('GET', '/mcp/connections');
                    this.data.profiles = await this.request<any>('GET', '/mcp/profiles');
                    this.data.settings = await this.request<any>('GET', '/mcp/settings');
                    break;
                case 'profiles':
                    this.data.profiles = await this.request<any>('GET', '/mcp/profiles');
                    break;
                case 'tools':
                    this.data.tools = await this.request<any>('GET', '/mcp/tools');
                    break;
                case 'skills':
                    this.data.skills = await this.request<any>('GET', '/mcp/skills');
                    break;
                case 'resources':
                    this.data.resources = await this.request<any>('GET', '/mcp/resources');
                    break;
                case 'prompts':
                    this.data.prompts = await this.request<any>('GET', '/mcp/prompts');
                    break;
                case 'extensions':
                    this.data.integrations = await this.request<any>('GET', '/mcp/integrations');
                    break;
                case 'approvals':
                    this.data.approvals = await this.request<any>('GET', '/mcp/approvals?status=pending');
                    break;
                case 'audit':
                    this.data.audit = await this.request<any>('GET', '/mcp/audit');
                    break;
                case 'settings':
                    this.data.settings = await this.request<any>('GET', '/mcp/settings');
                    this.data.profiles = await this.request<any>('GET', '/mcp/profiles');
                    this.settingsForm = { ...this.data.settings.settings };
                    break;
            }
        } catch (error: any) {
            this.error = error?.response?.errors?.[0]?.detail || error?.message || this.t('messages.request_failed');
        } finally {
            this.loading = false;
            m.redraw();
        }
    }

    private async act(method: string, path: string, body?: any): Promise<any> {
        try {
            const result = await this.request(method, path, body);

            if (result?.token) {
                this.token = result.token;
            }

            app.alerts.show({ type: 'success' }, this.t('messages.saved'));
            await this.load(this.activeTab);

            return result;
        } catch (error: any) {
            this.error = error?.response?.errors?.[0]?.detail || error?.message || this.t('messages.request_failed');
            m.redraw();

            return null;
        }
    }

    private renderOverview(): Mithril.Children {
        const overview: Overview | undefined = this.data.overview;

        if (!overview) {
            return <Placeholder text={this.t('messages.no_data')} />;
        }

        const stats = [
            ['connections', overview.stats.connections],
            ['active_connections', overview.stats.activeConnections],
            ['requests', overview.stats.requests],
            ['tool_executions', overview.stats.toolExecutions],
            ['denied', overview.stats.denied],
            ['errors', overview.stats.errors],
            ['pending_approvals', overview.stats.pendingApprovals],
            ['installed_skills', overview.stats.installedSkills],
            ['tools', overview.stats.tools],
            ['resources', overview.stats.resources],
            ['prompts', overview.stats.prompts],
        ];

        return (
            <div>
                <div className="McpServerBar">
                    <div>
                        <strong>{overview.server.enabled ? this.t('overview.server_enabled') : this.t('overview.server_disabled')}</strong>
                        <code>{overview.server.endpoint}</code>
                    </div>
                    <div className="McpMuted">
                        Flarum {overview.server.flarumVersion} · PHP {overview.server.phpVersion} · MCP {overview.server.version}
                    </div>
                </div>

                <div className="McpCards">
                    {stats.map(([key, value]) => (
                        <div className="McpCard">
                            <div className="McpCard-value">{value}</div>
                            <div className="McpCard-label">{this.t('overview.' + key)}</div>
                        </div>
                    ))}
                </div>

                <h3>{this.t('overview.recent_activity')}</h3>
                {this.auditTable((overview.recentActivity || []) as AuditLog[])}
            </div>
        );
    }

    private renderConnections(): Mithril.Children {
        const connections: Connection[] = this.data.connections?.connections || [];
        const profiles: Profile[] = this.data.profiles?.profiles || [];
        const form = this.form.connection;

        return (
            <div>
                <div className="McpPanel">
                    <h3>{form.id ? this.t('connections.edit_title') : this.t('connections.create_title')}</h3>
                    <div className="McpFormGrid">
                        <label>
                            {this.t('connections.name')}
                            <input className="FormControl" value={form.name || ''} oninput={(e: any) => (form.name = e.target.value)} />
                        </label>
                        <label>
                            {this.t('connections.user_id')}
                            <input
                                className="FormControl"
                                type="number"
                                min="1"
                                value={form.userId || ''}
                                oninput={(e: any) => (form.userId = e.target.value)}
                            />
                        </label>
                        <label>
                            {this.t('connections.profile')}
                            <select
                                className="FormControl"
                                value={form.permissionProfile || this.data.settings?.settings?.defaultPermissionProfile || 'minimum'}
                                onchange={(e: any) => (form.permissionProfile = e.target.value)}
                            >
                                {profiles.map((profile) => (
                                    <option value={profile.key}>
                                        {profile.name || this.t(profile.name_key?.replace('cengizkemec-mcp.admin.', '') || profile.key)}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            {this.t('connections.expires_in_days')}
                            <input
                                className="FormControl"
                                type="number"
                                min="1"
                                max="3650"
                                value={form.expiresInDays || ''}
                                oninput={(e: any) => (form.expiresInDays = e.target.value)}
                            />
                        </label>
                        {form.id && (
                            <label>
                                {this.t('connections.status')}
                                <select
                                    className="FormControl"
                                    value={form.status || 'active'}
                                    onchange={(e: any) => (form.status = e.target.value)}
                                >
                                    <option value="active">{this.t('connections.status_active')}</option>
                                    <option value="disabled">{this.t('connections.status_disabled')}</option>
                                </select>
                            </label>
                        )}
                        <label>
                            {this.t('connections.rate_limit_minute')}
                            <input
                                className="FormControl"
                                type="number"
                                min="0"
                                value={form.rateLimitPerMinute ?? ''}
                                oninput={(e: any) => (form.rateLimitPerMinute = e.target.value === '' ? undefined : Number(e.target.value))}
                            />
                        </label>
                        <label>
                            {this.t('connections.rate_limit_hour')}
                            <input
                                className="FormControl"
                                type="number"
                                min="0"
                                value={form.rateLimitPerHour ?? ''}
                                oninput={(e: any) => (form.rateLimitPerHour = e.target.value === '' ? undefined : Number(e.target.value))}
                            />
                        </label>
                        <label>
                            {this.t('connections.rate_limit_day')}
                            <input
                                className="FormControl"
                                type="number"
                                min="0"
                                value={form.rateLimitPerDay ?? ''}
                                oninput={(e: any) => (form.rateLimitPerDay = e.target.value === '' ? undefined : Number(e.target.value))}
                            />
                        </label>
                        <label className="McpFull">
                            {this.t('connections.description')}
                            <textarea
                                className="FormControl"
                                value={form.description || ''}
                                oninput={(e: any) => (form.description = e.target.value)}
                            />
                        </label>
                    </div>

                    <div className="McpInline">
                        <Switch state={!!form.requireApproval} onchange={(value: boolean) => (form.requireApproval = value)}>
                            {this.t('connections.require_approval')}
                        </Switch>
                        <Button
                            className="Button Button--primary"
                            loading={this.loading}
                            onclick={async () => {
                                if (form.id) {
                                    await this.act('PATCH', '/mcp/connections/' + form.id, form);
                                } else {
                                    await this.act('POST', '/mcp/connections', form);
                                }

                                this.form.connection = {};
                            }}
                        >
                            {form.id ? this.t('common.save') : this.t('common.create')}
                        </Button>
                        {form.id && (
                            <Button className="Button" onclick={() => (this.form.connection = {})}>
                                {this.t('common.cancel')}
                            </Button>
                        )}
                    </div>
                </div>

                <h3>{this.t('connections.list_title')}</h3>
                <div className="McpTableWrap">
                    <table className="McpTable">
                        <thead>
                            <tr>
                                <th>{this.t('connections.name')}</th>
                                <th>{this.t('connections.user')}</th>
                                <th>{this.t('connections.profile')}</th>
                                <th>{this.t('connections.token')}</th>
                                <th>{this.t('connections.status')}</th>
                                <th>{this.t('connections.last_used')}</th>
                                <th>{this.t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {connections.map((connection) => (
                                <tr>
                                    <td>{connection.name}</td>
                                    <td>{connection.username || connection.userId}</td>
                                    <td>{this.profileLabel(connection.permissionProfile)}</td>
                                    <td>
                                        <code>{connection.tokenPrefix}…</code>
                                    </td>
                                    <td>{this.t('connections.status_' + connection.status)}</td>
                                    <td>{connection.lastUsedAt ? new Date(connection.lastUsedAt).toLocaleString() : '—'}</td>
                                    <td className="McpActions">
                                        <Button
                                            className="Button Button--link"
                                            onclick={() => {
                                                this.form.connection = {
                                                    id: connection.id,
                                                    name: connection.name,
                                                    description: connection.description,
                                                    userId: connection.userId,
                                                    permissionProfile: connection.permissionProfile,
                                                    expiresInDays: connection.tokenExpiresAt
                                                        ? Math.max(1, Math.ceil((new Date(connection.tokenExpiresAt).getTime() - Date.now()) / 86400000))
                                                        : '',
                                                    requireApproval: connection.requireApproval,
                                                    status: connection.status,
                                                    rateLimitPerMinute: connection.rateLimitPerMinute,
                                                    rateLimitPerHour: connection.rateLimitPerHour,
                                                    rateLimitPerDay: connection.rateLimitPerDay,
                                                };
                                                m.redraw();
                                            }}
                                        >
                                            {this.t('common.edit')}
                                        </Button>
                                        <Button
                                            className="Button Button--link"
                                            onclick={() => this.act('POST', '/mcp/connections/' + connection.id + '/rotate', {})}
                                        >
                                            {this.t('connections.rotate')}
                                        </Button>
                                        <Button
                                            className="Button Button--link"
                                            onclick={() => this.act('POST', '/mcp/connections/' + connection.id + '/revoke', {})}
                                        >
                                            {this.t('connections.revoke')}
                                        </Button>
                                        <Button
                                            className="Button Button--link"
                                            onclick={() => confirm(this.ts('connections.confirm_delete')) && this.act('DELETE', '/mcp/connections/' + connection.id)}
                                        >
                                            {this.t('common.delete')}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    }

    private renderProfiles(): Mithril.Children {
        const profiles: Profile[] = this.data.profiles?.profiles || [];
        const permissions: PermissionDefinition[] = this.data.profiles?.permissions || [];
        const form = this.form.profile;

        return (
            <div>
                <div className="McpPanel">
                    <h3>{form.id ? this.t('profiles.edit_title') : this.t('profiles.create_title')}</h3>
                    <div className="McpFormGrid">
                        <label>
                            {this.t('profiles.name')}
                            <input className="FormControl" value={form.name || ''} oninput={(e: any) => (form.name = e.target.value)} />
                        </label>
                        <label className="McpFull">
                            {this.t('profiles.description')}
                            <textarea
                                className="FormControl"
                                value={form.description || ''}
                                oninput={(e: any) => (form.description = e.target.value)}
                            />
                        </label>
                    </div>

                    <div className="McpPermissions">
                        {permissions.map((permission) => (
                            <label className="McpPermission">
                                <input
                                    type="checkbox"
                                    checked={(form.permissions || []).includes(permission.id)}
                                    onchange={(e: any) => {
                                        const selected = new Set<string>(form.permissions || []);
                                        if (e.target.checked) selected.add(permission.id);
                                        else selected.delete(permission.id);
                                        form.permissions = Array.from(selected);
                                    }}
                                />
                                <span>{this.t((permission.labelKey || '').replace('cengizkemec-mcp.admin.', '')) || permission.label}</span>
                                <em>{permission.risk}</em>
                            </label>
                        ))}
                    </div>

                    <div className="McpInline">
                        <Button
                            className="Button Button--primary"
                            onclick={async () => {
                                if (form.id) await this.act('PATCH', '/mcp/profiles/' + form.id, form);
                                else await this.act('POST', '/mcp/profiles', form);
                                this.form.profile = { permissions: [] };
                            }}
                        >
                            {form.id ? this.t('common.save') : this.t('common.create')}
                        </Button>
                        {form.id && (
                            <Button className="Button" onclick={() => (this.form.profile = { permissions: [] })}>
                                {this.t('common.cancel')}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="McpTableWrap">
                    <table className="McpTable">
                        <thead>
                            <tr>
                                <th>{this.t('profiles.name')}</th>
                                <th>{this.t('profiles.permissions')}</th>
                                <th>{this.t('profiles.type')}</th>
                                <th>{this.t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {profiles.map((profile) => (
                                <tr>
                                    <td>{profile.name || this.t((profile.name_key || '').replace('cengizkemec-mcp.admin.', ''))}</td>
                                    <td>
                                        <span className="McpBadge">{profile.permissions.length}</span>
                                    </td>
                                    <td>{profile.builtin ? this.t('profiles.builtin') : this.t('profiles.custom')}</td>
                                    <td className="McpActions">
                                        <Button className="Button Button--link" onclick={() => this.act('POST', '/mcp/profiles/' + profile.key + '/duplicate')}>
                                            {this.t('profiles.duplicate')}
                                        </Button>
                                        <Button className="Button Button--link" onclick={() => this.exportProfile(profile.key)}>
                                            {this.t('profiles.export')}
                                        </Button>
                                        {!profile.builtin && (
                                            <>
                                                <Button
                                                    className="Button Button--link"
                                                    onclick={() => {
                                                        this.form.profile = {
                                                            id: profile.id,
                                                            name: profile.name,
                                                            description: profile.description,
                                                            permissions: [...profile.permissions],
                                                        };
                                                        m.redraw();
                                                    }}
                                                >
                                                    {this.t('common.edit')}
                                                </Button>
                                                <Button
                                                    className="Button Button--link"
                                                    onclick={() => confirm(this.ts('profiles.confirm_delete')) && this.act('DELETE', '/mcp/profiles/' + profile.id)}
                                                >
                                                    {this.t('common.delete')}
                                                </Button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    }

    private renderTools(): Mithril.Children {
        const tools: Tool[] = this.data.tools?.tools || [];

        return (
            <div className="McpTableWrap">
                <table className="McpTable">
                    <thead>
                        <tr>
                            <th>{this.t('tools.name')}</th>
                            <th>{this.t('tools.permission')}</th>
                            <th>{this.t('tools.source')}</th>
                            <th>{this.t('tools.calls')}</th>
                            <th>{this.t('tools.enabled')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {tools.map((tool) => (
                            <tr>
                                <td>
                                    <strong>{tool.name}</strong>
                                    <div className="McpMuted">{tool.description}</div>
                                </td>
                                <td>
                                    <code>{tool.permission}</code>
                                </td>
                                <td>{tool.source}</td>
                                <td>
                                    {tool.stats?.calls || 0} / {tool.stats?.errors || 0}
                                </td>
                                <td>
                                    <Switch
                                        state={tool.enabled}
                                        onchange={(value: boolean) => this.act('PATCH', '/mcp/tools/' + encodeURIComponent(tool.name), { enabled: value })}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    private renderSkills(): Mithril.Children {
        const skills: Skill[] = this.data.skills?.skills || [];

        return (
            <div>
                <div className="McpPanel">
                    <h3>{this.t('skills.install_title')}</h3>
                    <div className="McpInline">
                        <input
                            className="FormControl McpGrow"
                            placeholder={this.ts('skills.url_placeholder')}
                            value={this.form.skillUrl || ''}
                            oninput={(e: any) => (this.form.skillUrl = e.target.value)}
                        />
                        <Button
                            className="Button Button--primary"
                            onclick={() => this.act('POST', '/mcp/skills/install-url', { url: this.form.skillUrl, update: true })}
                        >
                            {this.t('skills.install_url')}
                        </Button>
                    </div>
                    <div className="McpInline">
                        <input
                            type="file"
                            accept=".md,.zip,text/markdown,application/zip"
                            onchange={async (e: any) => {
                                const file = e.target.files?.[0];
                                if (!file) return;
                                await this.installSkillFile(file);
                            }}
                        />
                        <span className="McpMuted">{this.t('skills.upload_help')}</span>
                    </div>
                </div>

                <div className="McpTableWrap">
                    <table className="McpTable">
                        <thead>
                            <tr>
                                <th>{this.t('skills.name')}</th>
                                <th>{this.t('skills.version')}</th>
                                <th>{this.t('skills.source')}</th>
                                <th>{this.t('skills.permission')}</th>
                                <th>{this.t('skills.enabled')}</th>
                                <th>{this.t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {skills.map((skill) => (
                                <tr>
                                    <td>
                                        <strong>{skill.name}</strong>
                                        <div className="McpMuted">{skill.description}</div>
                                    </td>
                                    <td>{skill.version}</td>
                                    <td>{skill.sourceType}</td>
                                    <td>
                                        <code>{skill.requiredPermission || 'skills.read'}</code>
                                    </td>
                                    <td>
                                        <Switch
                                            state={skill.enabled}
                                            onchange={(value: boolean) => this.act('POST', '/mcp/skills/' + encodeURIComponent(String(skill.id)) + '/enable', { enabled: value })}
                                        />
                                    </td>
                                    <td className="McpActions">
                                        <Button className="Button Button--link" onclick={() => this.previewSkill(skill)}>
                                            {this.t('skills.preview')}
                                        </Button>
                                        <Button className="Button Button--link" onclick={() => this.exportSkill(skill)}>
                                            {this.t('skills.export')}
                                        </Button>
                                        {!skill.isBuiltin && (
                                            <Button
                                                className="Button Button--link"
                                                onclick={() => confirm(this.ts('skills.confirm_delete')) && this.act('DELETE', '/mcp/skills/' + skill.id)}
                                            >
                                                {this.t('common.delete')}
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    }

    private renderResources(): Mithril.Children {
        const resources: Resource[] = this.data.resources?.resources || [];

        return (
            <div className="McpTableWrap">
                <table className="McpTable">
                    <thead>
                        <tr>
                            <th>{this.t('resources.uri')}</th>
                            <th>{this.t('resources.permission')}</th>
                            <th>{this.t('resources.type')}</th>
                            <th>{this.t('resources.enabled')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {resources.map((resource) => (
                            <tr>
                                <td>
                                    <code>{resource.uri}</code>
                                    <div className="McpMuted">{resource.description}</div>
                                </td>
                                <td>{resource.permission}</td>
                                <td>{resource.type}</td>
                                <td>
                                    <Switch
                                        state={resource.enabled}
                                        onchange={(value: boolean) => this.act('PATCH', '/mcp/resources', { uri: resource.uri, enabled: value })}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    private renderPrompts(): Mithril.Children {
        const prompts: Prompt[] = this.data.prompts?.prompts || [];
        const permissionIds: string[] = this.data.prompts?.permissions || [];
        const form = this.form.prompt;

        return (
            <div>
                <div className="McpPanel">
                    <h3>{this.t('prompts.create_title')}</h3>
                    <div className="McpFormGrid">
                        <label>
                            {this.t('prompts.name')}
                            <input className="FormControl" value={form.name || ''} oninput={(e: any) => (form.name = e.target.value)} />
                        </label>
                        <label>
                            {this.t('prompts.permission')}
                            <select className="FormControl" value={form.requiredPermission || 'prompts.read'} onchange={(e: any) => (form.requiredPermission = e.target.value)}>
                                {permissionIds.map((id) => (
                                    <option value={id}>{id}</option>
                                ))}
                            </select>
                        </label>
                        <label className="McpFull">
                            {this.t('prompts.description')}
                            <input className="FormControl" value={form.description || ''} oninput={(e: any) => (form.description = e.target.value)} />
                        </label>
                        <label className="McpFull">
                            {this.t('prompts.content')}
                            <textarea className="FormControl" rows={6} value={form.content || ''} oninput={(e: any) => (form.content = e.target.value)} />
                        </label>
                    </div>
                    <Button className="Button Button--primary" onclick={() => this.act('POST', '/mcp/prompts', form)}>
                        {this.t('common.create')}
                    </Button>
                </div>

                <div className="McpTableWrap">
                    <table className="McpTable">
                        <thead>
                            <tr>
                                <th>{this.t('prompts.name')}</th>
                                <th>{this.t('prompts.description')}</th>
                                <th>{this.t('prompts.permission')}</th>
                                <th>{this.t('prompts.source')}</th>
                                <th>{this.t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {prompts.map((prompt) => (
                                <tr>
                                    <td>
                                        <strong>{prompt.name}</strong>
                                    </td>
                                    <td>{prompt.description}</td>
                                    <td>{prompt.permission}</td>
                                    <td>{prompt.builtin ? this.t('prompts.builtin') : this.t('prompts.custom')}</td>
                                    <td className="McpActions">
                                        {!prompt.builtin && (
                                            <Button
                                                className="Button Button--link"
                                                onclick={() => confirm(this.ts('prompts.confirm_delete')) && this.act('DELETE', '/mcp/prompts/' + (prompt as any).id)}
                                            >
                                                {this.t('common.delete')}
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    }

    private renderIntegrations(): Mithril.Children {
        const integrations: Integration[] = this.data.integrations?.integrations || [];

        return (
            <div className="McpTableWrap">
                <table className="McpTable">
                    <thead>
                        <tr>
                            <th>{this.t('integrations.extension')}</th>
                            <th>{this.t('integrations.capabilities')}</th>
                            <th>{this.t('integrations.status')}</th>
                            <th>{this.t('common.actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {integrations.map((integration) => (
                            <tr>
                                <td>
                                    <code>{integration.extensionId}</code>
                                </td>
                                <td>
                                    {integration.tools.length} tools · {integration.resources.length} resources · {integration.prompts.length} prompts
                                </td>
                                <td>{integration.status}</td>
                                <td className="McpActions">
                                    {integration.extensionId !== 'cengizkemec-mcp' && (
                                        <>
                                            <Button
                                                className="Button Button--link"
                                                onclick={() => this.act('PATCH', '/mcp/integrations/' + encodeURIComponent(integration.extensionId), { status: 'approved' })}
                                            >
                                                {this.t('integrations.approve')}
                                            </Button>
                                            <Button
                                                className="Button Button--link"
                                                onclick={() => this.act('PATCH', '/mcp/integrations/' + encodeURIComponent(integration.extensionId), { status: 'disabled' })}
                                            >
                                                {this.t('integrations.disable')}
                                            </Button>
                                        </>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    private renderApprovals(): Mithril.Children {
        const approvals: Approval[] = this.data.approvals?.approvals || [];

        if (!approvals.length) {
            return <Placeholder text={this.t('approvals.empty')} />;
        }

        return (
            <div>
                {approvals.map((approval) => (
                    <div className="McpApproval">
                        <div className="McpApproval-header">
                            <strong>{approval.toolName}</strong>
                            <span className="McpBadge">{approval.connectionName}</span>
                            <span className="McpMuted">{approval.requestedAt ? new Date(approval.requestedAt).toLocaleString() : ''}</span>
                        </div>
                        <div className="McpMuted">{approval.permission}</div>
                        <pre>{JSON.stringify(approval.arguments, null, 2)}</pre>
                        <div className="McpInline">
                            <input className="FormControl McpGrow" placeholder={this.ts('approvals.note')} oninput={(e: any) => (approval.decisionNote = e.target.value)} />
                            <Button
                                className="Button Button--primary"
                                onclick={() => this.act('POST', '/mcp/approvals/' + approval.id + '/approve', { note: approval.decisionNote })}
                            >
                                {this.t('approvals.approve')}
                            </Button>
                            <Button
                                className="Button"
                                onclick={() => this.act('POST', '/mcp/approvals/' + approval.id + '/reject', { note: approval.decisionNote })}
                            >
                                {this.t('approvals.reject')}
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    private renderAudit(): Mithril.Children {
        const logs: AuditLog[] = this.data.audit?.logs || [];

        return (
            <div>
                <div className="McpInline McpFilters">
                    <input
                        className="FormControl"
                        placeholder={this.ts('audit.tool')}
                        value={this.auditFilters.toolName}
                        oninput={(e: any) => (this.auditFilters.toolName = e.target.value)}
                    />
                    <input
                        className="FormControl"
                        placeholder={this.ts('audit.status')}
                        value={this.auditFilters.status}
                        oninput={(e: any) => (this.auditFilters.status = e.target.value)}
                    />
                    <input
                        className="FormControl"
                        type="number"
                        placeholder={this.ts('audit.connection_id')}
                        value={this.auditFilters.connectionId}
                        oninput={(e: any) => (this.auditFilters.connectionId = e.target.value)}
                    />
                    <Button className="Button" onclick={() => this.loadAudit()}>
                        {this.t('audit.filter')}
                    </Button>
                </div>
                {this.auditTable(logs)}
            </div>
        );
    }

    private renderSettings(): Mithril.Children {
        const settings: Settings | undefined = this.data.settings?.settings;

        if (!settings) {
            return <Placeholder text={this.t('messages.no_data')} />;
        }

        const form = this.settingsForm;
        const profiles: Profile[] = this.data.profiles?.profiles || [];
        const rateLimits = form.defaultRateLimits || { minute: 60, hour: 1000, day: 10000 };

        return (
            <Form className="McpSettings">
                <div className="McpServerBar">
                    <div>
                        <strong>{this.t('settings.endpoint')}</strong>
                        <code>{settings.endpoint}</code>
                    </div>
                </div>

                <label className="McpSetting">
                    <span>{this.t('settings.enabled')}</span>
                    <Switch state={!!form.enabled} onchange={(value: boolean) => (form.enabled = value)} />
                </label>

                <label className="McpSetting">
                    <span>{this.t('settings.default_permission_profile')}</span>
                    <select
                        className="FormControl"
                        value={form.defaultPermissionProfile || 'minimum'}
                        onchange={(e: any) => (form.defaultPermissionProfile = e.target.value)}
                    >
                        {profiles.map((profile) => (
                            <option value={profile.key}>
                                {profile.name || this.t((profile.name_key || '').replace('cengizkemec-mcp.admin.', ''))}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="McpSetting">
                    <span>{this.t('settings.max_request_bytes')}</span>
                    <input
                        className="FormControl"
                        type="number"
                        value={form.maxRequestBytes || ''}
                        oninput={(e: any) => (form.maxRequestBytes = Number(e.target.value))}
                    />
                </label>

                <label className="McpSetting">
                    <span>{this.t('settings.session_ttl')}</span>
                    <input
                        className="FormControl"
                        type="number"
                        value={form.sessionTtl || ''}
                        oninput={(e: any) => (form.sessionTtl = Number(e.target.value))}
                    />
                </label>

                <label className="McpSetting">
                    <span>{this.t('settings.approval_ttl_hours')}</span>
                    <input
                        className="FormControl"
                        type="number"
                        value={form.approvalTtlHours || ''}
                        oninput={(e: any) => (form.approvalTtlHours = Number(e.target.value))}
                    />
                </label>

                <label className="McpSetting">
                    <span>{this.t('settings.audit_retention_days')}</span>
                    <input
                        className="FormControl"
                        type="number"
                        value={form.auditRetentionDays || ''}
                        oninput={(e: any) => (form.auditRetentionDays = Number(e.target.value))}
                    />
                </label>

                <div className="McpSetting">
                    <span>{this.t('settings.default_rate_limits')}</span>
                    <div className="McpFormGrid">
                        <label>
                            {this.t('settings.rate_limit_minute')}
                            <input
                                className="FormControl"
                                type="number"
                                min="0"
                                value={rateLimits.minute}
                                oninput={(e: any) => (form.defaultRateLimits = { ...rateLimits, minute: Number(e.target.value) })}
                            />
                        </label>
                        <label>
                            {this.t('settings.rate_limit_hour')}
                            <input
                                className="FormControl"
                                type="number"
                                min="0"
                                value={rateLimits.hour}
                                oninput={(e: any) => (form.defaultRateLimits = { ...rateLimits, hour: Number(e.target.value) })}
                            />
                        </label>
                        <label>
                            {this.t('settings.rate_limit_day')}
                            <input
                                className="FormControl"
                                type="number"
                                min="0"
                                value={rateLimits.day}
                                oninput={(e: any) => (form.defaultRateLimits = { ...rateLimits, day: Number(e.target.value) })}
                            />
                        </label>
                    </div>
                </div>

                <label className="McpSetting">
                    <span>{this.t('settings.allowed_hosts')}</span>
                    <input
                        className="FormControl"
                        value={(form.allowedHosts || []).join(', ')}
                        oninput={(e: any) => (form.allowedHosts = e.target.value.split(',').map((v: string) => v.trim()).filter(Boolean))}
                    />
                </label>

                <label className="McpSetting">
                    <span>{this.t('settings.allowed_origins')}</span>
                    <input
                        className="FormControl"
                        value={(form.allowedOrigins || []).join(', ')}
                        oninput={(e: any) => (form.allowedOrigins = e.target.value.split(',').map((v: string) => v.trim()).filter(Boolean))}
                    />
                </label>

                <Button
                    className="Button Button--primary"
                    onclick={() => {
                        const payload: any = {
                            ...form,
                            defaultRateLimitPerMinute: rateLimits.minute,
                            defaultRateLimitPerHour: rateLimits.hour,
                            defaultRateLimitPerDay: rateLimits.day,
                        };
                        delete payload.defaultRateLimits;
                        this.act('PATCH', '/mcp/settings', payload);
                    }}
                >
                    {this.t('common.save')}
                </Button>
            </Form>
        );
    }

    private auditTable(logs: AuditLog[]): Mithril.Children {
        if (!logs.length) {
            return <Placeholder text={this.t('audit.empty')} />;
        }

        return (
            <div className="McpTableWrap">
                <table className="McpTable">
                    <thead>
                        <tr>
                            <th>{this.t('audit.time')}</th>
                            <th>{this.t('audit.connection')}</th>
                            <th>{this.t('audit.user')}</th>
                            <th>{this.t('audit.operation')}</th>
                            <th>{this.t('audit.permission')}</th>
                            <th>{this.t('audit.status')}</th>
                            <th>{this.t('audit.duration')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {logs.map((log) => (
                            <tr>
                                <td>{log.createdAt ? new Date(log.createdAt).toLocaleString() : '—'}</td>
                                <td>{log.connectionName || log.connectionId || '—'}</td>
                                <td>{log.username || log.userId || '—'}</td>
                                <td>{log.toolName || log.operation}</td>
                                <td>{log.permission || '—'}</td>
                                <td>
                                    {log.status}
                                    {log.errorCode ? <div className="McpMuted">{log.errorCode}</div> : null}
                                </td>
                                <td>{log.durationMs !== null ? log.durationMs + ' ms' : '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    private async loadAudit(): Promise<void> {
        const params = new URLSearchParams();
        Object.entries(this.auditFilters).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) params.set(key, String(value));
        });

        this.loading = true;
        m.redraw();

        try {
            this.data.audit = await this.request('GET', '/mcp/audit?' + params.toString());
        } catch (error: any) {
            this.error = error?.response?.errors?.[0]?.detail || error?.message || this.t('messages.request_failed');
        } finally {
            this.loading = false;
            m.redraw();
        }
    }

    private async previewSkill(skill: Skill): Promise<void> {
        try {
            const exported = await this.request<any>('GET', '/mcp/skills/' + encodeURIComponent(String(skill.id)) + '/export');
            this.preview = { title: skill.name, content: exported.content };
            m.redraw();
        } catch (error: any) {
            this.error = error?.response?.errors?.[0]?.detail || error?.message || this.t('messages.request_failed');
        }
    }

    private async exportSkill(skill: Skill): Promise<void> {
        try {
            const exported = await this.request<any>('GET', '/mcp/skills/' + encodeURIComponent(String(skill.id)) + '/export');
            this.download(exported.filename, exported.content);
        } catch (error: any) {
            this.error = error?.response?.errors?.[0]?.detail || error?.message || this.t('messages.request_failed');
        }
    }

    private async exportProfile(key: string): Promise<void> {
        try {
            const exported = await this.request<any>('GET', '/mcp/profiles/' + encodeURIComponent(key) + '/export');
            this.download('profile-' + key.replace(':', '-') + '.json', JSON.stringify(exported, null, 2));
        } catch (error: any) {
            this.error = error?.response?.errors?.[0]?.detail || error?.message || this.t('messages.request_failed');
        }
    }

    private async installSkillFile(file: File): Promise<void> {
        const isZip = file.name.toLowerCase().endsWith('.zip');
        const reader = new FileReader();

        reader.onload = async () => {
            const result = String(reader.result || '');

            if (isZip) {
                const base64 = result.split(',')[1] || '';
                await this.act('POST', '/mcp/skills/install-zip', { archive: base64, update: true });
            } else {
                await this.act('POST', '/mcp/skills', { content: result, sourceType: 'manual', update: true });
            }
        };

        if (isZip) reader.readAsDataURL(file);
        else reader.readAsText(file);
    }

    private download(filename: string, content: string): void {
        const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        URL.revokeObjectURL(url);
    }
}
