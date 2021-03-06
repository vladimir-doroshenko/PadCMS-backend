<?php
/**
 * @file
 * AM_Model_Db_Application class definition.
 *
 * LICENSE
 *
 * $DOXY_LICENSE
 *
 * @author $DOXY_AUTHOR
 * @version $DOXY_VERSION
 */

/**
 * Application model class
 * @ingroup AM_Model
 */
class AM_Model_Db_Application extends AM_Model_Db_Abstract
{
    /** @var AM_Model_Db_Vocabulary **/
    protected $_oVocabularyTag = null; /**< @type AM_Model_Db_Vocabulary */

    /** @var AM_Model_Db_Vocabulary **/
    protected $_oVocabularyToc = null; /**< @type AM_Model_Db_Vocabulary */

    /** @var AM_Model_Db_Rowset_Issue **/
    protected $_oIssues = null; /**< @type AM_Model_Db_Rowset_Issue */

    /**
     * Set TOC vocabulary
     * @param AM_Model_Db_Vocabulary $oVocabulary
     * @return AM_Model_Db_Application
     */
    public function setVocabularyToc(AM_Model_Db_Vocabulary $oVocabulary)
    {
        $this->_oVocabularyToc = $oVocabulary;

        return $this;
    }

    /**
     * Get TOC vocabulary
     * @return AM_Model_Db_Vocabulary
     */
    public function getVocabularyToc()
    {
        if (empty($this->_oVocabularyToc)) {
            $this->fetchVocabularyToc();
        }
        return $this->_oVocabularyToc;
    }

    /**
     * Fetch TOC vocabulary
     * @return AM_Model_Db_Application
     */
    public function fetchVocabularyToc()
    {
        $oVocabularyTable = AM_Model_Db_Table_Abstract::factory('vocabulary');
        /* @var $oVocabularyTable AM_Model_Db_Table_Vocabulary */
        $this->_oVocabularyToc = $oVocabularyTable->findOneBy(array('has_hierarchy' => 1, 'multiple' => 0, 'application' => $this->id));

        if (empty($this->_oVocabularyToc)) {
            //Create TOC vovabulary
            $this->_oVocabularyToc = $oVocabularyTable->createTocVocabulary($this);
        }

        $this->_oVocabularyToc->fetchToc();

        return $this;
    }

    /**
     * Set Tag vocabulary
     * @param AM_Model_Db_Vocabulary $oVocabulary
     * @return AM_Model_Db_Application
     */
    public function setVocabularyTag(AM_Model_Db_Vocabulary $oVocabulary)
    {
        $this->_oVocabularyTag = $oVocabulary;

        return $this;
    }

    /**
     * Get Tag vocabulary
     * @return AM_Model_Db_Vocabulary
     */
    public function getVocabularyTag()
    {
        if (empty($this->_oVocabularyTag)) {
            $this->fetchVocabularyTag();
        }

        return $this->_oVocabularyTag;
    }

    /**
     * Fetch Tag vocabulary
     * @return AM_Model_Db_Application
     */
    public function fetchVocabularyTag()
    {
        $oVocabularyTable = AM_Model_Db_Table_Abstract::factory('vocabulary');
        /* @var $oVocabularyTable AM_Model_Db_Table_Vocabulary */
        $this->_oVocabularyTag = $oVocabularyTable->findOneBy(array('has_hierarchy' => 0, 'multiple' => 1, 'application' => $this->id));

        if (empty($this->_oVocabularyTag)) {
            //Create tag vocabulary
            $this->_oVocabularyTag = $oVocabularyTable->createTagVocabulary($this);
        }

        $this->_oVocabularyTag->fetchTags();

        return $this;
    }

    /**
     * Set application issues
     * @param $oIssues AM_Model_Db_IssueSet
     * @return AM_Model_Db_Application
     */
    public function setIssues(AM_Model_Db_Rowset_Issue $oIssues)
    {
        $this->_oIssues = $oIssues;

        return $this;
    }

    /**
     * Get application issues
     * @return AM_Model_Db_Rowset_Issue
     */
    public function getIssues()
    {
        if (empty($this->_oIssues)) {
            $this->fetchIssues();
        }
        return $this->_oIssues;
    }

    /**
     * Fetch all application issues
     * @return AM_Model_Db_Application
     */
    public function fetchIssues()
    {
        $this->_oIssues = AM_Model_Db_Table_Abstract::factory('issue')->findAllBy(array('application' => $this->id));

        if (!empty($this->_oIssues)) {
            $this->_oIssues->setApplication($this);
        }

        return $this;
    }

    /**
     * Move application and all its entities to other user
     * @param AM_Model_Db_User $oUser
     * @return AM_Model_Db_Application
     */
    public function moveToUser(AM_Model_Db_User $oUser)
    {
        //Nothing to move
        if ($oUser->client == $this->client || empty($oUser->client)) {
            return $this;
        }
        $oIssues = $this->getIssues();

        //Change client in application
        $this->client = $oUser->client;
        $this->save();

        if (!empty($oIssues)) {
            $oIssues->moveToUser($oUser);
        }

        return $this;
    }

    /**
     * Copy application and all its entities to other user
     * @param AM_Model_Db_User $oUser
     * @return AM_Model_Db_Application
     */
    public function copyToUser(AM_Model_Db_User $oUser)
    {
        $oIssues = $this->getIssues();

        $aData = array('client' => $oUser->client);

        $this->copy($aData);

        if (!empty($oIssues)) {
            $oIssues->copyToUser($oUser);
        }

        return $this;
    }

    /**
     * Delete application softly
     */
    public function delete()
    {
        $this->getIssues()->delete();

        $this->deleted = 'yes';
        $this->save();
    }
}