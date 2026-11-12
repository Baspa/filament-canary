<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\CommentedPostResource\Pages;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\CommentedPostResource;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostCommentResource;
use Filament\Resources\Pages\ManageRelatedRecords;

class ManagePostComments extends ManageRelatedRecords
{
    protected static string $resource = CommentedPostResource::class;

    protected static string $relationship = 'postComments';

    protected static ?string $relatedResource = PostCommentResource::class;
}
