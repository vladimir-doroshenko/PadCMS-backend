<?php
/**
 * @file
 * AM_Cli_Task_CreateThumbnails class definition.
 *
 * LICENSE
 *
 * $DOXY_LICENSE
 *
 * @author $DOXY_AUTHOR
 * @version $DOXY_VERSION
 */

/**
 * This task creates thumbnail for all resources
 * @ingroup AM_Cli
 */
class AM_Cli_Task_CreateThumbnails extends AM_Cli_Task_Abstract
{
    /** @var AM_Handler_Thumbnail */
    protected $_oThumbnailer = null; /**< @type AM_Handler_Thumbnail */

    protected function _configure()
    {
    }

    public function execute()
    {
        $this->_oThumbnailer = AM_Handler_Locator::getInstance()->getHandler('thumbnail');

        $this->_echo('Resizing elements');
        $this->_resizeElements();

        $this->_echo('Resizing TOC');
        $this->_resizeTOC();

        $this->_echo('Resizing horizontal pdfs');
        $this->_resizeHorizontalPdfs();
    }

    /**
     * Resizes all elements with type "resource"
     */
    protected function _resizeElements()
    {
        $oQuery = AM_Model_Db_Table_Abstract::factory('element_data')
                ->select()
                ->where(sprintf('key_name IN ("%s", "%s", "%s")', AM_Model_Db_Element_Data_Resource::DATA_KEY_RESOURCE
                                                                , AM_Model_Db_Element_Data_MiniArticle::DATA_KEY_THUMBNAIL
                                                                , AM_Model_Db_Element_Data_MiniArticle::DATA_KEY_THUMBNAIL_SELECTED));
        $oElementDatas = AM_Model_Db_Table_Abstract::factory('element_data')->fetchAll($oQuery);

        foreach ($oElementDatas as $oElementData) {
            $this->_resizeImage($oElementData->value, $oElementData->id_element, AM_Model_Db_Element_Data_Resource::TYPE, $oElementData->key_name);
        }
    }

    /**
     * Resizes all TOC terms
     */
    protected function _resizeTOC()
    {
        $oQuery = AM_Model_Db_Table_Abstract::factory('term')
                ->select()
                ->where('(thumb_stripe IS NOT NULL OR thumb_summary IS NOT NULL) AND deleted = "no"');

        $oTerms = AM_Model_Db_Table_Abstract::factory('term')->fetchAll($oQuery);

        foreach ($oTerms as $oTerm) {
            if (!empty($oTerm->thumb_stripe)) {
                $this->_resizeImage($oTerm->thumb_stripe, $oTerm->id, AM_Model_Db_Term_Data_Resource::TYPE, AM_Model_Db_Term_Data_Resource::RESOURCE_KEY_STRIPE);
            }
            if (!empty($oTerm->thumb_summary)) {
                $this->_resizeImage($oTerm->thumb_summary, $oTerm->id, AM_Model_Db_Term_Data_Resource::TYPE, AM_Model_Db_Term_Data_Resource::RESOURCE_KEY_SUMMARY);
            }
        }
    }

    /**
     * Resizes all horizontal pages
     */
    protected function _resizeHorizontalPdfs()
    {
        $oQuery = AM_Model_Db_Table_Abstract::factory('page_horisontal')
                ->select()
                ->where('resource IS NOT NULL');

        $oPagesHorizaontal = AM_Model_Db_Table_Abstract::factory('page_horisontal')->fetchAll($oQuery);

        foreach ($oPagesHorizaontal as $oPageHorizontal) {
            $this->_resizeImage($oPageHorizontal->resource, $oPageHorizontal->id_issue, AM_Model_Db_PageHorisontal::RESOURCE_TYPE, $oPageHorizontal->weight);
        }
    }


    /**
     * Resizes given image
     * @param string $sFileBaseName
     * @param int $iElementId The id of element, term, horisontal page
     * @param string $sResourceType The type of resource's parent (element, toc, cache-static-pdf)
     * @param string $sResourceKeyName The name of the resource type (resource, thumbnail, etc)
     * @return @void
     */
    protected function _resizeImage($sFileBaseName, $iElementId, $sResourceType, $sResourceKeyName)
    {
        $sFileExtension = strtolower(pathinfo($sFileBaseName, PATHINFO_EXTENSION));

        $sFilePath = AM_Tools::getContentPath($sResourceType, $iElementId)
                    . DIRECTORY_SEPARATOR
                    . $sResourceKeyName . '.' . $sFileExtension;
        try {
            $this->_oThumbnailer->clearSources()
                    ->addSourceFile($sFilePath)
                    ->loadAllPresets($sResourceType)
                    ->createThumbnails();

            $this->_echo(sprintf('%s', $sFilePath), 'success');
        } catch (Exception $oException) {
            $this->_echo(sprintf('%s', $sFilePath), 'error');
        }
    }
}