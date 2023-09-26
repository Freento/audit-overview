<?php

declare(strict_types=1);

namespace Freento\AuditOverview\Model;

use Freento\AuditOverview\Api\MagentoInterface;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Indexer;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\StateInterface;
use Magento\Framework\Phrase;
use Magento\Indexer\Model\Indexer\CollectionFactory;
use Magento\Framework\App\DeploymentConfig\Reader;

/**
 * @inheritdoc
 */
class Magento implements MagentoInterface
{
    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $metadata;

    /**
     * @var State
     */
    private State $state;

    /**
     * @var Manager
     */
    private Manager $cacheManager;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @var Reader
     */
    private Reader $reader;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $config;

    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

    /**
     * @param ProductMetadataInterface $metadata
     * @param State $state
     * @param Manager $cacheManager
     * @param CollectionFactory $collectionFactory
     * @param TypeListInterface $cacheTypeList
     * @param Reader $reader
     * @param ScopeConfigInterface $config
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        ProductMetadataInterface $metadata,
        State $state,
        Manager $cacheManager,
        CollectionFactory $collectionFactory,
        TypeListInterface $cacheTypeList,
        Reader $reader,
        ScopeConfigInterface $config,
        DeploymentConfig $deploymentConfig
    ) {
        $this->metadata = $metadata;
        $this->state = $state;
        $this->cacheManager = $cacheManager;
        $this->collectionFactory = $collectionFactory;
        $this->cacheTypeList = $cacheTypeList;
        $this->reader = $reader;
        $this->config = $config;
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * @inheritdoc
     */
    public function getVersion(): string
    {
        return $this->metadata->getVersion();
    }

    /**
     * @inheritDoc
     */
    public function getEdition(): string
    {
        return $this->metadata->getEdition();
    }

    /**
     * @inheritdoc
     */
    public function getDeployMode(): string
    {
        return $this->deploymentConfig->get('MAGE_MODE') ?? $this->state->getMode();
    }

    /**
     * Checks if cache of given type is invalidated
     *
     * @param string $cacheType
     * @return bool
     */
    private function isCacheInvalidated(string $cacheType): bool
    {
        return key_exists($cacheType, $this->cacheTypeList->getInvalidated());
    }

    /**
     * @inheritdoc
     */
    public function getCacheInfo(): array
    {
        $caches = [];
        $statuses = $this->cacheManager->getStatus();
        $types = $this->cacheTypeList->getTypes();
        foreach ($statuses as $type => $status) {
            $caches[] = [
                'type' => $type,
                'status' => $this->isCacheInvalidated($type) ? MagentoInterface::CACHE_INVALIDATED : $status,
                'label' => $types[$type]->getCacheType(),
                'description' => $types[$type]->getDescription()
            ];
        }
        return $caches;
    }

    /**
     * Returns indexer status
     *
     * @param IndexerInterface $indexer
     * @return Phrase
     */
    private function getIndexerStatus(Indexer\IndexerInterface $indexer): Phrase
    {
        $status = __('Unknown');
        switch ($indexer->getStatus()) {
            case StateInterface::STATUS_VALID:
                $status = __('Ready');
                break;
            case StateInterface::STATUS_INVALID:
                $status = __('Reindex required');
                break;
            case StateInterface::STATUS_WORKING:
                $status = __('Processing');
                break;
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function getReindexStatus(): array
    {
        $indexers = $this->collectionFactory->create()->getItems();
        $data = [];
        foreach ($indexers as $indexer) {
            $rowData = [
                'id' => $indexer->getId(),
                'title' => $indexer->getTitle(),
                'status' => $this->getIndexerStatus($indexer),
                'update_on' => $indexer->isScheduled() ? __('Schedule') : __('Save'),
                'schedule_status' => '',
            ];
            if ($indexer->isScheduled()) {
                $view = $indexer->getView();
                $state = $view->getState()->loadByView($view->getId());
                $changelog = $view->getChangelog()->setViewId($view->getId());
                $currentVersionId = $changelog->getVersion();
                $count = count($changelog->getList($state->getVersionId(), $currentVersionId));
                $rowData['schedule_status'] = __(
                    '%status (%count in backlog)',
                    [
                        'status' => $state->getStatus(),
                        'count' => $count,
                    ]
                );
            }
            $rowData['updated'] = $indexer->getLatestUpdated();
            $data[] = $rowData;
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function getFrontName(): string
    {
        // TODO: implement for Magento Cloud
        $useCustomFrontName = $this->config->getValue('admin/url/use_custom_path');
        if (!$useCustomFrontName) {
            $env = $this->reader->load(ConfigFilePool::APP_ENV);
            $frontName = $env['backend']['frontName'];
        } else {
            $frontName = $this->config->getValue('admin/url/custom_path');
        }

        return $frontName;
    }
}
