# Tools, Resources and Prompts

Tools are executable operations. Resources are readable data addressed by URI.
Prompts are reusable workflows or instructions. They have independent
permissions and are exposed only when the connection profile grants them.

## Output format

Tools return MCP content plus `structuredContent` for object results. The text
fallback is JSON, so clients that do not render structured content still receive
the full result.

Tool-level failures use `isError: true` with a safe message. Protocol errors
(unknown tool, invalid arguments) use JSON-RPC errors. The server never returns
raw exception messages to an AI client.

## Discussion tools

| Tool | Permission | Args |
| --- | --- | --- |
| `search_discussions` | `discussions.search` | `query`, `tag`, `author`, `limit` 1–50, `offset`, `sort` (`newest`, `oldest`, `created`, `relevance`) |
| `get_discussion` | `discussions.read` (+ `posts.read` for posts) | `id`, `includePosts`, `postLimit` 1–50, `postOffset`, `postSort` |
| `create_discussion` | `discussions.create` | `title` 3–80, `content`, `tags` (slugs or ids) |
| `edit_discussion` | `discussions.edit` | `id`, `title` and/or `isHidden` |
| `delete_discussion` | `discussions.delete` | `id`; approval by default |
| `list_recent_discussions` | `discussions.search` | `limit`, `offset` |

Creation uses Flarum's `DiscussionResource` internally, so validation, tag
rules, first-post creation, domain events and policies behave like the normal
API.

## Post tools

| Tool | Permission | Args |
| --- | --- | --- |
| `get_posts` | `posts.read` | `discussionId`, `limit`, `offset`, `sort` |
| `get_post` | `posts.read` | `id` |
| `create_post` | `posts.create` | `discussionId`, `content` |
| `edit_post` | `posts.edit` | `id`, `content` and/or `isHidden` |
| `delete_post` | `posts.delete` | `id`; approval by default |

Post content is returned as plain text derived from Flarum's formatter, not raw
HTML.

## Moderation tools

| Tool | Permission | Args |
| --- | --- | --- |
| `hide_discussion` | `discussions.hide` | `id`; approval by default |
| `restore_discussion` | `discussions.restore` | `id`; approval by default |
| `hide_post` | `posts.hide` | `id`; approval by default |
| `restore_post` | `posts.restore` | `id`; approval by default |
| `lock_discussion` | `discussions.lock` | `id`; requires Flarum Lock |
| `unlock_discussion` | `discussions.unlock` | `id`; requires Flarum Lock |
| `sticky_discussion` | `discussions.pin` | `id`; requires Flarum Sticky |
| `unsticky_discussion` | `discussions.unpin` | `id`; requires Flarum Sticky |

Lock and sticky tools are registered only when the corresponding extension is
enabled. They also require the normal Flarum ability (`lock` / `sticky`).

## User tools

| Tool | Permission | Args |
| --- | --- | --- |
| `search_users` | `users.search` | `query`, `limit`, `offset` |
| `get_user` | `users.read` | `id` or `username` |
| `get_current_user` | `users.read` | none |

User output contains public profile fields and visible groups. It never returns
email addresses, password hashes, access tokens, IP addresses, sessions or
private preferences.

## Tag tools

| Tool | Permission | Args |
| --- | --- | --- |
| `list_tags` | `tags.list` | none |
| `get_tag` | `tags.read` | `id` or `slug` |

These require Flarum Tags. Restricted and hidden tags follow Flarum visibility.

## Notification tools

| Tool | Permission | Args |
| --- | --- | --- |
| `get_notifications` | `notifications.read` | `limit`, `offset`, `unreadOnly` |
| `mark_notification_read` | `notifications.update` | `id` |

Only notifications belonging to the connection user are returned or modified.

## Forum and system tools

| Tool | Permission | Args |
| --- | --- | --- |
| `get_forum_info` | `system.info.read` | none |
| `get_forum_statistics` | `system.statistics.read` | none |
| `get_recent_activity` | `posts.read` | `limit` |
| `get_system_settings` | `system.settings.read` | none; administrator only |
| `set_system_setting` | `system.settings.edit` | `key`, `value`; administrator only; approval by default |

`set_system_setting` accepts only:

- `forum_title`
- `forum_description`
- `welcome_title`
- `default_locale`
- `theme_primary_color`
- `theme_secondary_color`
- `allow_sign_up`

It is not a general settings endpoint. Mail, OAuth, database, extension and
secret settings are never exposed.

## Skill, audit and approval tools

| Tool | Permission | Args |
| --- | --- | --- |
| `list_skills` | `skills.read` | none |
| `get_skill` | `skills.read` | `slug` |
| `get_audit_logs` | `audit.read` | `limit`, `offset`, `connectionId`, `toolName`, `status` |
| `get_pending_approvals` | `approvals.read` | `limit`, `offset` |

Non-administrators only see their own connection's audit records and approvals.

## Resources

| URI | Permission | Description |
| --- | --- | --- |
| `flarum://forum/info` | `system.info.read` | Public forum information |
| `flarum://forum/statistics` | `system.statistics.read` | Aggregate counts |
| `flarum://forum/configuration` | `system.settings.read` | Safe configuration only |
| `flarum://discussions/recent` | `discussions.read` | Recent discussions |
| `flarum://tags` | `tags.list` | Visible tags |
| `flarum://users/me` | `users.read` | Connection user |
| `flarum://notifications` | `notifications.read` | Connection notifications |
| `flarum://skills` | `skills.read` | Enabled Skills |
| `flarum://discussions/{id}` | `posts.read` | Discussion and visible posts |
| `flarum://users/{id}` | `users.read` | One visible user |
| `flarum://tags/{slug}` | `tags.read` | One visible tag |
| `flarum://skills/{slug}` | `skills.read` | One SKILL.md document |

Unavailable resources are not listed, and reading one directly returns an MCP
error rather than leaking the resource.

## Prompts

| Prompt | Permission | Arguments |
| --- | --- | --- |
| `summarize_discussion` | `discussions.read` | `discussion_id` |
| `analyze_community_activity` | `system.statistics.read` | `period` |
| `find_unanswered_discussions` | `discussions.read` | `tag`, `limit` |
| `generate_moderation_report` | `system.statistics.read` | `period` |
| `summarize_recent_activity` | `posts.read` | `limit` |
| `analyze_discussion_trends` | `discussions.read` | `period` |
| `improve_discussion_title` | `prompts.read` | `title`, `context` |
| `draft_forum_announcement` | `prompts.read` | `topic`, `tone` |

Prompts that include forum data fetch it with the connection's permissions and
wrap it in a delimited data block. The instructions tell the model to treat
forum content as data, not as instructions.

## Tool discovery

`tools/list` is filtered dynamically. It includes a tool only when:

- the profile and optional connection allowlist grant its permission,
- the tool is globally enabled,
- its required Flarum extension is enabled,
- its third-party integration is approved.

Execution re-checks all of those conditions.

## Approval-required result

When an approval is required, the tool result is a normal success result with:

```json
{
  "status": "approval_required",
  "approval_id": 42,
  "tool": "delete_discussion",
  "expires_at": "2026-11-16T12:00:00+00:00",
  "message": "This operation requires administrator approval. It has not been executed."
}
```

The operation runs only after an administrator approves it, and only if the
permission is still valid at that moment.
