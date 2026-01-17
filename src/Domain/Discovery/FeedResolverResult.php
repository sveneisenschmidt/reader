<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\Discovery;

final class FeedResolverResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    public function __construct(
        private string $resolverName,
        private string $status = self::STATUS_ERROR,
        private ?string $feedUrl = null,
        private ?string $error = null,
    ) {
    }

    public function getFeedUrl(): ?string
    {
        return $this->feedUrl;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(string $error): self
    {
        $this->error = $error;
        $this->feedUrl = null;
        $this->status = self::STATUS_ERROR;

        return $this;
    }

    public function setFeedUrl(string $feedUrl): self
    {
        $this->feedUrl = $feedUrl;
        $this->error = null;
        $this->status = self::STATUS_SUCCESS;

        return $this;
    }

    public function getResolverName(): string
    {
        return $this->resolverName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
