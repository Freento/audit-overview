<?php

declare(strict_types=1);

namespace Freento\AuditOverview\Model;

use Exception;
use Freento\AuditOverview\Api\ServerEnvironmentInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;

/**
 * @inheritdoc
 */
class ServerEnvironment implements ServerEnvironmentInterface
{
    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @param Curl $curl
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resource
     */
    public function __construct(
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resource
    ) {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->resource = $resource;
    }

    /**
     * @inheritdoc
     */
    public function getMysqlVersion(): string
    {
        return $this->resource->getConnection()->fetchOne('SELECT version()');
    }

    /**
     * @inheritdoc
     */
    public function getElasticVersion(): string
    {
        $host = $this->scopeConfig->getValue('catalog/search/elasticsearch7_server_hostname') ?: 'localhost';
        $port = $this->scopeConfig->getValue('catalog/search/elasticsearch7_server_port') ?: '9200';
        $url = $host . ':' . $port;
        try {
            $this->curl->get($url);
            return json_decode($this->curl->getBody())->version->number;
        } catch (Exception $ignored) {
            return __('Unable to retrieve version because ElasticSearch isn`t installed or is off')->render();
        }
    }
}
