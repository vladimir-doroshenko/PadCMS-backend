<?php
/**
 * @file
 * AM_Component_Filter class definition.
 *
 * LICENSE
 *
 * $DOXY_LICENSE
 *
 * @author $DOXY_AUTHOR
 * @version $DOXY_VERSION
 */

/**
 * Filter componet
 * @ingroup AM_Component
 * @todo refactoring
 */
class AM_Component_Filter extends Volcano_Component_Record
{
    /** @var array */
    protected $_aParams    = array(); /**< @type array */
    /** @var array */
    protected $_aUrlParams = null; /**< @type array */
    /** @var Zend_Db_Adapter_Abstract */
    protected $_oDbAdapter = null; /**< @type Zend_Db_Adapter_Abstract */

    public function __construct(AM_Controller_Action $oActionController, $sName, $aParams)
    {
        $this->_aParams    = $aParams;
        $this->_oDbAdapter = $oActionController->oDb;

        $aControls = array();
        foreach ($aParams['controls'] as $sKey => $aValue) {
            $sControlName = isset($aValue['name']) ? $aValue['name'] : $sKey;

            $aControls[] = new Volcano_Component_Control(
                $oActionController,
                $sControlName,
                isset($aValue['title'])  ? $aValue['title']   : ucfirst($sControlName),
                isset($aValue['rules'])  ? $aValue['rules']   : null
            );
        }

        parent::__construct($oActionController, $sName, $aControls);
    }

    /**
     * Returns component object by name
     *
     * @param string $sControlName
     * @return Volcano_Component_Control
     */
    public function getControl($sControlName)
    {
        return $this->controls[$sControlName];
    }

    /**
     * Get URLparameters
     *
     * @return array
     */
    public function getUrlParams()
    {
        return $this->_aUrlParams;
    }

    /**
     * @return boolean
     */
    public function operation()
    {
        if (!$this->isSubmitted) {
            $aGetParams = $this->actionController->getRequest()->getParams();
            foreach ($this->controls as $oControl) {
                if (isset($aGetParams[$oControl->getName()])) {
                    $oControl->setValue($aGetParams[$oControl->getName()]);
                }
            }
            return false;
        }

        if (!$this->validate()) {
            return false;
        }

        /* Define urlParams */
        $aPostParams = array();
        $aAllParams  = $this->actionController->getRequest()->getParams();
        if (!isset($aAllParams['reset'])) {
            $aPostParams = $this->actionController->getRequest()->getPost();

            foreach($aPostParams as $sKey => $sValue) {
                if (!$sKey || !$sValue || $sKey == 'form') {
                    unset($aPostParams[$sKey]);
                }
            }
        }

        $this->_aUrlParams = array(
                'controller' => $this->actionController->getRequest()->getParam('controller'),
                'action'     => $this->actionController->getRequest()->getParam('action'),
                'aid'        => $this->actionController->getRequest()->getParam('aid'),
            ) + $aPostParams;

        return true;
    }

    /**
     * @param type $aParams
     */
    public function show($aParams = null)
    {
        $aParams         = $this->actionController->getRequest()->getParams();
        $aDefaultOptions = array('' => 'Not selected');

        $aControlsValues = array();
        foreach ($this->_aParams['controls'] as $sKey => $aValue) {
            $aControlsValues[$sKey] = array();

            if(isset($aValue['values']) && !is_array($aValue['values'])) {
                switch ($aValue['values']) {
                    case 'issue':
                          $aQuery = $this->_oDbAdapter->select()
                                          ->from($aValue['values'], array('id', 'title'))
                                          ->where('application = ?', $aParams['aid']);

                          $aControlsValues[$sKey] = $this->_oDbAdapter->fetchPairs($aQuery);
                      break;
                    default:
                      break;
                }
            }

            if (!isset($this->_aParams['controls'][$sKey]['values']) || !is_array($aValue['values'])) {
                is_array($aControlsValues[$sKey]) ? $aControlsValues[$sKey] = $aDefaultOptions + $aControlsValues[$sKey] : $aDefaultOptions;
            } else {
                $aControlsValues[$sKey] = $this->_aParams['controls'][$sKey]['values'];
            }
        }

        $this->actionController->view->{$this->getName()} = array(
                'controlsValues' => $aControlsValues);

        parent::show();
    }
}