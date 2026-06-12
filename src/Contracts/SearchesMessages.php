<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Models\Conversation;
use Syriable\LaravelMessages\Models\Message;

interface SearchesMessages
{
    /**
     * Search message bodies and attachment filenames visible to the user.
     *
     * @return Builder<Message>
     */
    public function messages(Model|int|string $user, string $term): Builder;

    /**
     * Search conversation subjects, participants and content visible to
     * the user.
     *
     * @return Builder<Conversation>
     */
    public function conversations(Model|int|string $user, string $term): Builder;
}
