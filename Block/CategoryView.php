<?php
/*
 * @package      Webcode_Glami
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2021 Webcode Ltd. (https://webcode.bg/)
 * @license      See LICENSE.txt for license details.
 */

namespace Webcode\Glami\Block;

use Exception;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Json\Encoder;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Webcode\Glami\Helper\Data as HelperData;

/**
 * Category Information to view
 */
class CategoryView extends Pixel
{

    /**
     * @var Session
     */
    private $catalogSession;

    /**
     * @var CategoryInterface
     */
    private $currentCategory;

    /**
     * @var int
     */
    private $categoryId;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * Constructor.
     *
     * @param HelperData $helper
     * @param StoreManagerInterface $storeManager
     * @param Session $catalogSession
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Encoder $jsonEncoder
     * @param Context $context
     * @param array $data
     *
     * @throws Exception
     */
    public function __construct(
        HelperData $helper,
        StoreManagerInterface $storeManager,
        Session $catalogSession,
        CategoryRepositoryInterface $categoryRepository,
        Encoder $jsonEncoder,
        Context $context,
        array $data = []
    ) {
        $this->catalogSession = $catalogSession;
        $this->categoryRepository = $categoryRepository;
        $this->helper = $helper;

        $this->setEventName('ViewContent');
        $this->assignEventData();
        parent::__construct($helper, $storeManager, $jsonEncoder, $context, $data);
    }

    /**
     * Get product detail info
     * @throws Exception
     */
    public function assignEventData()
    {
        if ($this->getCurrentCategory()) {
            $itemIds = [];

            // TODO: Add Products limited with page and filters (if applied)

            $this->eventData = [
                'item_ids' => $itemIds,
                'content_type' => 'category',
                'category_text' => $this->helper->getCategoryPathName($this->getCurrentCategory())
            ];
        }
    }

    /**
     * @return CategoryInterface|bool
     */
    public function getCurrentCategory()
    {
        if (!$this->currentCategory && ($categoryId = $this->getCategoryId())) {
            try {
                $this->currentCategory = $this->categoryRepository->get($categoryId);
            } catch (NoSuchEntityException $e) {
                $this->helper->logger($e->getMessage());

                return false;
            }
        }

        return $this->currentCategory;
    }

    /**
     * @return int|bool
     */
    private function getCategoryId()
    {
        if (!$this->categoryId && !$this->categoryId = (int)$this->catalogSession->getData('last_viewed_category_id')) {
            return false;
        }

        return $this->categoryId;
    }
}
