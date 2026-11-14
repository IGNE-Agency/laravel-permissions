<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\Normalizer\SegmentNormalizer;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\PermissionName;
use Igne\Permissions\Tests\Fixtures\TestLeadPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;

final class PermissionNameTest extends PermissionTestCase
{
    public function test_make_with_prefix_and_action(): void
    {
        $name = PermissionName::make(
            permission: TestResourcePermission::ARTICLE,
            action: PermissionAction::VIEW,
            prefix: 'backend',
        );

        $this->assertSame('backend:article:view', (string) $name);
        $this->assertSame('backend:article:view', $name->value);
    }

    public function test_make_with_action_only(): void
    {
        $name = PermissionName::make(permission: TestResourcePermission::ARTICLE, action: PermissionAction::VIEW_ANY);

        $this->assertSame('article:viewAny', (string) $name);
    }

    public function test_make_with_space_resolves_the_space_prefix(): void
    {
        $name = PermissionName::make(
            permission: TestResourcePermission::USER,
            action: PermissionAction::VIEW,
            space: TestSpace::ADMIN,
        );

        $this->assertSame('backend:user:view', (string) $name);
    }

    public function test_make_with_group_renders_it_between_space_and_permission(): void
    {
        $name = PermissionName::make(
            permission: TestLeadPermission::PHONE,
            action: PermissionAction::VIEW_ANY,
            space: TestSpace::PORTAL,
            group: 'leadSales',
            prefix: 'app',
        );

        $this->assertSame('app:portal:leadSales:phone:viewAny', (string) $name);
    }

    public function test_the_group_is_normalized_like_every_segment(): void
    {
        $name = PermissionName::make(
            permission: TestLeadPermission::WEB_FORM,
            action: PermissionAction::VIEW_ANY,
            group: 'LeadSales',
            normalizer: SegmentNormalizer::snake(),
        );

        $this->assertSame('lead_sales:web_form:view_any', (string) $name);
    }

    public function test_make_with_prefix_only(): void
    {
        $name = PermissionName::make(permission: TestResourcePermission::USER, prefix: 'backend');

        $this->assertSame('backend:user', (string) $name);
    }

    public function test_make_with_a_string_permission_and_nothing_else(): void
    {
        $this->assertSame('access', (string) PermissionName::make(permission: 'access'));
        $this->assertSame('article', (string) PermissionName::make(permission: TestResourcePermission::ARTICLE));
    }

    public function test_a_multi_word_value_is_camel_cased(): void
    {
        $name = PermissionName::make(
            permission: TestResourcePermission::BLOG_POST,
            action: PermissionAction::VIEW,
            prefix: 'backend',
        );

        $this->assertSame('backend:blogPost:view', (string) $name);
    }

    public function test_make_with_a_custom_separator(): void
    {
        $name = PermissionName::make(
            permission: TestResourcePermission::ARTICLE,
            action: PermissionAction::VIEW,
            prefix: 'backend',
            separator: '.',
        );

        $this->assertSame('backend.article.view', (string) $name);
    }

    public function test_null_and_empty_segments_are_omitted(): void
    {
        $name = PermissionName::make(
            permission: TestResourcePermission::ARTICLE,
            action: PermissionAction::VIEW,
            group: '',
            prefix: null,
        );

        $this->assertSame('article:view', (string) $name);
    }

    public function test_each_normalizer_applies_to_every_segment(): void
    {
        $segments = ['permission' => TestResourcePermission::BLOG_POST, 'action' => PermissionAction::VIEW_ANY, 'prefix' => 'backend'];

        $this->assertSame('backend:blogPost:viewAny', (string) PermissionName::make(...$segments, normalizer: SegmentNormalizer::camel()));
        $this->assertSame('backend:blog_post:view_any', (string) PermissionName::make(...$segments, normalizer: SegmentNormalizer::snake()));
        $this->assertSame('backend:blog-post:view-any', (string) PermissionName::make(...$segments, normalizer: SegmentNormalizer::kebab()));
        $this->assertSame('Backend:BlogPost:ViewAny', (string) PermissionName::make(...$segments, normalizer: SegmentNormalizer::studly()));
    }

    public function test_a_normalizer_is_built_from_its_config_name(): void
    {
        $this->assertSame('blogPost', SegmentNormalizer::fromName('camel')('BlogPost'));
        $this->assertSame('blog_post', SegmentNormalizer::fromName('snake')('BlogPost'));
        $this->assertSame('blog-post', SegmentNormalizer::fromName('kebab')('BlogPost'));
        $this->assertSame('BlogPost', SegmentNormalizer::fromName('studly')('blog_post'));
    }

    public function test_a_custom_closure_can_normalize(): void
    {
        $upper = SegmentNormalizer::using(static fn (string $segment): string => strtoupper($segment));

        $this->assertSame('BACKEND:ARTICLE', (string) PermissionName::make(permission: TestResourcePermission::ARTICLE, prefix: 'backend', normalizer: $upper));
    }

    public function test_an_unknown_normalizer_name_throws(): void
    {
        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('Unknown segment normalizer [headline]');

        SegmentNormalizer::fromName('headline');
    }
}
