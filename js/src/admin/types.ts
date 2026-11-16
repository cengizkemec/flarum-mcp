export interface PermissionDefinition {
    id: string;
    group: string;
    groupLabelKey: string;
    labelKey: string | null;
    label: string;
    risk: string;
    extensionId?: string | null;
}

export interface Profile {
    key: string;
    id?: number;
    name?: string | null;
    name_key?: string;
    description?: string | null;
    description_key?: string;
    permissions: string[];
    builtin: boolean;
}

export interface Connection {
    id: number;
    name: string;
    description: string | null;
    userId: number | null;
    username: string | null;
    permissionProfile: string;
    customPermissions: string[] | null;
    tokenPrefix: string;
    tokenExpiresAt: string | null;
    tokenRevokedAt: string | null;
    status: string;
    rateLimitPerMinute: number;
    rateLimitPerHour: number;
    rateLimitPerDay: number;
    requireApproval: boolean;
    lastUsedAt: string | null;
    lastUsedIp: string | null;
    createdAt: string | null;
}

export interface Tool {
    name: string;
    title: string | null;
    description: string;
    permission: string;
    source: string;
    extensionId: string | null;
    isBuiltin: boolean;
    enabled: boolean;
    approved: boolean;
    requiresApproval: boolean;
    inputSchema: unknown;
    outputSchema: unknown;
    stats?: {
        calls: number;
        errors: number;
        lastUsedAt: string | null;
    };
}

export interface Resource {
    uri: string;
    name: string;
    title: string | null;
    description: string;
    permission: string;
    mimeType: string;
    type: string;
    source: string;
    extensionId: string | null;
    isBuiltin: boolean;
    enabled: boolean;
    approved: boolean;
}

export interface Skill {
    id: string | number;
    slug: string;
    name: string;
    description: string | null;
    version: string;
    sourceType: string;
    sourceUrl: string | null;
    checksum: string;
    enabled: boolean;
    isBuiltin: boolean;
    extensionId?: string | null;
    requiredPermission: string | null;
    metadata: Record<string, unknown> | null;
    content?: string;
    createdAt: string | null;
    updatedAt: string | null;
}

export interface Prompt {
    name: string;
    title: string | null;
    description: string;
    permission: string;
    arguments: { name: string; description?: string; required?: boolean }[];
    source: string;
    extensionId: string | null;
    approved: boolean;
    builtin: boolean;
    enabled: boolean;
    content: string | null;
}

export interface Integration {
    extensionId: string;
    status: string;
    approved: boolean;
    tools: string[];
    resources: string[];
    prompts: string[];
    permissions: string[];
}

export interface Approval {
    id: number;
    connectionId: number;
    connectionName: string | null;
    userId: number | null;
    username: string | null;
    toolName: string;
    permission: string;
    arguments: Record<string, unknown>;
    status: string;
    ipAddress: string | null;
    requestedAt: string | null;
    expiresAt: string | null;
    decidedAt: string | null;
    decidedBy: string | null;
    decisionNote: string | null;
    executedAt: string | null;
    error: string | null;
}
export interface AuditLog {
    id: number;
    connectionId: number | null;
    connectionName: string | null;
    userId: number | null;
    username: string | null;
    operation: string;
    toolName: string | null;
    permission: string | null;
    permissionDecision: string;
    status: string;
    errorCode: string | null;
    errorMessage: string | null;
    durationMs: number | null;
    approvalId: number | null;
    ipAddress: string | null;
    requestId: string | null;
    meta: Record<string, unknown> | null;
    createdAt: string | null;
}

export interface Overview {
    server: {
        enabled: boolean;
        endpoint: string;
        version: string;
        flarumVersion: string;
        phpVersion: string;
    };
    stats: Record<string, number>;
    recentActivity: AuditLog[];
    health: Record<string, boolean | number>;
}

export interface Settings {
    enabled: boolean;
    endpoint: string;
    maxRequestBytes: number;
    sessionTtl: number;
    approvalTtlHours: number;
    auditRetentionDays: number;
    defaultPermissionProfile: string;
    defaultRateLimits: { minute: number; hour: number; day: number };
    allowedHosts: string[];
    allowedOrigins: string[];
}
