<?php
/**
 * @file
 * AM_Model_Db_Table_Abstract class definition.
 *
 * LICENSE
 *
 * $DOXY_LICENSE
 *
 * @author $DOXY_AUTHOR
 * @version $DOXY_VERSION
 */

/**
 * The base table gateway class
 * @ingroup AM_Model
 */
abstract class AM_Model_Db_Table_Abstract extends Zend_Db_Table_Abstract
{

    /**
     * Return necessary table
     * @param string $sName DB table name
     * @return AM_Model_Db_Table_Abstract
     */
    public static function factory($sName)
    {
        $oFilter = new Zend_Filter();
        $oFilter->addFilter(new Zend_Filter_Word_UnderscoreToCamelCase());
        $sClassName = AM_Model_Db_Abstract::TABLE_CLASS_PREFIX . '_' . $oFilter->filter($sName);
        if (!class_exists($sClassName, true)) {
            throw new AM_Model_Db_Table_Exception(sprintf('Table class "%s" not found', $sClassName));
        }

        return new $sClassName();
    }

    /**
     * @see Zend_Db_Table_Abstract::init()
     */
    public function init()
    {
        $sPostfix = $this->_getModelName();
        $this->setRowClass(AM_Model_Db_Abstract::ROW_CLASS_PREFIX . '_' . $sPostfix);
        $this->setRowsetClass(AM_Model_Db_Abstract::ROWSET_CLASS_PREFIX . '_' . $sPostfix);
    }

    /**
     * @see Zend_Db_Table_Abstract::_setupTableName
     */
    protected function _setupTableName()
    {
        if (!$this->_name) {
            $oFilter = new Zend_Filter();
            $oFilter->addFilter(new Zend_Filter_Word_CamelCaseToUnderscore())
                   ->addFilter(new Zend_Filter_StringToLower());
            $this->_name = $oFilter->filter($this->_getModelName());
        }
    }

    /**
     * Gets model's class postfix
     *
     * @return void
     * @throws Base_Exception
     */
    protected function _getModelName()
    {
        $sClassName        = get_class($this);
        $aChunks           = explode('_', $sClassName);
        $sClassNamePostfix = array_pop($aChunks);
        if (empty($sClassNamePostfix)) {
            throw new AM_Model_Db_Table_Exception(sprintf('Table class "%s" has wrong name', $sClassName), 501);
        }

        return $sClassNamePostfix;
    }

    /**
     * Find all rows by key and value
     * @param array | string   $mKey
     * @param string           $sValue
     * @param string|array     $mOrder  OPTIONAL An SQL ORDER clause
     * @param int              $iCount  OPTIONAL An SQL LIMIT count
     * @param int              $iOffset OPTIONAL An SQL LIMIT offset
     * @return AM_Model_Db_Rowset_Abstract
     */
    public function findAllBy($mKey, $sValue = null, $mOrder = null, $iCount = null, $iOffset = null)
    {
        $mWhere = $this->_whereValues($mKey, $sValue);
        $oRows  = $this->fetchAll($mWhere, $mOrder, $iCount, $iOffset);

        return $oRows;
    }

    /**
     * Find one row by key and value
     * @param array | string   $mKey
     * @param string           $sValue
     * @param string|array     $mOrder  OPTIONAL An SQL ORDER clause
     * @param int              $iCount  OPTIONAL An SQL LIMIT count
     * @param int              $iOffset OPTIONAL An SQL LIMIT offset
     * @return Zend_Db_Table_Row
     */
    public function findOneBy($mKey, $sValue = null, $mOrder = null, $iCount = null, $iOffset = null)
    {
        $mWhere = $this->_whereValues($mKey, $sValue);
        $oRow   = $this->fetchRow($mWhere, $mOrder, $iCount, $iOffset);

        return $oRow;
    }

    /**
     * Delete rows
     * @param array | string $mKey
     * @param string|null    $sValue
     * @return int The number of rows deleted.
     */
    public function deleteBy($mKey, $sValue = null)
    {
        $mWhere   = $this->_whereValues($mKey, $sValue);
        $iRowsNum = $this->delete($mWhere);

        return $iRowsNum;
    }

    /**
     * Quotes key(s) and value(s)
     * @param array | string $key
     * @param string $sValue
     * @return array | string An SQL-safe quoted value placed into the original text
     */
    protected function _whereValues($mKey, $sValue = null)
    {
        if ($sValue === null && is_array($mKey)) {
            $mWhere = array();
            foreach ($mKey as $sKey => $mValue) {
                $mWhere[] = $this->getAdapter()->quoteInto("$sKey=?", $mValue);
            }
        } else {
            $mKey   = $this->getAdapter()->quoteIdentifier($mKey);
            $mWhere = $this->getAdapter()->quoteInto("$mKey=?", $sValue);
        }

        return $mWhere;
    }

    /**
     * @see Zend_Db_Table_Abstract::insert()
     */
    public function insert(array $aData)
    {
        //TODO: set update & created fields in all timastampable tables
        if (empty($aData['created']) && in_array('created', $this->_getCols())) {
            $aData['created'] = new Zend_Db_Expr('NOW()');
        }

        if (empty($aData['updated']) && in_array('updated', $this->_getCols())) {
            $aData['updated'] = new Zend_Db_Expr('NOW()');
        }

        return parent::insert($aData);
    }

    /**
     * @see Zend_Db_Table_Abstract::update()
     */
    public function update(array $aData, $mWhere)
    {
        //TODO: set update & created fields in all timastampable tables
        if (empty($aData['updated']) && in_array('updated', $this->_getCols())) {
            $aData['updated'] = new Zend_Db_Expr('NOW()');
        }
        return parent::update($aData, $mWhere);
    }
}
