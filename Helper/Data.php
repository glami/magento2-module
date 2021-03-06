<?php
/*
 * @package      Webcode_Glami
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2021 Webcode Ltd. (https://webcode.bg/)
 * @license      See LICENSE.txt for license details.
 */

namespace Webcode\Glami\Helper;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Unserialize\Unserialize;
use Magento\Framework\UrlInterface;
use Magento\Framework\Xml\Parser;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Helper Data
 */
class Data extends AbstractHelper
{
    /**
     * Module Name for settings.
     */
    const MODULE_NAME = 'glami';

    /**
     * Path to module active flag.
     */
    const XML_PATH_SYNC_ENABLED = 'general/enabled';

    /**
     * Path to Config for Pixel ID
     */
    const XML_PATH_PIXEL_ID = 'general/pixel_id';

    /**
     * Feed URL
     */
    const FEED_DIR = 'feed' . DIRECTORY_SEPARATOR . 'glami';

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var Unserialize;
     */
    private $unserialize;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var array
     */
    private $glamiCategories = [];

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * Data constructor.
     *
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param Parser $parser
     * @param Unserialize $unserialize
     * @param DirectoryList $directoryList
     * @param Context $context
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        Parser $parser,
        Unserialize $unserialize,
        DirectoryList $directoryList,
        Context $context
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->parser = $parser;
        $this->unserialize = $unserialize;
        $this->directoryList = $directoryList;
        parent::__construct($context);
    }

    /**
     * Get Config Data
     *
     * @param string $field
     * @param int|bool $storeId
     *
     * @return string
     * @throws Exception
     */
    public function getConfigData($field, $storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $field = self::MODULE_NAME . DIRECTORY_SEPARATOR . $field;

        return $this->scopeConfig->getValue($field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Check is module enabled for selected store.
     *
     * @param int|bool $storeId
     *
     * @return bool
     * @throws Exception
     */
    public function isActive($storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        return (bool)$this->getConfigData(self::XML_PATH_SYNC_ENABLED, $storeId);
    }

    /**
     * Get Current Magento Store
     *
     * @return StoreInterface|null
     */
    public function getCurrentStore()
    {
        try {
            return $this->storeManager->getStore();
        } catch (NoSuchEntityException $e) {
            $this->logger($e->getMessage());
        }

        return $this->storeManager->getDefaultStoreView();
    }

    /**
     * Format Product Price. Convert currency and add currency label.
     *
     * @param float $price
     * @param bool $withCurrencyLabel
     * @param \Magento\Store\Model\Store|null $store
     *
     * @return string
     * @throws \Exception
     */
    public function formatPrice($price, $withCurrencyLabel = true, Store $store = null)
    {
        if (!$store) {
            $store = $this->getCurrentStore();
        }
        /* @phpstan-ignore-next-line */
        $baseCurrencyCode = $store->getBaseCurrencyCode();
        /* @phpstan-ignore-next-line */
        $currentCurrencyCode = $store->getCurrentCurrencyCode();

        if ($baseCurrencyCode !== $currentCurrencyCode) {
            $price = $store->getBaseCurrency()->convert($price, $currentCurrencyCode);
        }

        return number_format($price, 2) . ($withCurrencyLabel ? ' ' . $currentCurrencyCode : '');
    }

    /**
     * Get Current Store Curreny
     *
     * @return string
     */
    public function getCurrentStoreCurrency()
    {
        return $this->getCurrentStore()->getCurrentCurrencyCode();
    }

    /**
     * Get Pixel API Key
     *
     * @return string|null
     */
    public function getPixelId()
    {
        try {
            if ($this->isActive()) {
                return $this->getConfigData('general/pixel_id');
            }
        } catch (Exception $e) {
            $this->logger($e->getMessage());
        }

        return null;
    }

    /**
     * Get Pixel Locale
     *
     * @return string
     */
    public function getPixelLocale()
    {
        try {
            if ($this->isActive()) {
                return $this->getConfigData('general/locale');
            }
        } catch (Exception $e) {
            $this->logger($e->getMessage());
        }

        return 'eco';
    }

    /**
     * @return array
     */
    public function getAllowedAttributes()
    {
        $attributes = null;
        try {
            $attributes = $this->getConfigData('feed/attributes');
        } catch (Exception $e) {
            $this->logger($e->getMessage());
        }

        return explode(',', $attributes);
    }

    /**
     * @param string $field
     *
     * @return string|null
     */
    public function getAttributeCode($field)
    {
        try {
            return $this->getConfigData('feed/' . $field);
        } catch (Exception $e) {
            $this->logger($e->getMessage());

            return null;
        }
    }

    /**
     * @param CategoryInterface $category
     *
     * @return string
     * @SuppressWarnings(PHPMD.ShortVariableNames)
     */
    public function getCategoryPathName(CategoryInterface $category)
    {
        $categories = [];
        foreach ($category->getPathIds() as $pathId) {
            try {
                $cat = $this->categoryRepository->get($pathId);
                $categories[] = $cat->getName();
            } catch (NoSuchEntityException $e) {
                $this->logger($e->getMessage());
            }
        }

        return implode(' | ', $categories);
    }

    private function getCategoriesUrl()
    {
        $urls = [
            'bg' => 'https://www.glami.bg/kategoria-xml/',
            'cz' => 'https://www.glami.cz/category-xml/',
            'hr' => 'https://www.glami.hr/category-xml/',
            'eco' => 'https://www.glami.eco/categorie-xml/',
            'fr' => 'https://www.glami.fr/category-xml/',
            'de' => 'https://www.glami.de/category-xml/',
            'gr' => 'https://www.glami.gr/category-xml/',
            'hu' => 'https://www.glami.hu/kategaria-xml/',
            'pt' => 'https://www.glami.eco/category-xml/',
            'ro' => 'https://www.glami.ro/categorie-xml/',
            'ru' => 'https://www.glami.ru/category-xml/',
            'sk' => 'https://www.glami.sk/category-xml/',
            'si' => 'https://www.glami.si/kategorija-xml/',
            'es' => 'https://www.glami.es/categoria-xml/',
            'tr' => 'https://www.glami.com.tr/kategori-xml/',
            'ee' => 'https://www.glami.ee/kategooria-xml/',
            'lv' => 'https://www.glami.lv/kategorija-xml/',
            'lt' => 'https://www.glami.lt/kategorija-xml/',
            'br' => 'https://www.glami.com.br/category-xml/',
        ];

        return $urls[$this->getPixelLocale()];
    }

    /**
     * @param array $productCategories
     *
     * @return string|null
     */
    public function getGlamiCategory(array $productCategories)
    {
        $categoriesConfigData = '';
        try {
            $categoriesConfigData = $this->getConfigData('feed/categories');
        } catch (Exception $e) {
            $this->logger($e->getMessage());
        }

        if ($categories = $this->unserialize->unserialize($categoriesConfigData)) {
            foreach ($categories as $category) {
                if (!empty($category['target']) && \in_array($category['source_id'], $productCategories, true)) {
                    $glamiCategoryIds[] = $category['target'];
                }
            }
        }

        if (isset($glamiCategoryIds) && $glamiCategories = $this->getGlamiCategories()) {
            return $glamiCategories[max($glamiCategoryIds)];
        }

        return null;
    }

    /**
     * @param array $categories
     * @param array $options
     *
     * @return array
     */
    public function appendChildCategories(array $categories, array $options = [])
    {
        foreach ($categories as $category) {
            if (isset($category['CATEGORY_FULLNAME'])) {
                $options[$category['CATEGORY_ID']] = $category['CATEGORY_FULLNAME'];

                if (isset($category['CATEGORY'])) {
                    $options = $this->appendChildCategories($category['CATEGORY'], $options);
                }
            }
        }

        return $options;
    }

    /**
     * @return array
     */
    public function getGlamiCategories()
    {
        if (empty($this->glamiCategories) &&
            $categories = $this->parser->load($this->getCategoriesUrl())->xmlToArray()
        ) {
            $this->glamiCategories = $this->appendChildCategories($categories['GLAMI']['CATEGORY']);
        }

        return $this->glamiCategories;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getFeedPath()
    {
        return $this->directoryList->getPath(DirectoryList::PUB) . DIRECTORY_SEPARATOR . self::FEED_DIR . DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    public function getFeedUrl()
    {
        if ($store = $this->getCurrentStore()) {
            /* @phpstan-ignore-next-line */
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            $baseUrl = str_replace(UrlInterface::URL_TYPE_MEDIA . '/', '', $baseUrl);
            return $baseUrl . self::FEED_DIR . DIRECTORY_SEPARATOR . $store->getCode() . '.xml';
        }

        return null;
    }

    /**
     * @param string $message
     * @param string $type
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function logger($message, $type = 'alert')
    {
        return $this->_logger->{$type}(self::MODULE_NAME, ['message' => $message]);
    }
}
