<?php

declare(strict_types=1);

namespace Freento\AuditOverview\Block\Adminhtml;

use Composer\Json\JsonValidationException;
use Freento\AuditModuleList\Api\Data\ModuleInterface;
use Freento\AuditModuleList\Exception\NoRepositoriesException;
use Freento\AuditModuleList\Exception\SetupException;
use Freento\AuditOverview\Api\MagentoInterface;
use Freento\AuditOverview\Api\PhpInterface;
use Freento\AuditOverview\Api\ServerEnvironmentInterface;
use Freento\AuditOverview\Model\Magento;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mview\View\StateInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;

class Report extends Template
{
    public const INDEX_TTL_IN_HOURS = 72;

    private const CURRENT_PHP_VERSION = '8.0.0';
    private const INDEX_TIME_TO_LIVE = self::INDEX_TTL_IN_HOURS * 60 * 60;
    private const CLASS_CRITICAL = 'grid-severity-critical';
    private const CLASS_NOTICE = 'grid-severity-notice';
    private const CLASS_MINOR = 'grid-severity-minor';
    private const CLASS_MAJOR = 'grid-severity-major';
    private const DEFAULT_FRONT_NAME = 'admin';

    /**
     * @var string
     */
    protected $_template = 'Freento_AuditOverview::report.phtml';

    /**
     * @var MagentoInterface
     */
    private MagentoInterface $magento;

    /**
     * @var PhpInterface
     */
    private PhpInterface $php;

    /**
     * @var ServerEnvironmentInterface
     */
    private ServerEnvironmentInterface $serverEnvironment;

    /**
     * @var ModuleInterface
     */
    private ModuleInterface $module;

    /**
     * @var DateTimeFactory
     */
    private DateTimeFactory $dateTimeFactory;

    /**
     * @param MagentoInterface $magento
     * @param PhpInterface $php
     * @param ServerEnvironmentInterface $serverEnvironment
     * @param Context $context
     * @param ModuleInterface $module
     * @param DateTimeFactory $dateTimeFactory
     */
    public function __construct(
        MagentoInterface $magento,
        PhpInterface $php,
        ServerEnvironmentInterface $serverEnvironment,
        Context $context,
        ModuleInterface $module,
        DateTimeFactory $dateTimeFactory
    ) {
        $this->magento = $magento;
        $this->php = $php;
        $this->serverEnvironment = $serverEnvironment;
        $this->module = $module;
        $this->dateTimeFactory = $dateTimeFactory;
        parent::__construct($context);
    }

    /**
     * Get wrapper for bin/magento
     *
     * @return MagentoInterface
     */
    public function getMagento(): MagentoInterface
    {
        return $this->magento;
    }

    /**
     * Check if Magento version is outdated
     *
     * @throws JsonValidationException
     * @throws NoRepositoriesException
     * @throws SetupException
     * @throws LocalizedException
     */
    public function isMagentoOutdated(): ?bool
    {
        $edition = $this->getMagento()->getEdition();
        switch ($edition) {
            case MagentoInterface::COMMUNITY:
                $package = 'magento/product-community-edition';
                break;
            case MagentoInterface::ENTERPRISE:
                $package = 'magento/product-enterprise-edition';
                break;
            default:
                $package = '';
                break;
        }

        $latest = $this->module->getLatestModuleVersion($package);
        return ($package !== '' && $latest !== ModuleInterface::PARAMETER_N_A && $latest !== '0')
        ? version_compare(
            $this->getMagento()->getVersion(),
            $latest,
            '<'
        ) : null;
    }

    /**
     * Get grid css class for cache status
     *
     * @param int $status
     * @return string
     */
    public function getCacheStatusCssClass(int $status): string
    {
        switch ($status) {
            case MagentoInterface::CACHE_DISABLED:
                $class = self::CLASS_CRITICAL;
                break;
            case MagentoInterface::CACHE_ENABLED:
                $class = self::CLASS_NOTICE;
                break;
            case MagentoInterface::CACHE_INVALIDATED:
                $class = self::CLASS_MINOR;
                break;
            default:
                $class = self::CLASS_MAJOR;
                break;
        }

        return $class;
    }

