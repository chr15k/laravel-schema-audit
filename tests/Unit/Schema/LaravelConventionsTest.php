<?php

declare(strict_types=1);

use Chr15k\SchemaAudit\Schema\LaravelConventions;

it('derives table and foreign key names from model classes', function (): void {
    $conventions = new LaravelConventions;

    expect($conventions->tableNameFromModel('App\\Models\\BlogPost::class'))->toBe('blog_posts')
        ->and($conventions->foreignKeyColumnFromModel('App\\Models\\BlogPost::class'))->toBe('blog_post_id')
        ->and($conventions->tableNameFromForeignKey('blog_post_id'))->toBe('blog_posts');
});

it('builds conventional index names and normalises separators', function (): void {
    $conventions = new LaravelConventions;

    expect($conventions->indexName('articles', ['category-id', 'author.id'], 'index'))
        ->toBe('articles_category_id_author_id_index');
});

it('applies table prefixes to simple and qualified table names', function (): void {
    $conventions = new LaravelConventions(prefixIndexes: true, tablePrefix: 'app_');

    expect($conventions->indexName('users', ['email'], 'unique'))
        ->toBe('app_users_email_unique')
        ->and($conventions->indexName('tenant.users', ['email'], 'unique'))
        ->toBe('tenant_app_users_email_unique');
});
