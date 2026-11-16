<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Carbon\Carbon;
use Flarum\Api\JsonApi;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\PostResource;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Foundation\Config;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\LocaleManager;
use Flarum\Notification\Notification;
use Flarum\Post\Post;
use Flarum\Post\PostRepository;
use Flarum\Search\SearchCriteria;
use Flarum\Search\SearchManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Laminas\Diactoros\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Safe, permission-aware access to Flarum data.
 *
 * Read operations use Flarum repositories and search services (so visibility
 * scopes apply). Write operations reuse Flarum's JSON:API resources internally,
 * which preserves validation, policies, domain events, tag handling and
 * extension hooks.
 */
final class FlarumDataService
{
    public function __construct(
        private readonly JsonApi $api,
        private readonly SearchManager $search,
        private readonly DiscussionRepository $discussions,
        private readonly PostRepository $posts,
        private readonly UrlGenerator $url,
        private readonly SettingsRepositoryInterface $settings,
        private readonly Config $config,
        private readonly LocaleManager $locales
    ) {
    }

    public function forumInfo(User $actor): array
    {
        return [
            'title' => (string) $this->settings->get('forum_title', ''),
            'description' => (string) $this->settings->get('forum_description', ''),
            'baseUrl' => (string) $this->config->url(),
            'locale' => (string) $this->settings->get('default_locale', 'en'),
            'version' => app()->version(),
            'logoUrl' => $this->assetUrl($this->settings->get('logo_path')),
            'logoDarkModeUrl' => $this->assetUrl($this->settings->get('logo_dark_mode_path')),
            'faviconUrl' => $this->assetUrl($this->settings->get('favicon_path')),
            'registrationOpen' => (bool) $this->settings->get('allow_sign_up', false),
            'viewer' => $this->serializeUser($actor, $actor),
        ];
    }

    public function forumStatistics(User $actor): array
    {
        $discussionQuery = Discussion::query()->whereVisibleTo($actor);
        $postQuery = Post::query()->whereVisibleTo($actor)->where('type', 'comment');

        $tags = 0;

        if (class_exists(\Flarum\Tags\Tag::class)) {
            $tags = \Flarum\Tags\Tag::query()->whereVisibleTo($actor)->count();
        }

        return [
            'users' => User::query()->count(),
            'discussions' => $discussionQuery->count(),
            'posts' => $postQuery->count(),
            'tags' => $tags,
        ];
    }

    public function forumConfiguration(User $actor): array
    {
        return [
            'forum_title' => $this->settings->get('forum_title'),
            'forum_description' => $this->settings->get('forum_description'),
            'welcome_title' => $this->settings->get('welcome_title'),
            'default_locale' => $this->settings->get('default_locale'),
            'available_locales' => array_keys($this->locales->getLocales()),
            'theme_primary_color' => $this->settings->get('theme_primary_color'),
            'theme_secondary_color' => $this->settings->get('theme_secondary_color'),
            'allow_sign_up' => (bool) $this->settings->get('allow_sign_up', false),
        ];
    }

    /**
     * @param array{q?: string, tag?: string, author?: string} $filters
     * @return array{discussions: array<int, array<string, mixed>>, total: int, hasMore: bool}
     */
    public function searchDiscussions(
        User $actor,
        array $filters,
        int $limit = 10,
        int $offset = 0,
        string $sort = 'newest'
    ): array {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);

        $criteriaFilters = [];

        if (isset($filters['q']) && is_string($filters['q']) && trim($filters['q']) !== '') {
            $criteriaFilters['q'] = trim($filters['q']);
        }

        if (isset($filters['author']) && is_string($filters['author']) && $filters['author'] !== '') {
            $criteriaFilters['author'] = ltrim($filters['author'], '@');
        }

        if (isset($filters['tag']) && $filters['tag'] !== '' && $filters['tag'] !== null && class_exists(\Flarum\Tags\Tag::class)) {
            $criteriaFilters['tag'] = $this->tagSlug((string) $filters['tag'], $actor);
        }

