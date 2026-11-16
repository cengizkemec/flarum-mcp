# Permissions

Flarum MCP uses a layered permission model. A connection can only do what **all
four** layers allow:

1. **Connection profile** — the built-in or custom permission set.
2. **Connection allowlist** — optional custom permissions, intersected with the
   profile.
3. **Tool switch and integration approval** — globally enabled and, for
   third-party capabilities, approved by an administrator.
4. **Flarum authorization** — the assigned user's policies, visibility scopes,
   group permissions and admin status.

A hidden tool is never the only protection: execution checks the same
permissions again.

## Built-in profiles

Built-in profiles are immutable definitions in code. They are not database rows
and cannot be overwritten. Administrators can clone them into custom profiles.

### Minimum Access

Read-only public content:

- forum information and statistics
- search/read discussions
- read posts
- search/read users
- list/read tags
- read notifications
- read Skills and prompts

### Standard Access

Minimum plus:

- create discussions
- edit own discussions where Flarum allows it
- create posts
- edit own posts where Flarum allows it
- mark notifications read

### Advanced Access

Standard plus configurable moderation:

- delete discussions
- hide/restore discussions
- lock/unlock discussions
- pin/unpin discussions
- delete/hide/restore posts
- edit/suspend users
- manage tags
- read approvals

### Full Access

Every supported non-administrative MCP permission, including permissions
registered dynamically by approved third-party integrations. It must be selected
explicitly. It still does not bypass Flarum policies, and it does not include
administrator-only management permissions.

## Permission identifiers

| Group | Identifiers |
| --- | --- |
| System | `system.info.read`, `system.statistics.read`, `system.settings.read`, `system.settings.edit`, `system.extensions.read`, `system.extensions.manage` |
| Discussions | `discussions.search`, `discussions.read`, `discussions.create`, `discussions.edit`, `discussions.delete` |
| Moderation | `discussions.hide`, `discussions.restore`, `discussions.lock`, `discussions.unlock`, `discussions.pin`, `discussions.unpin`, `posts.hide`, `posts.restore` |
| Posts | `posts.read`, `posts.create`, `posts.edit`, `posts.delete` |
| Users | `users.search`, `users.read`, `users.edit`, `users.suspend` |
| Tags | `tags.list`, `tags.read`, `tags.manage` |
| Notifications | `notifications.read`, `notifications.update` |
| Skills | `skills.read`, `skills.install`, `skills.manage` |
| Prompts | `prompts.read`, `prompts.manage` |
| Audit | `audit.read` |
| Integrations | `approvals.read`, `approvals.manage`, `integrations.read`, `integrations.manage`, `tools.manage` |

`approvals.manage`, `integrations.manage` and `tools.manage` are
administrator-only. Custom profiles cannot grant them.

## Custom profiles

Create a profile in **MCP Server → Permission Profiles** and choose individual
permission identifiers. You can:

- create, edit and delete custom profiles
- duplicate any built-in or custom profile
- export a profile as JSON
- import a profile as a new custom profile
- clone a built-in profile

Import format:

```json
{
  "format": "flarum-mcp-profile",
  "version": 1,
  "name": "Support AI",
  "description": "Read and reply only",
  "permissions": ["discussions.read", "posts.create"]
}
```

Imports never overwrite built-ins. An imported profile is created as a custom
profile with a unique name.

Example custom profile:

| Permission | Value |
| --- | --- |
| Read discussions | YES |
| Create discussions | YES |
| Reply to discussions | YES |
| Edit own posts | YES |
| Search users | YES |
| Edit users | NO |
| Delete posts | NO |
| Moderate discussions | NO |
| Manage extensions | NO |
| Read statistics | YES |

## Tool mapping

| Tool | Permission |
| --- | --- |
| `search_discussions` | `discussions.search` |
| `get_discussion` | `discussions.read` (posts require `posts.read`) |
| `create_discussion` | `discussions.create` |
| `edit_discussion` | `discussions.edit` |
| `delete_discussion` | `discussions.delete` |
| `list_recent_discussions` | `discussions.search` |
| `get_posts`, `get_post` | `posts.read` |
| `create_post` | `posts.create` |
| `edit_post` | `posts.edit` |
| `delete_post` | `posts.delete` |
| `hide_discussion`, `restore_discussion` | `discussions.hide`, `discussions.restore` |
| `hide_post`, `restore_post` | `posts.hide`, `posts.restore` |
| `lock_discussion`, `unlock_discussion` | `discussions.lock`, `discussions.unlock` |
| `sticky_discussion`, `unsticky_discussion` | `discussions.pin`, `discussions.unpin` |
| `search_users` | `users.search` |
| `get_user`, `get_current_user` | `users.read` |
| `list_tags`, `get_tag` | `tags.list`, `tags.read` |
| `get_notifications` | `notifications.read` |
| `mark_notification_read` | `notifications.update` |
| `get_forum_info` | `system.info.read` |
| `get_forum_statistics` | `system.statistics.read` |
| `get_recent_activity` | `posts.read` |
| `get_system_settings` | `system.settings.read` |
| `set_system_setting` | `system.settings.edit` |
| `list_skills`, `get_skill` | `skills.read` |
| `get_audit_logs` | `audit.read` |
| `get_pending_approvals` | `approvals.read` |

## Flarum policies still apply

Examples:

- Creating a discussion also requires Flarum's `startDiscussion` ability and
  tag permissions.
- Editing a post also requires Flarum's `edit` policy, which may depend on
  authorship and the forum's editing window.
- Hiding content also requires Flarum's `hide` policy.
- A moderator connection cannot edit an administrator-only setting unless the
  assigned user is an administrator.
- Private discussions, restricted tags and hidden posts remain invisible
  according to Flarum's visibility scopes.

When Flarum denies an operation, the MCP tool returns a safe error and writes an
audit record. It does not reveal the protected content.

## Approval interaction

A high-risk tool with approval enabled does not execute on the first call. The
connection receives an approval id. The request is re-checked after approval, so
revoking a permission or disabling a tool in the meantime prevents execution.

## Testing permissions

Test at least:

- a user who can perform the action and one who cannot
- a visible and an invisible record
- a tool that is visible but whose target is protected
- a disabled tool
- a connection with an expired or revoked token
- an approval that is rejected, expires or is approved
