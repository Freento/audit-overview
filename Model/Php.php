<?php

declare(strict_types=1);

namespace Freento\AuditOverview\Model;

use Freento\AuditOverview\Api\PhpInterface;

/**
 * @inheritdoc
 */
class Php implements PhpInterface
{
    /**
     * @inheritdoc
     */
    public function getVersion(): string
    {
        return phpversion();
    }

    /**
     * @inheritdoc
     */
    public function getModules(): array
    {
        return get_loaded_extensions();
    }
}
