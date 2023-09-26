<?php

declare(strict_types=1);

namespace Freento\AuditOverview\Api;

use Magento\Framework\Phrase;

/**
 * Wrapper for bin/magento
 */
interface MagentoInterface
{
    public const CACHE_DISABLED = 0;
    public const CACHE_ENABLED = 1;
    public const CACHE_INVALIDATED = 2;

    public const COMMUNITY = 'Community';
    public const ENTERPRISE = 'Enterprise';

    /**
     * Returns Magento version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Returns Magento edition
     *
     * @return string
     */
    public function getEdition(): string;

    /**
     * Returns deploy mode
     *
     * @return string
     */
    public function getDeployMode(): string;

    /**
     * Returns cache status
     *
     * @return string[][]
     */
    public function getCacheInfo(): array;

    /**
     * Returns indexers status
     *
     * @return string[][]|Phrase[][]
     */
    public function getReindexStatus(): array;

    /**
     * Returns frontName from config if configured or from env.php
     *
     * @return string
     */
    public function getFrontName(): string;
}
