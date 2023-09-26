<?php

declare(strict_types=1);

namespace Freento\AuditOverview\Api;

/**
 * Provides servers environment data
 */
interface ServerEnvironmentInterface
{
    /**
     * Returns MySQL version
     *
     * @return string
     */
    public function getMysqlVersion(): string;

    /**
     * Returns ElasticSearch version
     *
     * @return string
     */
    public function getElasticVersion(): string;
}