    /**
     * Get caption for cache status
     *
     * @param int $status
     * @return Phrase
     */
    public function getCacheStatusCaption(int $status): Phrase
    {
        switch ($status) {
            case MagentoInterface::CACHE_DISABLED:
                $caption = __('Disabled');
                break;
            case MagentoInterface::CACHE_ENABLED:
                $caption = __('Enabled');
                break;
            case MagentoInterface::CACHE_INVALIDATED:
                $caption = __('Invalidated');
                break;
            default:
                $caption = __('Unknown');
                break;
        }

        return $caption;
    }

    /**
     * Get grid css class for reindex mode
     *
     * @param string $mode
     * @return string
     */
    public function getReindexModeCssClass(string $mode): string
    {
        switch ($mode) {
            case 'Save':
                $class = self::CLASS_MAJOR;
                break;
            case 'Schedule':
                $class = self::CLASS_NOTICE;
                break;
            default:
                $class = self::CLASS_CRITICAL;
                break;
        }

        return $class;
    }

    /**
     * Get caption for reindex mode
     *
     * @param string $mode
     * @return Phrase
     */
    public function getReindexModeCaption(string $mode): Phrase
    {
        switch ($mode) {
            case 'Save':
                $caption = __('Update on save');
                break;
            case 'Schedule':
                $caption = __('Update by schedule');
                break;
            default:
                $caption = __('Reindex mode unknown');
        }

        return $caption;
    }

    /**
     * Get css class for reindex status
     *
     * @param string $status
     * @return string
     */
    public function getReindexStatusCssClass(string $status): string
    {
        switch ($status) {
            case 'Reindex required':
                $class = self::CLASS_CRITICAL;
                break;
            case 'Ready':
                $class = self::CLASS_NOTICE;
                break;
            case 'Processing':
                $class = self::CLASS_MINOR;
                break;
            default:
                $class = self::CLASS_MAJOR;
                break;
        }

        return $class;
    }

    /**
     * Get css class for scheduled status
     *
     * @param Phrase $statusPhrase
     * @return string
     */
    public function getScheduleStatusCssClass(Phrase $statusPhrase): string
    {
        $arguments = $statusPhrase->getArguments();
        $status = $arguments['status'] ?? '';
        $count = $arguments['count'] ?? 0;
        if ($count > 1000) {
            $class = self::CLASS_CRITICAL;
        } elseif ($count > 100) {
            $class = self::CLASS_MAJOR;
        } elseif ($count > 10) {
            $class = self::CLASS_MINOR;
        } else {
            $class = self::CLASS_NOTICE;
        }

        if ($status !== StateInterface::STATUS_IDLE) {
            $class = self::CLASS_MINOR;
        }

        return $class;
    }

    /**
     * Check if index is outdated
     *
     * @param string $updated
     * @return bool
     */
    public function isIndexOutdated(string $updated): bool
    {
        $dateTime = $this->dateTimeFactory->create();
        return $dateTime->timestamp() - $dateTime->timestamp($updated) > self::INDEX_TIME_TO_LIVE;
    }

    /**
     * Get object providing information about installed php instance
     *
     * @return PhpInterface
     */
    public function getPhp(): PhpInterface
    {
        return $this->php;
    }

    /**
     * Check if php is outdated
     *
     * @return bool|int
     */
    public function isPhpOutdated()
    {
        return version_compare($this->getPhp()->getVersion(), self::CURRENT_PHP_VERSION, '<');
    }

    /**
     * Get object providing information about server environment
     *
     * @return ServerEnvironmentInterface
     */
    public function getServerEnvironment(): ServerEnvironmentInterface
    {
        return $this->serverEnvironment;
    }

    /**
     * Check if front name is set to default
     *
     * @return bool
     */
    public function isFrontNameInsecure(): bool
    {
        // TODO: implement for Magento Cloud
        return $this->getMagento()->getFrontName() === self::DEFAULT_FRONT_NAME;
    }
}