        $sortMap = [
            'newest' => ['-lastPostedAt'],
            'oldest' => ['lastPostedAt'],
            'created' => ['-createdAt'],
            'relevance' => [],
        ];

        $sortArray = $sortMap[$sort] ?? $sortMap['newest'];

        $results = $this->search->query(
            Discussion::class,
            new SearchCriteria(
                actor: $actor,
                filters: $criteriaFilters,
                limit: $limit,
                offset: $offset,
                sort: $sortArray,
                sortIsDefault: $sortArray !== []
            )
        );

        $collection = $results->getResults();
        $this->eagerLoadDiscussions($collection);

        return [
            'discussions' => $collection->map(fn (Discussion $discussion): array => $this->serializeDiscussion($discussion, $actor))->all(),
            'total' => $results->getTotalResults(),
            'hasMore' => $results->areMoreResults(),
        ];
    }

    public function getDiscussion(
        User $actor,
        int $discussionId,
        bool $includePosts = true,
        int $postLimit = 20,
        int $postOffset = 0,
        string $postSort = 'oldest'
    ): array {
        $discussion = $this->discussions->findOrFail($discussionId, $actor);
        $discussion->loadMissing(['user']);

        $data = $this->serializeDiscussion($discussion, $actor);

        if ($includePosts) {
            $postLimit = max(1, min(50, $postLimit));
            $postOffset = max(0, $postOffset);

            $query = Post::query()
                ->where('discussion_id', $discussion->id)
                ->where('type', 'comment')
                ->whereVisibleTo($actor)
                ->with(['user'])
                ->orderBy('number', $postSort === 'newest' ? 'desc' : 'asc')
                ->skip($postOffset)
                ->take($postLimit + 1);

            $posts = $query->get();
            $hasMore = $posts->count() > $postLimit;

            if ($hasMore) {
                $posts->pop();
            }

            $data['posts'] = $posts->map(fn (Post $post): array => $this->serializePost($post, $actor))->all();
            $data['postCount'] = Post::query()
                ->where('discussion_id', $discussion->id)
                ->where('type', 'comment')
                ->whereVisibleTo($actor)
                ->count();
            $data['postsHasMore'] = $hasMore;
        }

        return $data;
    }

    public function createDiscussion(User $actor, ServerRequestInterface $request, string $title, string $content, array $tags = []): array
    {
        $attributes = [
            'title' => $title,
            'content' => $content,
        ];

        $relationships = [];

        if ($tags !== [] && class_exists(\Flarum\Tags\Tag::class)) {
            $tagIds = $this->resolveTagIds($tags, $actor);

            $relationships['tags'] = [
                'data' => array_map(
                    fn (int $id): array => ['type' => 'tags', 'id' => (string) $id],
                    $tagIds
                ),
            ];
        }

        $body = [
            'data' => [
                'attributes' => $attributes,
                'relationships' => $relationships,
            ],
        ];

        /** @var Discussion $discussion */
        $discussion = $this->api->withRequest($this->cleanRequest($request))
            ->forResource(DiscussionResource::class)
            ->forEndpoint('create')
            ->process($body, [], ['actor' => $actor]);

        if (! $discussion instanceof Discussion) {
            throw new RuntimeException('Flarum did not return the created discussion.');
        }

        $discussion->refresh();
        $discussion->loadMissing(['user']);

        return $this->serializeDiscussion($discussion, $actor);
    }
    public function updateDiscussion(User $actor, ServerRequestInterface $request, int $discussionId, ?string $title, ?bool $isHidden): array
    {
        $discussion = $this->discussions->findOrFail($discussionId, $actor);

        if ($title !== null) {
            $actor->assertCan('rename', $discussion);
        }

        if ($isHidden !== null) {
            $actor->assertCan('hide', $discussion);
        }

        $attributes = [];

        if ($title !== null) {
            $attributes['title'] = $title;
        }

        if ($isHidden !== null) {
            $attributes['isHidden'] = $isHidden;
        }

        if ($attributes !== []) {
            $this->apiHandle(
                DiscussionResource::class,
                'update',
                '/discussions/'.$discussionId,
                'PATCH',
                [
                    'data' => [
                        'id' => (string) $discussionId,
                        'attributes' => $attributes,
                    ],
                ],
                $actor,
                $request
            );
        }

        $discussion->refresh();

        return $this->serializeDiscussion($discussion->loadMissing('user'), $actor);
    }

    /**
     * Update arbitrary writable discussion fields. Used for the optional
     * lock/sticky integrations, which register their fields on Flarum's own
     * DiscussionResource.
     *
     * @param array<string, mixed> $attributes
     */
    public function updateDiscussionAttributes(
        User $actor,
        ServerRequestInterface $request,
        int $discussionId,
        array $attributes
    ): array {
        $discussion = $this->discussions->findOrFail($discussionId, $actor);

        $this->apiHandle(
            DiscussionResource::class,
            'update',
            '/discussions/'.$discussionId,
            'PATCH',
            [
                'data' => [
                    'id' => (string) $discussionId,
                    'attributes' => $attributes,
                ],
            ],
            $actor,
            $request
        );

        $discussion->refresh();

        return $this->serializeDiscussion($discussion->loadMissing('user'), $actor);
    }

    public function deleteDiscussion(User $actor, ServerRequestInterface $request, int $discussionId): array
    {        $discussion = $this->discussions->findOrFail($discussionId, $actor);
        $actor->assertCan('delete', $discussion);

        $this->apiHandle(
            DiscussionResource::class,
            'delete',
            '/discussions/'.$discussionId,
            'DELETE',
            ['data' => ['id' => (string) $discussionId]],
            $actor,
            $request
        );

        return ['deleted' => true, 'id' => $discussionId];
    }

    public function getPosts(User $actor, int $discussionId, int $limit = 20, int $offset = 0, string $sort = 'oldest'): array
    {
        $discussion = $this->discussions->findOrFail($discussionId, $actor);
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);

        $query = Post::query()
            ->where('discussion_id', $discussion->id)
            ->where('type', 'comment')
            ->whereVisibleTo($actor)
            ->with(['user'])
            ->orderBy('number', $sort === 'newest' ? 'desc' : 'asc')
            ->skip($offset)
            ->take($limit + 1);

        $posts = $query->get();
        $hasMore = $posts->count() > $limit;

        if ($hasMore) {
            $posts->pop();
        }

        return [
            'discussionId' => $discussion->id,
            'posts' => $posts->map(fn (Post $post): array => $this->serializePost($post, $actor))->all(),
            'hasMore' => $hasMore,
        ];
    }

    public function getPost(User $actor, int $postId): array
    {
        $post = $this->posts->findOrFail($postId, $actor);
        $post->loadMissing('user');

        return $this->serializePost($post, $actor);
    }

    public function createPost(User $actor, ServerRequestInterface $request, int $discussionId, string $content): array
    {
        $discussion = $this->discussions->findOrFail($discussionId, $actor);
        $actor->assertCan('reply', $discussion);

        $request = $this->cleanRequest($request)
            ->withAttribute('ipAddress', Authenticator::clientIp($request));

        /** @var Post $post */
        $post = $this->api->withRequest($request)
            ->forResource(PostResource::class)
            ->forEndpoint('create')
            ->process([
                'data' => [
                    'attributes' => ['content' => $content],
                    'relationships' => [
                        'discussion' => [
                            'data' => ['type' => 'discussions', 'id' => (string) $discussionId],
                        ],
                    ],
                ],
            ], [], ['actor' => $actor]);

        if (! $post instanceof Post) {
            throw new RuntimeException('Flarum did not return the created post.');
        }

        $post->refresh();
        $post->loadMissing(['user', 'discussion']);

        return $this->serializePost($post, $actor);
    }

    public function updatePost(User $actor, ServerRequestInterface $request, int $postId, ?string $content, ?bool $isHidden): array
    {
        $post = $this->posts->findOrFail($postId, $actor);

        if ($content !== null) {
            $actor->assertCan('edit', $post);
        }

        if ($isHidden !== null) {
            $actor->assertCan('hide', $post);
        }

        $attributes = [];

        if ($content !== null) {
            $attributes['content'] = $content;
        }

        if ($isHidden !== null) {
            $attributes['isHidden'] = $isHidden;
        }

        if ($attributes !== []) {
            $this->apiHandle(
                PostResource::class,
                'update',
                '/posts/'.$postId,
                'PATCH',
                [
                    'data' => [
                        'id' => (string) $postId,
                        'attributes' => $attributes,
                    ],
                ],
                $actor,
                $request
            );
        }

        $post->refresh();

        return $this->serializePost($post->loadMissing(['user', 'discussion']), $actor);
    }

    public function deletePost(User $actor, ServerRequestInterface $request, int $postId): array
    {
        $post = $this->posts->findOrFail($postId, $actor);
        $actor->assertCan('delete', $post);

        $this->apiHandle(
            PostResource::class,
            'delete',
            '/posts/'.$postId,
            'DELETE',
            ['data' => ['id' => (string) $postId]],
            $actor,
            $request
        );

        return ['deleted' => true, 'id' => $postId];
    }

    /**
     * @return array{users: array<int, array<string, mixed>>, total: int, hasMore: bool}
     */
    public function searchUsers(User $actor, string $query, int $limit = 10, int $offset = 0): array
    {
        $actor->assertCan('searchUsers');

        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);

        $filters = [];

        if (trim($query) !== '') {
            $filters['q'] = trim($query);
        }

        $results = $this->search->query(
            User::class,
            new SearchCriteria(
                actor: $actor,
                filters: $filters,
                limit: $limit,
                offset: $offset,
                sort: ['username'],
                sortIsDefault: true
            )
        );

        $collection = $results->getResults();
        $collection->load('visibleGroups');

        return [
            'users' => $collection->map(fn (User $user): array => $this->serializeUser($user, $actor))->all(),
            'total' => $results->getTotalResults(),
            'hasMore' => $results->areMoreResults(),
        ];
    }

    public function getUser(User $actor, ?int $id, ?string $username): array
    {
        $query = User::query()->whereVisibleTo($actor);

        if ($id !== null) {
            $user = $query->findOrFail($id);
        } elseif ($username !== null && $username !== '') {
            $user = $query->where('username', $username)->firstOrFail();
        } else {
            throw new ValidationException(['user' => 'Provide a user id or username.']);
        }

        $user->loadMissing('visibleGroups');

        return $this->serializeUser($user, $actor);
    }

    public function currentUser(User $actor): array
    {
        $actor->loadMissing('visibleGroups');

        return $this->serializeUser($actor, $actor);
    }

    /**
     * @return array{tags: array<int, array<string, mixed>>}
     */
    public function listTags(User $actor): array
    {
        if (! class_exists(\Flarum\Tags\Tag::class)) {
            throw new ValidationException(['tags' => 'The Tags extension is not enabled.']);
        }

        $tags = \Flarum\Tags\Tag::query()
            ->whereVisibleTo($actor)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return [
            'tags' => $tags->map(fn ($tag): array => $this->serializeTag($tag))->all(),
        ];
    }

    public function getTag(User $actor, ?int $id, ?string $slug): array
    {
        if (! class_exists(\Flarum\Tags\Tag::class)) {
            throw new ValidationException(['tags' => 'The Tags extension is not enabled.']);
        }

        $query = \Flarum\Tags\Tag::query()->whereVisibleTo($actor);

        if ($id !== null) {
            $tag = $query->findOrFail($id);
        } elseif ($slug !== null && $slug !== '') {
            $tag = $query->where('slug', $slug)->firstOrFail();
        } else {
            throw new ValidationException(['tag' => 'Provide a tag id or slug.']);
        }

        return $this->serializeTag($tag);
    }

    /**
     * @return array{notifications: array<int, array<string, mixed>>, unreadCount: int}
     */
    public function notifications(User $actor, int $limit = 20, int $offset = 0, bool $unreadOnly = false): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);

        $query = Notification::query()
            ->where('user_id', $actor->id)
            ->where('is_deleted', false)
            ->whereSubjectVisibleTo($actor)
            ->with(['fromUser', 'subject'])
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $total = (clone $query)->count();
        $items = $query->skip($offset)->take($limit)->get();

        return [
            'notifications' => $items->map(fn (Notification $notification): array => $this->serializeNotification($notification, $actor))->all(),
            'unreadCount' => Notification::query()
                ->where('user_id', $actor->id)
                ->where('is_deleted', false)
                ->whereNull('read_at')
                ->whereSubjectVisibleTo($actor)
                ->count(),
            'total' => $total,
        ];
    }

    public function markNotificationRead(User $actor, int $notificationId): array
    {
        $notification = Notification::query()
            ->where('user_id', $actor->id)
            ->where('is_deleted', false)
            ->findOrFail($notificationId);

        if ($notification->read_at === null) {
            $notification->read();
            $notification->save();
        }

        return [
            'id' => $notification->id,
            'readAt' => $notification->read_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>}
     */
    public function recentActivity(User $actor, int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));

        $posts = Post::query()
            ->where('type', 'comment')
            ->whereVisibleTo($actor)
            ->with(['user', 'discussion'])
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();

        return [
            'items' => $posts->map(function (Post $post) use ($actor): array {
                return [
                    'type' => 'post',
                    'post' => $this->serializePost($post, $actor),
                    'discussion' => $post->discussion ? [
                        'id' => $post->discussion->id,
                        'title' => $post->discussion->title,
                        'url' => $this->discussionUrl($post->discussion->id),
                    ] : null,
                ];
            })->all(),
        ];
    }

    public function getSystemSettings(User $actor): array
    {
        $actor->assertAdmin();

        return [
            'settings' => $this->forumConfiguration($actor),
            'editableKeys' => array_keys($this->editableSettingRules()),
        ];
    }

    public function setSystemSetting(User $actor, string $key, mixed $value): array
    {
        $actor->assertAdmin();

        $rules = $this->editableSettingRules();

        if (! isset($rules[$key])) {
            throw new ValidationException(['setting' => 'This setting is not editable through MCP.']);
        }

        $value = $this->validateSettingValue($key, $value, $rules[$key]);

        $this->settings->set($key, $value);

        return [
            'key' => $key,
            'value' => $value,
            'settings' => $this->forumConfiguration($actor),
        ];
    }

    /**
     * @return array<string, array{type: string, max?: int}>
     */
    private function editableSettingRules(): array
    {
        return [
            'forum_title' => ['type' => 'string', 'max' => 100],
            'forum_description' => ['type' => 'string', 'max' => 500],
            'welcome_title' => ['type' => 'string', 'max' => 100],
            'default_locale' => ['type' => 'locale'],
            'theme_primary_color' => ['type' => 'color'],
            'theme_secondary_color' => ['type' => 'color'],
            'allow_sign_up' => ['type' => 'boolean'],
        ];
    }

    private function validateSettingValue(string $key, mixed $value, array $rule): mixed
    {
        return match ($rule['type']) {
            'string' => $this->validatedString($key, $value, $rule['max'] ?? 500),
            'color' => $this->validatedColor($key, $value),
            'boolean' => (bool) $value,
            'locale' => $this->validatedLocale($key, $value),
            default => throw new ValidationException([$key => 'Unsupported setting type.']),
        };
    }

    private function validatedString(string $key, mixed $value, int $max): string
    {
        if (! is_string($value)) {
            throw new ValidationException([$key => 'This setting must be a string.']);
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > $max) {
            throw new ValidationException([$key => "This setting must be between 1 and $max characters."]);
        }

        return $value;
    }

    private function validatedColor(string $key, mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            throw new ValidationException([$key => 'This setting must be a six-digit hex colour.']);
        }

        return strtolower($value);
    }

    private function validatedLocale(string $key, mixed $value): string
    {
        if (! is_string($value) || ! $this->locales->hasLocale($value)) {
            throw new ValidationException([$key => 'This locale is not installed.']);
        }

        return $value;
    }

    public function serializeDiscussion(Discussion $discussion, User $actor, bool $includeFirstPost = false): array
    {
        $discussion->loadMissing('user');

        $tags = [];

        if (class_exists(\Flarum\Tags\Tag::class) && method_exists($discussion, 'tags')) {
            $discussion->loadMissing('tags');
            $tags = $discussion->tags->map(fn ($tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
                'icon' => $tag->icon,
            ])->all();
        }

        $data = [
            'id' => $discussion->id,
            'title' => $discussion->title,
            'slug' => $discussion->slug,
            'url' => $this->discussionUrl($discussion->id),
            'author' => $discussion->user ? $this->serializeUserSummary($discussion->user) : null,
            'createdAt' => $discussion->created_at?->toIso8601String(),
            'lastPostedAt' => $discussion->last_posted_at?->toIso8601String(),
            'commentCount' => $discussion->comment_count,
            'participantCount' => $discussion->participant_count,
            'isHidden' => $discussion->hidden_at !== null,
            'isSticky' => (bool) ($discussion->getAttribute('is_sticky') ?? false),
            'isLocked' => (bool) ($discussion->getAttribute('is_locked') ?? false),
            'tags' => $tags,
        ];

        if ($includeFirstPost && $discussion->first_post_id) {
            $firstPost = Post::query()
                ->where('id', $discussion->first_post_id)
                ->whereVisibleTo($actor)
                ->first();

            if ($firstPost) {
                $data['firstPost'] = $this->serializePost($firstPost, $actor);
            }
        }

        return $data;
    }

    public function serializePost(Post $post, User $actor): array
    {
        $post->loadMissing(['user', 'discussion']);

        return [
            'id' => $post->id,
            'discussionId' => $post->discussion_id,
            'number' => $post->number,
            'type' => $post->type,
            'content' => $post->content,
            'url' => $this->postUrl($post->discussion_id, $post->number),
            'author' => $post->user ? $this->serializeUserSummary($post->user) : null,
            'createdAt' => $post->created_at?->toIso8601String(),
            'editedAt' => $post->edited_at?->toIso8601String(),
            'isHidden' => $post->hidden_at !== null,
        ];
    }

    public function serializeUser(User $user, User $actor): array
    {
        $user->loadMissing('visibleGroups');

        return [
            'id' => $user->id,
            'username' => $user->username,
            'displayName' => $user->display_name ?: $user->username,
            'avatarUrl' => $user->avatar_url,
            'bio' => $user->getAttribute('bio'),
            'joinedAt' => $user->joined_at?->toIso8601String(),
            'discussionCount' => $user->discussion_count,
            'commentCount' => $user->comment_count,
            'groups' => $user->visibleGroups->map(fn ($group): array => [
                'id' => $group->id,
                'name' => $group->name_singular ?: $group->name_plural,
                'color' => $group->color,
                'icon' => $group->icon,
            ])->all(),
            'url' => $this->userUrl($user->username),
            'isSelf' => $actor->id === $user->id,
        ];
    }

    private function serializeUserSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'displayName' => $user->display_name ?: $user->username,
            'avatarUrl' => $user->avatar_url,
            'url' => $this->userUrl($user->username),
        ];
    }

    private function serializeTag($tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'description' => $tag->description,
            'color' => $tag->color,
            'icon' => $tag->icon,
            'isPrimary' => (bool) $tag->is_primary,
            'isHidden' => (bool) $tag->is_hidden,
            'isRestricted' => (bool) $tag->is_restricted,
            'parentId' => $tag->parent_id,
            'position' => $tag->position,
            'discussionCount' => $tag->discussion_count,
            'url' => $this->tagUrl($tag->slug),
        ];
    }

    private function serializeNotification(Notification $notification, User $actor): array
    {
        $subject = $notification->subject;

        $subjectData = null;

        if ($subject instanceof Discussion) {
            $subjectData = [
                'type' => 'discussion',
                'id' => $subject->id,
                'title' => $subject->title,
                'url' => $this->discussionUrl($subject->id),
            ];
        } elseif ($subject instanceof Post) {
            $subjectData = [
                'type' => 'post',
                'id' => $subject->id,
                'discussionId' => $subject->discussion_id,
                'url' => $this->postUrl($subject->discussion_id, $subject->number),
            ];
        } elseif ($subject instanceof User) {
            $subjectData = $this->serializeUserSummary($subject);
        }

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'fromUser' => $notification->fromUser ? $this->serializeUserSummary($notification->fromUser) : null,
            'subject' => $subjectData,
            'data' => $notification->data,
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param Collection<int, Discussion> $discussions
     */
    private function eagerLoadDiscussions(Collection $discussions): void
    {
        if ($discussions->isEmpty()) {
            return;
        }

        $discussions->loadMissing('user');

        if (class_exists(\Flarum\Tags\Tag::class) && method_exists($discussions->first(), 'tags')) {
            $discussions->loadMissing('tags');
        }
    }

    private function cleanRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request
            ->withParsedBody(null)
            ->withAttribute('bypassCsrfToken', true);
    }

    /**
     * Run a non-create resource endpoint through Flarum's normal endpoint
     * handling, which resolves the model from the path and applies visibility
     * and authorization before the action runs.
     *
     * @param class-string $resourceClass
     * @param array<string, mixed> $body
     */
    private function apiHandle(
        string $resourceClass,
        string $endpoint,
        string $path,
        string $method,
        array $body,
        User $actor,
        ServerRequestInterface $request
    ): ResponseInterface {
        $request = RequestUtil::withActor(
            $this->cleanRequest($request)
                ->withMethod($method)
                ->withUri(new Uri($path))
                ->withParsedBody($body),
            $actor
        );

        return $this->api
            ->withRequest($request)
            ->forResource($resourceClass)
            ->forEndpoint($endpoint)
            ->handle($request);
    }

    /**
     * @param array<int, string|int> $tags
     * @return int[]
     */
    private function resolveTagIds(array $tags, User $actor): array
    {
        $ids = [];

        foreach ($tags as $tag) {
            $query = \Flarum\Tags\Tag::query()->whereVisibleTo($actor);

            if (is_numeric($tag)) {
                $id = $query->whereKey((int) $tag)->value('id');
            } else {
                $id = $query->where('slug', (string) $tag)->value('id');
            }

            if ($id === null) {
                throw new ValidationException(['tags' => "Unknown or inaccessible tag: $tag"]);
            }

            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    private function tagSlug(string $tag, User $actor): string
    {
        if (! is_numeric($tag)) {
            return $tag;
        }

        $slug = \Flarum\Tags\Tag::query()
            ->whereVisibleTo($actor)
            ->whereKey((int) $tag)
            ->value('slug');

        if ($slug === null) {
            throw new ValidationException(['tag' => 'Unknown or inaccessible tag.']);
        }

        return (string) $slug;
    }

    private function discussionUrl(int $id): string
    {
        try {
            return $this->url->to('forum')->route('discussion', ['id' => $id]);
        } catch (\Throwable) {
            return rtrim((string) $this->config->url(), '/')."/d/$id";
        }
    }

    private function postUrl(int $discussionId, int $number): string
    {
        try {
            return $this->url->to('forum')->route('discussion', ['id' => $discussionId, 'near' => $number]);
        } catch (\Throwable) {
            return rtrim((string) $this->config->url(), '/')."/d/$discussionId/$number";
        }
    }

    private function userUrl(string $username): string
    {
        try {
            return $this->url->to('forum')->route('user', ['username' => $username]);
        } catch (\Throwable) {
            return rtrim((string) $this->config->url(), '/')."/u/$username";
        }
    }

    private function tagUrl(string $slug): string
    {
        try {
            return $this->url->to('forum')->route('tag', ['slug' => $slug]);
        } catch (\Throwable) {
            return rtrim((string) $this->config->url(), '/')."/t/$slug";
        }
    }

    private function assetUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            return resolve('filesystem')->disk('flarum-assets')->url($path);
        } catch (\Throwable) {
            return rtrim((string) $this->config->url(), '/').'/assets/'.ltrim($path, '/');
        }
    }
}
