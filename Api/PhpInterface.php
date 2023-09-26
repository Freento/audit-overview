<?php

declare(strict_types=1);

namespace Freento\AuditOverview\Api;

/**
 * Provides information about php
 */
interface PhpInterface
{
    /**
     * Return php version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Returns installed php extensions
     *
     * @return string[]
     */
    public function getModules(): array;
}
